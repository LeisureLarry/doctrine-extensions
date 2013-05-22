<?php

namespace Webmasters\Doctrine\ORM;

class EntityValidator
{
    protected $_em;
    protected $_entity;
    protected $_whitelist;
    protected $_blacklist;
    protected $_errors = array();

    public function __construct($em, $entity, $autostart)
    {
        $this->_em = $em;
        $this->_entity = $entity;

        if ($autostart) {
            $this->validateData();
        }
    }

    public function setWhitelist(array $whitelist)
    {
        $this->_whitelist = $whitelist;
        $this->_blacklist = null; // keine parallele Nutzung
        return $this;
    }

    public function setBlacklist(array $blacklist)
    {
        $this->_blacklist = $blacklist;
        $this->_whitelist = null; // keine parallele Nutzung
        return $this;
    }

    public function validateData()
    {
        $data = Util\ArrayMapper::setEntity($this->_entity)->toArray(false);

        // Validierungen eingrenzen
        if (!empty($this->_whitelist)) {
            $data = array_intersect_key($data, array_fill_keys($this->_whitelist, ''));
        } elseif (!empty($this->_blacklist)) {
            $data = array_diff_key($data, array_fill_keys($this->_blacklist, ''));
        }

        foreach ($data as $key => $val) {
            $validate = 'validate' . ucfirst($key);
            if (method_exists($this, $validate)) {
                $this->$validate($val);
            }
        }

        return $this;
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
