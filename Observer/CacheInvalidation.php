<?php
declare(strict_types=1);

namespace Kamlesh\VarnishLog\Observer;

use Magento\Framework\Event\ObserverInterface;
use Kamlesh\VarnishLog\Logger\VarnishLogger;
use Magento\Backend\Model\Auth\Session;

class CacheInvalidation implements ObserverInterface
{
    /**
     * @var VarnishLogger
     */
    private VarnishLogger $logger;

    /**
     * @var Session|null
     */
    private ?Session $authSession;

    /**
     * @param VarnishLogger $logger
     * @param Session|null $authSession
     */
    public function __construct(
        VarnishLogger $logger,
        ?Session $authSession = null
    ) {
        $this->logger = $logger;
        $this->authSession = $authSession;
    }

    /**
     * Execute observer
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    public function execute(\Magento\Framework\Event\Observer $observer): void
    {
        $tags = $observer->getEvent()->getTags();
        
        if (!is_array($tags) || empty($tags)) {
            return;
        }

        $user = 'N/A';
        if ($this->authSession && $this->authSession->getUser()) {
            $user = $this->authSession->getUser()->getUserName();
        }

        $allTags = implode(', ', $tags);
        $context = [
            'purge_tags' => $allTags,
            'user' => $user,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        // Log only if we have Magento cache tags
        if (strpos($allTags, 'cat_') !== false || 
            strpos($allTags, 'cms_') !== false || 
            strpos($allTags, 'store_') !== false) {
            
            $this->logger->info('Cache purge for tags: ' . $allTags, $context);

            // Additional details for specific tag types
            foreach ($tags as $tag) {
                if (strpos($tag, 'cat_c_') === 0) {
                    $categoryId = substr($tag, 6); // Remove 'cat_c_' prefix
                    $this->logger->info(
                        'Category cache invalidated',
                        ['category_id' => $categoryId, 'tag' => $tag]
                    );
                } elseif (strpos($tag, 'cms_') === 0) {
                    $this->logger->info(
                        'CMS cache invalidated',
                        ['cms_tag' => $tag]
                    );
                }
            }
        }
    }
}