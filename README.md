# doctrine-extensions

## Webmasters Doctrine Extensions

Just Another Doctrine2 Extension

### Bootstrap

```php
<?php

// MySQL database configuration
$connectionOptions = [
    'default' => [
        'driver' => 'pdo_mysql',
        'dbname' => 'example_db',
        'host' => 'localhost',
        'user' => 'root',
        'password' => '',
        'prefix' => '',
    ],
];

// Application/Doctrine configuration
$applicationOptions = [
    'debug_mode' => true, // in production environment false
];

// Use Composer autoloading
require_once 'vendor/autoload.php';

// Get Doctrine entity manager
$bootstrap = Webmasters\Doctrine\Bootstrap::getInstance(
    $connectionOptions,
    $applicationOptions
);

$em = $bootstrap->getEm();

```

### Idea
[Jan Teriete](https://plus.google.com/106660436858103395374?rel=author)