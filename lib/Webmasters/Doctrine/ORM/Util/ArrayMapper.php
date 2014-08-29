<?php

namespace Webmasters\Doctrine\ORM\Util;

class ArrayMapper
{
    protected static $instances = array();
    protected $entity = null;

    protected function __construct($entity)
    {
        $this->entity = $entity;
    }

    protected function __clone()
    {
    }

    public static function setEntity($entity)
    {
        $hash = spl_object_hash($entity);

        if (!isset(self::$instances[$hash])) {
            self::$instances[$hash] = new ArrayMapper($entity);
        }

        return self::$instances[$hash];
    }

    public function setData(array $data, $camelize = true)
    {
        // wenn $data nicht leer ist, rufe die passenden Setter auf
        if ($data) {
            foreach ($data as $key => $value) {
                if ($camelize) {
                    $setterName = 'set' . StringConverter::camelize($key);
                } else {
                    $setterName = 'set' . ucfirst($key);
                }

                // pruefe ob ein passender Setter existiert
                if (method_exists($this->entity, $setterName)) {
                    $this->entity->$setterName($value); // Setteraufruf
                }
            }
        }
    }

    public function toArray($mitId = true, $decamelize = true)
    {
        $data = $this->convert2Array($this->entity, $decamelize);

        if ($mitId === false) {
            // wenn $mitId false ist, entferne den Schluessel id aus dem Ergebnis
            unset($data['id']);
        }

        return $data;
    }

    protected function convert2Array($entity, $decamelize)
    {
        $reflection = new \ReflectionObject($entity);
        $props = $reflection->getProperties();

        $result = array();
        foreach ($props as $p) {
            $key = $p->getName();
            $getterName = 'get' . ucfirst($key);

            if ($decamelize) {
                $key = StringConverter::decamelize($key);
            }

            if (method_exists($this->entity, $getterName)) {
                $result[$key] = $entity->$getterName();
            }
        }

        return $result;
    }
}
