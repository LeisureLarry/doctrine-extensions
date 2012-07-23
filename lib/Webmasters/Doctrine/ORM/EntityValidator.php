<?php

namespace Webmasters\Doctrine\ORM;

class EntityValidator
{
  protected $_em;
  protected $object;
  protected $errors = array();

  public function __construct($em, $object)
  {
    $this->_em = $em;
    $this->object = $object;

    $this->validateData($object->toArray(false));
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

  public function getRepository()
  {
    $class = get_class($this->object);
    return $this->_em->getRepository($class);
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
