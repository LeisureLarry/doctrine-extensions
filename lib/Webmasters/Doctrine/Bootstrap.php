<?php

namespace Webmasters\Doctrine;

// Kurznamen der zentralen Doctrine Namespaces anlegen
use \Doctrine\Common, \Doctrine\DBAL, \Doctrine\ORM;

// Alias der Webmasters Doctrine Extensions anlegen
use \Webmasters\Doctrine\ORM as WORM;

class Bootstrap
{
    protected static $_singletonInstance = null;

    protected $_connectionOptions = array();
    protected $_applicationOptions = array();

    protected $_cache;
    protected $_annotationReader;
    protected $_cachedAnnotationReader;
    protected $_driverChain;
    protected $_ormConfiguration;
    protected $_eventManager;
    protected $_entityManager;

    public static function getInstance($connectionOptions = array(), $applicationOptions = array())
    {
        if (self::$_singletonInstance == null) {
            self::$_singletonInstance = new Bootstrap($connectionOptions, $applicationOptions);
        }
        return self::$_singletonInstance;
    }

    protected function __construct($connectionOptions, $applicationOptions)
    {
        $this->_setConnectionOptions($connectionOptions);
        $this->_setApplicationOptions($applicationOptions);
        $this->_errorMode();
    }

    protected function __clone()
    {
    }

    protected function _setConnectionOptions($options)
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

    protected function _setApplicationOptions($options)
    {
        $vendorDir = realpath(__DIR__ . '/../../../../..');
        $baseDir = dirname($vendorDir);

        $defaultOptions = array(
            'debug_mode' => true,
            'vendor_dir' => $vendorDir,
            'base_dir' => $baseDir,
            'entity_dir' => $baseDir . '/src/Models',
            'proxy_dir' => realpath(sys_get_temp_dir()), // Ablage im Temp-Verzeichnis
            'autogenerate_proxy_classes' => true
        );

        $options = $options + $defaultOptions;
        
        if (!isset($options['entity_namespace'])) {
            $options['entity_namespace'] = basename($options['entity_dir']);
        }

        if (!isset($options['cache'])) {
            $className = '\\Doctrine\\Common\Cache\\'; // Namespace

            if (!$options['debug_mode'] && function_exists('apc_store')) {
                $className .= 'ApcCache'; // Only use APC in production environment
            } else {
                $className .= 'ArrayCache';
            }

            $options['cache'] = $className;
        }

        $this->_applicationOptions = new WORM\Util\OptionsCollection($options);
    }

    public function getApplicationOptions()
    {
        return $this->_applicationOptions;
    }

    public function setOption($key, $value)
    {
        $this->_applicationOptions->set($key, $value);
    }

    protected  function _getOption($key)
    {
        return $this->getApplicationOptions()->get($key);
    }

    public function isDebug()
    {
        return $this->_getOption('debug_mode') === true;
    }

    // *** Display Errors In Debug Mode (Default: true) ***
    protected function _errorMode()
    {
        if (!$this->isDebug()) {
            error_reporting(null);
            ini_set('display_errors', 0);
        } else {
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
            if (!ini_get('display_errors')) {
                die('Enable display_errors in php.ini!');
            }
        }
    }

    public function getCache()
    {
        if ($this->_cache === null) {
            $this->_cache = $this->_getOption('cache');
            if (!is_object($this->_cache)) {
                $this->_cache = new $this->_cache();
            }
        }

        return $this->_cache;
    }

    public function getAnnotationReader()
    {
        if ($this->_annotationReader === null) {
            $this->_annotationReader = new Common\Annotations\AnnotationReader();
        }

        return $this->_annotationReader;
    }

    public function getCachedAnnotationReader()
    {
        if ($this->_cachedAnnotationReader === null) {
            $this->_cachedAnnotationReader = new Common\Annotations\CachedReader(
                $this->getAnnotationReader(),
                $this->getCache(),
                $this->isDebug()
            );
        }

        return $this->_cachedAnnotationReader;
    }

    public function getDriverChain()
    {
        if ($this->_driverChain === null) {
            $this->_driverChain = new ORM\Mapping\Driver\DriverChain();

            $ormDir = realpath($this->_getOption('vendor_dir') . '/doctrine/orm/lib/Doctrine/ORM');

            // Sicherheitshalber die Datei fuer die Standard Doctrine Annotationen registrieren
            Common\Annotations\AnnotationRegistry::registerFile(
                $ormDir . '/Mapping/Driver/DoctrineAnnotations.php'
            );

            // Gedmo Annotationen aktivieren
            \Gedmo\DoctrineExtensions::registerAbstractMappingIntoDriverChainORM(
                $this->_driverChain,
                $this->getCachedAnnotationReader()
            );

            // Wir verwenden die neue Annotations-Syntax für die Entities
            $annotationDriver = new ORM\Mapping\Driver\AnnotationDriver(
                $this->getCachedAnnotationReader(),
                array($this->_getOption('entity_dir'))
            );

            // AnnotationDriver fuer den Entity-Namespace aktivieren
            $this->_driverChain->addDriver($annotationDriver, $this->_getOption('entity_namespace'));
        }

        return $this->_driverChain;
    }

    public function getOrmConfiguration()
    {
        if ($this->_ormConfiguration === null) {
            $this->_ormConfiguration = new ORM\Configuration();

            // Teile Doctrine mit, wie es mit Proxy-Klassen umgehen soll
            $this->_ormConfiguration->setProxyNamespace('Proxies');
            $this->_ormConfiguration->setProxyDir($this->_getOption('proxy_dir'));
            $this->_ormConfiguration->setAutoGenerateProxyClasses($this->_getOption('autogenerate_proxy_classes'));

            // Ergaenze die DriverChain in der Konfiguration
            $this->_ormConfiguration->setMetadataDriverImpl($this->getDriverChain());

            // Cache fuer Metadaten, Queries und Results benutzen
            $cache = $this->getCache();
            $this->_ormConfiguration->setMetadataCacheImpl($cache);
            $this->_ormConfiguration->setQueryCacheImpl($cache);
            $this->_ormConfiguration->setResultCacheImpl($cache);
        }

        return $this->_ormConfiguration;
    }

    public function getEventManager()
    {
        if ($this->_eventManager === null) {
            $this->_eventManager = new Common\EventManager();

            // Erweiterungen aktivieren
            $timestampableListener = new \Gedmo\Timestampable\TimestampableListener();
            $timestampableListener->setAnnotationReader($this->getCachedAnnotationReader());
            $this->_eventManager->addEventSubscriber($timestampableListener);

            // MySQL set names UTF-8
            $this->_eventManager->addEventSubscriber(new DBAL\Event\Listeners\MysqlSessionInit());
        }

        return $this->_eventManager;
    }

    public function getEm()
    {
        if ($this->_entityManager === null) {
            $this->_entityManager = WORM\EntityManager::create(
                $this->_connectionOptions,
                $this->getOrmConfiguration(),
                $this->getEventManager()
            );
        }

        return $this->_entityManager;
    }
}
