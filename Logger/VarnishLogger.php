<?php
declare(strict_types=1);

namespace Kamlesh\VarnishLog\Logger;

use Kamlesh\VarnishLog\Logger\Handler\VarnishHandler;
use Monolog\Logger;

class VarnishLogger extends Logger
{
    public function __construct(
        VarnishHandler $handler
    ) {
        parent::__construct('varnish_purge');
        $this->pushHandler($handler);
    }
}