<?php

namespace Webmasters\Doctrine\ORM;

class EntityValidator
{
    protected $em;
    protected $entity;
    protected $errors = array();

    public function __construct($em, $entity)
    {
        $this->em = $em;
        $this->entity = $entity;

        $this->validateData();
    }

    public function validateData()
    {
        $data = Util\ArrayMapper::setEntity($this->entity)->toArray(false);

        foreach ($data as $key => $val) {
            $validate = 'validate' . ucfirst($key);
            if (method_exists($this, $validate)) {
                $this->$validate($val);
            }
        }
    }

    public function getEntityManager()
    {
        return $this->em;
    }

    public function getRepository($class = null)
    {
        if (empty($class)) {
            $class = get_class($this->entity);
        }

        return $this->getEntityManager()->getRepository($class);
    }

    public function getEntity()
    {
        return $this->entity;
    }

    public function addError($error)
    {
        $this->errors[] = $error;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function isValid()
    {
        return empty($this->errors);
    }
}
