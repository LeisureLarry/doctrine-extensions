<?php

namespace Webmasters\Doctrine\ORM;

class EntityParent
{
  public function setDaten(array $daten)
  {
    // wenn $daten nicht leer ist, rufe die passenden Setter auf
    if ($daten) {
      foreach ($daten as $k => $v) {
        $setterName = 'set' . ucfirst($k);
        // pruefe ob ein passender Setter existiert
        if (method_exists($this, $setterName)) {
          $this->$setterName($v); // Setteraufruf
        }
      }
    }
  }

  public function toArray($mitId = true)
  {
    $attributes = $this->_convert2Array($this);
    if ($mitId === false) {
      // wenn $mitId false ist, entferne den Schluessel id aus dem Ergebnis
      unset($attributes['id']);
    }
    return $attributes;
  }
  
  protected function _convert2Array($obj) {
    $ref = new \ReflectionObject($obj);
    $pros = $ref->getProperties();
	
    $result = array();
    foreach ($pros as $pro) {
	  $getterName = 'get' . ucfirst($pro->getName());
      $result[$pro->getName()] = $obj->$getterName();
    }

    return $result;
  }  
}
