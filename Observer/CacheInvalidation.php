<?php
namespace Kamlesh\VarnishLog\Observer;

use Magento\Framework\Event\ObserverInterface;
use Kamlesh\VarnishLog\Logger\VarnishLogger;

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

    public function __construct(
        VarnishLogger $logger,
        \Magento\Backend\Model\Auth\Session $authSession = null
    ) {
        $this->logger = $logger;
        $this->authSession = $authSession;
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
        $this->logger->info(sprintf(
            'Varnish cache purge triggered. Time: %s, IP: %s, Referrer: %s, User: %s, Tags: %s',
            date('Y-m-d H:i:s'),
            $ip,
            $referrer,
            $user,
            $allTags
        ));
        if (is_array($tags)) {
            foreach ($tags as $tag) {
                // Log category-related cache invalidations
                if (strpos($tag, 'c_') === 0) {
                    $categoryId = substr($tag, 2);
                    $this->logger->info(sprintf(
                        'Category cache purged. Category ID: %s, Tag: %s, Time: %s, IP: %s, User: %s',
                        $categoryId,
                        $tag,
                        date('Y-m-d H:i:s'),
                        $ip,
                        $user
                    ));
                }
                // Log product-related cache invalidations
                if (strpos($tag, 'p_') === 0) {
                    $productId = substr($tag, 2);
                    $this->logger->info(sprintf(
                        'Product cache purged. Product ID: %s, Tag: %s, Time: %s, IP: %s, User: %s',
                        $productId,
                        $tag,
                        date('Y-m-d H:i:s'),
                        $ip,
                        $user
                    ));
                }
            }
        }
    }
}