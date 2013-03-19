<?php

namespace Webmasters\Doctrine\ORM;

class EntityValidator
{
    protected $_em;
    protected $_entity;
    protected $_errors = array();

    public function __construct($em, $entity)
    {
        $this->_em = $em;
        $this->_entity = $entity;

        $data = Util\ArrayMapper::setEntity($entity)->toArray(false);
        $this->validateData($data);
    }

    public function validateData($data)
    {
        foreach ($data as $key => $val) {
            $validate = 'validate' . ucfirst($key);
            if (method_exists($this, $validate)) {
                $this->$validate($val);
            }
        }
    }

    public function getEntityManager()
    {
        return $this->_em;
    }

    public function getRepository($class = null)
    {
        if (empty($class)) {
            $class = get_class($this->_entity);
        }

        return $this->getEntityManager()->getRepository($class);
    }

    public function getEntity()
    {
        return $this->_entity;
    }

    public function addError($error)
    {
        $this->_errors[] = $error;
    }

    public function getErrors()
    {
        return $this->_errors;
    }

    public function isValid()
    {
        return empty($this->_errors);
    }
}
