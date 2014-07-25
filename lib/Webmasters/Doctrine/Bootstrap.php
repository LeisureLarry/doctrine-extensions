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

    protected $_annotationReader;
    protected $_cache;
    protected $_cachedAnnotationReader;
    protected $_driverChain;
    protected $_listeners;
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
        $vendorDir = realpath(__DIR__ . '/../../../../..');
        $baseDir = dirname($vendorDir);
        $host = php_uname('n');

        if (empty($connectionOptions)) {
            $path = $baseDir . '/config/';
            if (file_exists($path . $host . '-config.php')) {
                require_once $path . $host . '-config.php';
            } elseif (file_exists($path . 'default-config.php')) {
                require_once $path . 'default-config.php';
            } else {
                die(sprintf('"config/default-config.php" or "config/%s-config.php" missing!', $host));
            }
        }

        $defaultOptions = array(
            'autogenerate_proxy_classes' => true,
            'debug_mode' => true,
            'base_dir' => $baseDir,
            'entity_dir' => $baseDir . '/src/Models',
            'proxy_dir' => realpath(sys_get_temp_dir()), // Ablage im Temp-Verzeichnis
            'vendor_dir' => $vendorDir,
            'gedmo_ext' => array('Timestampable'),
        );

        $this->_setConnectionOptions($connectionOptions);
        $this->_setApplicationOptions($applicationOptions + $defaultOptions);
        $this->_errorMode();
    }

    protected function __clone()
    {
    }

    protected function _setConnectionOptions($options)
    {
        $this->_connectionOptions = $options;
    }

    protected function _setApplicationOptions($options)
    {
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

    protected function _getOption($key)
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
            ini_set('display_errors', 0); // nur bei nicht fatalen Fehlern
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

            // Gedmo Annotationen aktivieren sofern Paket installiert
            if ($this->_getOption('gedmo_ext')) {
                if (class_exists('\\Gedmo\\DoctrineExtensions')) {
                    \Gedmo\DoctrineExtensions::registerAbstractMappingIntoDriverChainORM(
                        $this->_driverChain,
                        $this->getCachedAnnotationReader()
                    );
                } else {
                    die('"gedmo/doctrine-extensions" missing!');
                }
            }

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

    private function _initGedmoListeners()
    {
        if ($this->_getOption('gedmo_ext') && is_array($this->_getOption('gedmo_ext'))) {
            $this->_listeners['Gedmo'] = array();
            foreach ($this->_getOption('gedmo_ext') as $name) {
                if (is_string($name)) {
                    $listenerClass = '\\Gedmo\\' . $name . '\\' . $name . 'Listener';
                    $listener = new $listenerClass();
                    $this->_listeners['Gedmo'][$name] = $listener;
                }

                $listener->setAnnotationReader($this->getCachedAnnotationReader());
                $this->_eventManager->addEventSubscriber($listener);
            }
        }
    }

    public function getListener($ext, $name)
    {
        if (!isset($this->_listeners[$ext][$name])) {
            throw new \Exception(
                sprintf('Listener "%s\%s" missing', $ext, $name)
            );
        }

        return $this->_listeners[$ext][$name];
    }

    public function getEventManager()
    {
        if ($this->_eventManager === null) {
            $this->_eventManager = new Common\EventManager();

            // Erweiterungen aktivieren
            $this->_initGedmoListeners();

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
