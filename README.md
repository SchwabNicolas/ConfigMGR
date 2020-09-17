# ConfigMGR
ConfigMGR is a Configuration Manager for PHP. It is meant to remove all these old ``config.php`` files in a simple and efficient way.

## Install with Composer
You can install this library with [Composer](https://getcomposer.org).
```
composer require nschwab/configmgr 
```

## Simple example
You can find a simple example in the ``/example/`` directory.

## String interpolation
String interpolation is possible within your config file. By using curly brackets "{ }", you can specify any variable or constants defined in your config. It will look for constants defined by the system before checking for configuration keys.
### Example
```json
{
    "constants": {
      "DB_NAME": "testDb",
      "SQL_USER": "root",
      "SQL_HOST": "localhost",
      "SQL_PASSWORD": "123456",
      "CONNECTION_STRING": "Server={SQL_HOST}; Database={DB_NAME}; User Id={SQL_USER}; Password={SQL_PASSWORD}",
      "VERSION": "v1.7.3-alpha {PHP_VERSION}"
    }
}
```

## Features
- [x] Loading configuration from JSON
- [x] Creating variables from configuration
- [x] Defining constants from configuration
- [x] Format content of a configuration key with another
- [x] Format content of multiple configuration keys with another
- [x] Composer package
- [x] Search to format content with already defined constants
- [x] Custom markup
- [x] Crawl recursively through tables to format content with configuration keys
- [ ] Load tables from CSV
- [ ] Load objects from JSON
- [ ] Monolog integration
