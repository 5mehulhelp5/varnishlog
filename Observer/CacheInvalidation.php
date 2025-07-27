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
        try {
            $event = $observer->getEvent();
            if (!$event) {
                $this->logger->error('No event data available in observer');
                return;
            }

            $tags = $event->getTags();
            if (!is_array($tags) || empty($tags)) {
                $this->logger->debug('No cache tags to process');
                return;
            }

            // Get user information safely
            $user = $this->getUserInfo();
            
            // Basic context for all log entries
            $context = $this->prepareBaseContext($tags, $user);
            
            // Log raw tags for debugging
            $allTags = implode(', ', $tags);
            $this->logger->info('Cache purge initiated', $context);
            
            // Process and categorize tags
            $categorizedTags = $this->categorizeTags($tags);
            
            // Log each category of tags
            $this->logCategorizedTags($categorizedTags, $context);

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
        $categorized = [
            'category' => [],
            'product' => [],
            'cms' => []
        ];

        foreach ($tags as $tag) {
            if (!is_string($tag)) {
                continue;
            }

            if (strpos($tag, 'cat_c_') === 0) {
                $categoryId = substr($tag, 6);
                $categorized['category'][] = [
                    'id' => $categoryId,
                    'tag' => $tag,
                    'type' => 'category'
                ];
            } elseif (strpos($tag, 'cat_p_') === 0) {
                $categoryId = substr($tag, 6);
                $categorized['category'][] = [
                    'id' => $categoryId,
                    'tag' => $tag,
                    'type' => 'category_products'
                ];
            } elseif (strpos($tag, 'cat_p') === 0) {
                $categorized['product'][] = $tag;
            } elseif (strpos($tag, 'cms_') === 0) {
                $categorized['cms'][] = $tag;
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