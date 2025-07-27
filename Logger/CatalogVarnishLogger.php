<?php
declare(strict_types=1);

namespace Kamlesh\VarnishLog\Logger;

use Kamlesh\VarnishLog\Logger\Handler\CatalogVarnishHandler;
use Monolog\Logger;

class CatalogVarnishLogger extends Logger
{
    public function __construct(
        CatalogVarnishHandler $handler
    ) {
        parent::__construct('catalog_varnish_purge');
        $this->pushHandler($handler);
    }
}
