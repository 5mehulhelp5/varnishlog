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

        // First log the complete set of tags being purged
        $this->logger->debug('Raw cache tags received: ' . $allTags, $context);

        // Categorize and log specific types of cache invalidations
        $categoryTags = [];
        $productTags = [];
        $cmsTags = [];
        
        foreach ($tags as $tag) {
            // Category tags: cat_c, cat_c_p, cat_p
            if (strpos($tag, 'cat_c_') === 0) {
                $categoryId = substr($tag, 6);
                $categoryTags[] = ['id' => $categoryId, 'tag' => $tag, 'type' => 'category'];
            } elseif (strpos($tag, 'cat_p_') === 0) {
                $categoryId = substr($tag, 6);
                $categoryTags[] = ['id' => $categoryId, 'tag' => $tag, 'type' => 'category_products'];
            }
            
            // Product tags
            elseif (strpos($tag, 'cat_p') === 0) {
                $productTags[] = $tag;
            }
            
            // CMS tags
            elseif (strpos($tag, 'cms_') === 0) {
                $cmsTags[] = $tag;
            }
        }

        // Log detailed information for each type
        if (!empty($categoryTags)) {
            $this->logger->info(
                'Category cache invalidation',
                array_merge($context, ['category_details' => $categoryTags])
            );
        }

        if (!empty($productTags)) {
            $this->logger->info(
                'Product cache invalidation',
                array_merge($context, ['product_tags' => $productTags])
            );
        }

        if (!empty($cmsTags)) {
            $this->logger->info(
                'CMS cache invalidation',
                array_merge($context, ['cms_tags' => $cmsTags])
            );
        }
    }
        }
    }
}