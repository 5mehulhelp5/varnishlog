<?php
namespace Kamlesh\VarnishLog\Observer;

use Magento\Framework\Event\ObserverInterface;
use Kamlesh\VarnishLog\Logger\VarnishLogger;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Store\Model\StoreManagerInterface;

class CacheInvalidation implements ObserverInterface
{
    /**
     * @var VarnishLogger
     */
    protected $logger;

    /**
     * @var \Magento\Backend\Model\Auth\Session|null
     */
    protected $authSession;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var Curl
     */
    protected $curl;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    public function __construct(
        VarnishLogger $logger,
        \Magento\Backend\Model\Auth\Session $authSession = null,
        RequestInterface $request,
        Curl $curl,
        StoreManagerInterface $storeManager
    ) {
        $this->logger = $logger;
        $this->authSession = $authSession;
        $this->request = $request;
        $this->curl = $curl;
        $this->storeManager = $storeManager;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $tags = $observer->getEvent()->getTags();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'N/A';
        $referrer = $_SERVER['HTTP_REFERER'] ?? 'N/A';
        $user = 'N/A';
        if ($this->authSession && $this->authSession->getUser()) {
            $user = $this->authSession->getUser()->getUserName();
        }

        $allTags = is_array($tags) ? implode(', ', $tags) : 'N/A';
        $context = [
            'ip' => $ip,
            'user' => $user,
            'referrer' => $referrer,
            'request_path' => $this->request->getRequestUri(),
            'all_tags' => $allTags
        ];

        $this->logger->info('Varnish cache purge triggered.', $context);

        if (!is_array($tags)) {
            return;
        }

        foreach ($tags as $tag) {
            if (strpos($tag, 'c_') === 0) {
                $categoryId = substr($tag, 2);
                $this->verifyCategoryCache($categoryId, $tag, $context);
            } elseif (strpos($tag, 'p_') === 0) {
                $productId = substr($tag, 2);
                $this->verifyProductCache($productId, $tag, $context);
            }
        }
    }

    /**
     * Verify category cache synchronization
     *
     * @param string $categoryId
     * @param string $tag
     * @param array $context
     */
    private function verifyCategoryCache($categoryId, $tag, array $context)
    {
        try {
            $stores = $this->storeManager->getStores();
            foreach ($stores as $store) {
                $categoryUrl = $store->getBaseUrl() . 'catalog/category/view/id/' . $categoryId;
                $this->verifyUrl($categoryUrl, "Category ID: {$categoryId}");
            }
            $this->logger->info(
                "Category cache verified successfully",
                array_merge($context, ['category_id' => $categoryId, 'tag' => $tag])
            );
        } catch (\Exception $e) {
            $this->logger->error(
                "Category cache verification failed: " . $e->getMessage(),
                array_merge($context, ['category_id' => $categoryId, 'tag' => $tag])
            );
        }
    }

    /**
     * Verify product cache synchronization
     *
     * @param string $productId
     * @param string $tag
     * @param array $context
     */
    private function verifyProductCache($productId, $tag, array $context)
    {
        try {
            $stores = $this->storeManager->getStores();
            foreach ($stores as $store) {
                $productUrl = $store->getBaseUrl() . 'catalog/product/view/id/' . $productId;
                $this->verifyUrl($productUrl, "Product ID: {$productId}");
            }
            $this->logger->info(
                "Product cache verified successfully",
                array_merge($context, ['product_id' => $productId, 'tag' => $tag])
            );
        } catch (\Exception $e) {
            $this->logger->error(
                "Product cache verification failed: " . $e->getMessage(),
                array_merge($context, ['product_id' => $productId, 'tag' => $tag])
            );
        }
    }

    /**
     * Verify URL cache status
     *
     * @param string $url
     * @param string $context
     * @throws \Exception
     */
    private function verifyUrl($url, $context)
    {
        $this->curl->addHeader('X-Magento-Debug', '1');
        $this->curl->get($url);
        
        $headers = $this->curl->getHeaders();
        
        // Check if we got a HIT from Varnish
        $cacheStatus = $headers['X-Magento-Cache-Debug'] ?? 'MISS';
        
        if ($cacheStatus !== 'HIT') {
            throw new \Exception(
                sprintf(
                    'Cache verification failed for %s. Status: %s',
                    $context,
                    $cacheStatus
                )
            );
        }
    }
}