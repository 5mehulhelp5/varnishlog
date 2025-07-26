# VarnishLog Magento 2 Module

[![Packagist](https://img.shields.io/packagist/v/barkamlesh/varnishlog.svg)](https://packagist.org/packages/barkamlesh/varnishlog)
[![MIT License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

## Description
Magento 2 module for Varnish logging and cache invalidation. This module logs cache invalidation events for categories and products, helping you monitor Varnish cache operations.

## Installation

### Composer
```bash
composer require barkamlesh/varnishlog
```

### Enable the module
```bash
php bin/magento module:enable Defsys_VarnishLog
php bin/magento setup:upgrade
```

## Usage
- Logs cache invalidation events for categories and products.
- Integrates with Magento's event system.

## File Structure
```
VarnishLog/
  composer.json
  registration.php
  etc/
    module.xml
    events.xml
  Logger/
    VarnishLogger.php
  Observer/
    CacheInvalidation.php
```

## Support
- Issues: https://github.com/barkamlesh/varnishlog/issues
- Source: https://github.com/barkamlesh/varnishlog

## License
MIT
