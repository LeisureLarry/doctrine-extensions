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
    $attribute = get_object_vars($this);
    if ($mitId === false) {
      // wenn $mitId false ist, entferne den Schlüssel id aus dem Ergebnis
      unset($attribute['id']);
    }
    return $attribute;
  }
}
