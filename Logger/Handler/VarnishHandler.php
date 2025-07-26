<?php
namespace Kamlesh\VarnishLog\Logger\Handler;

use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class VarnishHandler extends StreamHandler
{
    /**
     * @var TimezoneInterface
     */
    private $timezone;

    /**
     * @param TimezoneInterface $timezone
     * @param string $file
     * @param int $level
     */
    public function __construct(
        TimezoneInterface $timezone,
        $file = 'var/log/varnish_purge.log',
        $level = Logger::DEBUG
    ) {
        $this->timezone = $timezone;
        parent::__construct($file, $level);
    }

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
