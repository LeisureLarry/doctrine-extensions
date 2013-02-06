<?php

namespace Webmasters\Doctrine;

// Kurznamen der zentralen Doctrine Namespaces anlegen
use \Doctrine\Common, \Doctrine\DBAL, \Doctrine\ORM;

// Alias der Webmasters Doctrine Extensions anlegen
use \Webmasters\Doctrine\ORM as WORM;

class Bootstrap
{
  protected static $_instance = null;
  protected $_connectionOptions = array();
  protected $_applicationOptions = array();
  protected $_autoGenerateProxyClasses = false;

  public static function getInstance($connectionOptions = array(), $applicationOptions = array())
  {
    if (self::$_instance == null) {
      self::$_instance = new Bootstrap($connectionOptions, $applicationOptions);
    }
    return self::$_instance;
  }

  protected function __construct($connectionOptions, $applicationOptions)
  {
    $this->setConnectionOptions($connectionOptions);
    $this->setApplicationOptions($applicationOptions);
    $this->errorMode();
  }

  protected function __clone()
  {
  }

  public function setConnectionOptions($options)
  {
    $host = php_uname('n');

    if (isset($options[$host])) {
      $options = $options[$host];
    } elseif (isset($options['default'])) {
      $options = $options['default'];
    } else {
      die(sprintf('Connection options for "default" or "%s" missing!', $host));
    }

    $this->_connectionOptions = $options;
  }

  public function setApplicationOptions($options)
  {
    if (!isset($options['debug_mode'])) {
      $options['debug_mode'] = true;
    }

    if (!isset($options['application_path'])) {
      $options['application_path'] = preg_replace(
        '/' . preg_quote(DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR, '/') . '.*$/',
        '',
        dirname(__DIR__)
      );
    }

    if (!isset($options['proxy_path'])) {
      $this->_autoGenerateProxyClasses = true;
      $options['proxy_path'] = realpath(sys_get_temp_dir());// Ablage im Temp-Verzeichnis
    }

    if (isset($options['cache']) && !is_object($options['cache'])) {
      $options['cache'] = new $options['cache']();
    } elseif (!isset($options['cache'])) {
      $options['cache'] = null;
    }

    $this->_applicationOptions = $options;
  }

  public function getApplicationOptions()
  {
    return $this->_applicationOptions;
  }

  public function getOption($key)
  {
    $options = $this->getApplicationOptions();

    $result = null;
    if (!empty($key) && isset($options[$key])) {
      $result = $options[$key];
    }

    return $result;
  }

  public function isDebug()
  {
    return $this->getOption('debug_mode') === true;
  }

  // *** Display Errors In Debug Mode (Default: true) ***
  protected function errorMode()
  {
    if ($this->isDebug()) {
      error_reporting(E_ALL);
      ini_set('display_errors', 1);
      if (!ini_get('display_errors')) {
        die('Enable display_errors in php.ini!');
      }
    }
  }

  protected function _initCache()
  {
    if ($this->getOption('cache') == null) {
      $className = '\\Doctrine\\Common\Cache\\'; // Namespace

      if (!$this->isDebug() && function_exists('apc_store')) {
        $className .= 'ApcCache';
      } else {
        $className .= 'ArrayCache';
      }

      $cache = new $className();
    } else {
      $cache = $this->getOption('cache');
    }

    return $cache;
  }

  protected function _initAnnotationReader($cache)
  {
    $annotationReader = new Common\Annotations\AnnotationReader();

    $cachedAnnotationReader = new Common\Annotations\CachedReader(
      $annotationReader, // den Reader
      $cache, // und den Cache verwenden
      $this->isDebug()
    );

    return $cachedAnnotationReader;
  }

  protected function _initDriverChain($cachedAnnotationReader)
  {
    $driverChain = new ORM\Mapping\Driver\DriverChain();

    $path = $this->getOption('application_path');
    $ormPath = realpath($path . '/vendor/doctrine/orm/lib/Doctrine/ORM');

    // Sicherheitshalber die Datei fuer die Standard Doctrine Annotationen registrieren
    Common\Annotations\AnnotationRegistry::registerFile(
      $ormPath . '/Mapping/Driver/DoctrineAnnotations.php'
    );

    // Gedmo Annotationen aktivieren
    \Gedmo\DoctrineExtensions::registerAbstractMappingIntoDriverChainORM(
      $driverChain, // die Driver-Chain
      $cachedAnnotationReader // und den gecachten AnnotationReader nutzen
    );

    // Wir verwenden die neue Annotations-Syntax für die Entities
    $annotationDriver = new ORM\Mapping\Driver\AnnotationDriver(
      $cachedAnnotationReader, // den gecachten AnnotationReader nutzen
      array($path . '/models/Entities') // Pfad der Entities
    );

    // AnnotationDriver fuer den Entity-Namespace aktivieren
    $driverChain->addDriver($annotationDriver, 'Entities');

    return $driverChain;
  }

  protected function _initOrmConfiguration($driverChain, $cache)
  {
    $config = new ORM\Configuration();

    // Teile Doctrine mit, wie es mit Proxy-Klassen umgehen soll
    $config->setProxyDir($this->getOption('proxy_path'));
    $config->setProxyNamespace('Proxies');
    $config->setAutoGenerateProxyClasses($this->_autoGenerateProxyClasses);

    // Ergaenze die DriverChain in der Konfiguration
    $config->setMetadataDriverImpl($driverChain);

    // Cache fuer Metadaten, Queries und Results benutzen
    $config->setMetadataCacheImpl($cache);
    $config->setQueryCacheImpl($cache);
    $config->setResultCacheImpl($cache);

    return $config;
  }

  protected function _initEventManager($cachedAnnotationReader)
  {
    $evm = new Common\EventManager();

    // Erweiterungen aktivieren
    $timestampableListener = new \Gedmo\Timestampable\TimestampableListener();
    $timestampableListener->setAnnotationReader($cachedAnnotationReader);
    $evm->addEventSubscriber($timestampableListener);

    // MySQL set names UTF-8
    $evm->addEventSubscriber(new DBAL\Event\Listeners\MysqlSessionInit());

    return $evm;
  }

  public function getEm()
  {
    $cache = $this->_initCache();
    $cachedAnnotationReader = $this->_initAnnotationReader($cache);
    $driverChain = $this->_initDriverChain($cachedAnnotationReader);

    $connectionOptions = $this->_connectionOptions;
    $config = $this->_initOrmConfiguration($driverChain, $cache);
    $evm = $this->_initEventManager($cachedAnnotationReader);

    $em = WORM\EntityManager::create($connectionOptions, $config, $evm);
    return $em;
  }
}
