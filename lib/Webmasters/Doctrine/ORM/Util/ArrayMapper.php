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

    public function setData(array $data)
    {
        // wenn $data nicht leer ist, rufe die passenden Setter auf
        if ($data) {
            foreach ($data as $k => $v) {
                $setterName = 'set' . ucfirst($k);
                // pruefe ob ein passender Setter existiert
                if (method_exists($this->entity, $setterName)) {
                    $this->entity->$setterName($v); // Setteraufruf
                }
            }
        }
    }

    public function toArray($mitId = true)
    {
        $data = $this->convert2Array($this->entity);

        if ($mitId === false) {
            // wenn $mitId false ist, entferne den Schluessel id aus dem Ergebnis
            unset($data['id']);
        }

        return $data;
    }

    protected function convert2Array($entity)
    {
        $reflection = new \ReflectionObject($entity);
        $props = $reflection->getProperties();

        $result = array();
        foreach ($props as $p) {
            $getterName = 'get' . ucfirst($p->getName());
            if (method_exists($this->entity, $getterName)) {
                $result[$p->getName()] = $entity->$getterName();
            }
        }

        return $result;
    }
}
