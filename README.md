Doctrine2 extensions
==============

Bootstrap

```php
<?php

// Konfiguration der Datenbankverbindung
$connectionOptions = array(
    'default' => array(
        'driver' => 'pdo_mysql',
        'dbname' => 'example_db',
        'host' => 'localhost',
        'user' => 'root',
        'password' => '',
    ),
);

// Konfiguration der Anwendung
$applicationOptions = array(
    'debug_mode' => false,
);

// Einbindung des Autoloaders
require 'vendor/autoload.php';

$em = Webmasters\Doctrine\Bootstrap::getInstance(
    $connectionOptions,
    $applicationOptions
)->getEm();

?>
```