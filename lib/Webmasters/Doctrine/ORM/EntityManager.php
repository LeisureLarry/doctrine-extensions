<?php

namespace Webmasters\Doctrine\ORM;

use \Doctrine\ORM\Configuration, \Doctrine\ORM\Events, \Doctrine\DBAL\DriverManager, \Doctrine\Common\EventManager;

class EntityManager extends \Doctrine\ORM\EntityManager
{
  public static function create($conn, Configuration $config, EventManager $eventManager = null)
  {
    parent::create($conn, $config, $eventManager);

    if (is_array($conn)) {
      $prefix = isset($conn['prefix']) ? $conn['prefix'] : '';

      $conn = DriverManager::getConnection(
        $conn,
        $config,
        ($eventManager ?: new EventManager())
      );

      $evm = $conn->getEventManager();

      // Table Prefix
      $tablePrefix = new Listener\TablePrefix($prefix);
      $evm->addEventListener(Events::loadClassMetadata, $tablePrefix);
    }

    return new EntityManager($conn, $config, $evm);
  }

  public function getValidator($entity)
  {
    $class = get_class($entity);
    $class_name = preg_replace('/^[A-Z][a-z]+./', '', $class);
    $validator = 'Validators\\' . $class_name . 'Validator';
    return new $validator($this, $entity);
  }
}
