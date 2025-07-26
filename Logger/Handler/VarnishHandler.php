<?php
namespace Kamlesh\VarnishLog\Logger\Handler;

use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\App\Filesystem\DirectoryList;

/**
 * Class VarnishHandler
 */
class VarnishHandler extends StreamHandler
{
    /**
     * @var TimezoneInterface
     */
    private $timezone;

    /**
     * @param TimezoneInterface $timezone
     * @param DirectoryList $directoryList
     * @param File $filesystem
     * @param string $fileName
     * @param int $level
     */
    public function __construct(
        TimezoneInterface $timezone,
        DirectoryList $directoryList,
        File $filesystem,
        $fileName = 'varnish_purge.log',
        $level = Logger::DEBUG
    ) {
        $this->timezone = $timezone;
        $logPath = $directoryList->getPath(DirectoryList::VAR_DIR) . 
            DIRECTORY_SEPARATOR . 'log' . 
            DIRECTORY_SEPARATOR . $fileName;
        
        // Ensure directory exists
        $logDir = dirname($logPath);
        $filesystem->createDirectory($logDir);
        
        parent::__construct($logPath, $level);

    /**
     * Format log message with additional context
     *
     * @param array $record
     * @return void
     */
    protected function write(array $record): void
    {
        $record['formatted'] = sprintf(
            "[%s] %s %s %s\n",
            $this->timezone->date()->format('Y-m-d H:i:s'),
            $record['level_name'],
            $record['message'],
            !empty($record['context']) ? json_encode($record['context']) : ''
        );

        parent::write($record);
    }
}
