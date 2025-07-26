<?php
namespace Defsys\VarnishLog\Observer;

use Magento\Framework\Event\ObserverInterface;
use Defsys\VarnishLog\Logger\VarnishLogger;

class CacheInvalidation implements ObserverInterface
{
    protected $logger;

    public function __construct(
        VarnishLogger $logger
    ) {
        $this->logger = $logger;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $tags = $observer->getEvent()->getTags();
        
        if (is_array($tags)) {
            foreach ($tags as $tag) {
                // Log category-related cache invalidations
                if (strpos($tag, 'c_') === 0) {
                    $categoryId = substr($tag, 2);
                    $this->logger->info(sprintf(
                        'Varnish cache invalidated for category ID: %s, Tag: %s, Time: %s',
                        $categoryId,
                        $tag,
                        date('Y-m-d H:i:s')
                    ));
                }
                
                // Log product-related cache invalidations
                if (strpos($tag, 'p_') === 0) {
                    $productId = substr($tag, 2);
                    $this->logger->info(sprintf(
                        'Varnish cache invalidated for product ID: %s, Tag: %s, Time: %s',
                        $productId,
                        $tag,
                        date('Y-m-d H:i:s')
                    ));
                }
            }
        }
    }
}