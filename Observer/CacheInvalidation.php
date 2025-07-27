<?php
declare(strict_types=1);

namespace Kamlesh\VarnishLog\Observer;

use Magento\Framework\Event\ObserverInterface;
use Kamlesh\VarnishLog\Logger\VarnishLogger;
use Kamlesh\VarnishLog\Logger\CatalogVarnishLogger;
use Magento\Backend\Model\Auth\Session;

class CacheInvalidation implements ObserverInterface
{
    private VarnishLogger $logger;
    private CatalogVarnishLogger $catalogLogger;
    private ?Session $authSession;

    public function __construct(
        VarnishLogger $logger,
        CatalogVarnishLogger $catalogLogger,
        ?Session $authSession = null
    ) {
        $this->logger = $logger;
        $this->catalogLogger = $catalogLogger;
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
        try {
            $event = $observer->getEvent();
            if (!$event) {
                return;
            }

            $tags = $event->getTags();
            $object = $event->getObject();
            
            // If no tags but we have an object that can provide identities, use those
            if ((!is_array($tags) || empty($tags)) && $object && method_exists($object, 'getIdentities')) {
                $tags = $object->getIdentities();
            }

            // Skip if we still have no tags
            if (!is_array($tags) || empty($tags)) {
                return;
            }

            // Split tags into catalog and non-catalog
            $catalogTags = array_filter($tags, function($tag) {
                return strpos($tag, 'cat_') === 0;
            });
            
            $otherTags = array_filter($tags, function($tag) {
                return strpos($tag, 'cat_') !== 0;
            });

            $user = $this->getUserInfo();
            $timestamp = date('Y-m-d H:i:s');

            // Log catalog-related cache invalidations
            if (!empty($catalogTags)) {
                $context = [
                    'purge_tags' => implode(', ', $catalogTags),
                    'user' => $user,
                    'timestamp' => $timestamp,
                    'total_tags' => count($catalogTags)
                ];

                if ($object) {
                    $context['entity_type'] = get_class($object);
                    $context['entity_id'] = method_exists($object, 'getId') ? $object->getId() : 'no_id';
                }

                $categorizedTags = $this->categorizeTags($catalogTags);
                
                if (!empty($categorizedTags['category'])) {
                    $this->catalogLogger->info(
                        'Category cache invalidation',
                        array_merge($context, ['category_details' => $categorizedTags['category']])
                    );
                }

                if (!empty($categorizedTags['product'])) {
                    $this->catalogLogger->info(
                        'Product cache invalidation',
                        array_merge($context, ['product_details' => $categorizedTags['product']])
                    );
                }
            }

            // Log non-catalog cache invalidations
            if (!empty($otherTags)) {
                $context = [
                    'purge_tags' => implode(', ', $otherTags),
                    'user' => $user,
                    'timestamp' => $timestamp,
                    'total_tags' => count($otherTags)
                ];

                if ($object) {
                    $context['entity_type'] = get_class($object);
                    $context['entity_id'] = method_exists($object, 'getId') ? $object->getId() : 'no_id';
                }

                $this->logger->info('Non-catalog cache invalidation', $context);
            }
            
        } catch (\Exception $e) {
            $this->logger->error('Error processing cache tags: ' . $e->getMessage(), [
                'exception' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Get current user information
     *
     * @return string
     */
    private function getUserInfo(): string
    {
        try {
            if ($this->authSession && 
                $this->authSession->getUser() && 
                $this->authSession->getUser()->getUserName()) {
                return $this->authSession->getUser()->getUserName();
            }
        } catch (\Exception $e) {
            $this->logger->warning('Could not get user info: ' . $e->getMessage());
        }
        return 'N/A';
    }

    /**
     * Prepare base context for logging
     *
     * @param array $tags
     * @param string $user
     * @return array
     */
    private function prepareBaseContext(array $tags, string $user): array
    {
        return [
            'purge_tags' => implode(', ', $tags),
            'user' => $user,
            'timestamp' => date('Y-m-d H:i:s'),
            'total_tags' => count($tags)
        ];
    }

    /**
     * Categorize tags by type
     *
     * @param array $tags
     * @return array
     */
    private function categorizeTags(array $tags): array
    {
        if (empty($tags)) {
            return [
                'category' => [],
                'product' => [],
                'cms' => [],
                'other' => []
            ];
        }

        $categorized = [
            'category' => [],
            'product' => [],
            'cms' => [],
            'other' => []
        ];

        foreach ($tags as $tag) {
            if (!is_string($tag)) {
                continue;
            }

            // Category specific tags
            if (strpos($tag, 'cat_c_') === 0) {
                $categoryId = substr($tag, 6);
                $categorized['category'][] = [
                    'id' => $categoryId,
                    'tag' => $tag,
                    'type' => 'category_main',
                    'purge_method' => 'BAN',
                    'pattern' => '.*'
                ];
            } 
            // Category product relation tags
            elseif (strpos($tag, 'cat_p_') === 0) {
                $categoryId = substr($tag, 6);
                $categorized['category'][] = [
                    'id' => $categoryId,
                    'tag' => $tag,
                    'type' => 'category_products',
                    'purge_method' => 'BAN',
                    'pattern' => '.*'
                ];
            }
            // Product specific tags
            elseif (strpos($tag, 'p_') === 0) {
                $productId = substr($tag, 2);
                $categorized['product'][] = [
                    'id' => $productId,
                    'tag' => $tag,
                    'type' => 'product_main',
                    'purge_method' => 'BAN',
                    'pattern' => '.*'
                ];
            }
            // Product category tags
            elseif (strpos($tag, 'cat_p') === 0) {
                $categorized['product'][] = [
                    'tag' => $tag,
                    'type' => 'product_category',
                    'purge_method' => 'BAN',
                    'pattern' => '.*'
                ];
            }
            // CMS page tags
            elseif (strpos($tag, 'cms_p_') === 0) {
                $pageId = substr($tag, 6);
                $categorized['cms'][] = [
                    'id' => $pageId,
                    'tag' => $tag,
                    'type' => 'cms_page',
                    'purge_method' => 'BAN',
                    'pattern' => '.*'
                ];
            }
            // CMS block tags
            elseif (strpos($tag, 'cms_b_') === 0) {
                $blockId = substr($tag, 6);
                $categorized['cms'][] = [
                    'id' => $blockId,
                    'tag' => $tag,
                    'type' => 'cms_block',
                    'purge_method' => 'BAN',
                    'pattern' => '.*'
                ];
            }
            // Store tags
            elseif (strpos($tag, 'store_') === 0) {
                $storeId = substr($tag, 6);
                $categorized['other'][] = [
                    'id' => $storeId,
                    'tag' => $tag,
                    'type' => 'store',
                    'purge_method' => 'BAN',
                    'pattern' => '.*'
                ];
            }
            // Other uncategorized tags
            else {
                $categorized['other'][] = [
                    'tag' => $tag,
                    'type' => 'unknown',
                    'purge_method' => 'BAN',
                    'pattern' => '.*'
                ];
            }
        }

        return $categorized;
    }

    /**
     * Log categorized tags
     *
     * @param array $categorizedTags
     * @param array $baseContext
     * @return void
     */
    private function logCategorizedTags(array $categorizedTags, array $baseContext): void
    {
        if (!empty($categorizedTags['category'])) {
            $this->logger->info(
                'Category cache invalidation',
                array_merge($baseContext, ['category_details' => $categorizedTags['category']])
            );
        }

        if (!empty($categorizedTags['product'])) {
            $this->logger->info(
                'Product cache invalidation',
                array_merge($baseContext, ['product_tags' => $categorizedTags['product']])
            );
        }

        if (!empty($categorizedTags['cms'])) {
            $this->logger->info(
                'CMS cache invalidation',
                array_merge($baseContext, ['cms_tags' => $categorizedTags['cms']])
            );
        }
    }
}
