<?php

namespace Webmasters\Doctrine\ORM\Util;

class ArrayMapper
{
  protected static $_instances = array();
  protected $_entity = null;

  public static function setEntity($entity)
  {
    $oid = spl_object_hash($entity);
    if (!isset(self::$_instances[$oid])) {
      self::$_instances[$oid] = new ArrayMapper($entity);
    }
    return self::$_instances[$oid];
  }
  
  protected function __construct($entity)
  {
    $this->_entity = $entity;
  }

  protected function __clone()
  {
  }
  
  public function setData(array $data)
  {
    // wenn $data nicht leer ist, rufe die passenden Setter auf
    if ($data) {
      foreach ($data as $k => $v) {
        $setterName = 'set' . ucfirst($k);
        // pruefe ob ein passender Setter existiert
        if (method_exists($this->_entity, $setterName)) {
          $this->_entity->$setterName($v); // Setteraufruf
        }
      }
    }
  }
  
  public function toArray($mitId = true)
  {
    $data = $this->_convert2Array($this->_entity);
    if ($mitId === false) {
      // wenn $mitId false ist, entferne den Schluessel id aus dem Ergebnis
      unset($data['id']);
    }
    return $data;
  }
  
  protected function _convert2Array($entity)
  {
    $reflection = new \ReflectionObject($entity);
    $props = $reflection->getProperties();
    
    $result = array();
    foreach ($props as $p) {
      $getterName = 'get' . ucfirst($p->getName());
      $result[$p->getName()] = $entity->$getterName();
    }
    
    return $result;
  }
}
