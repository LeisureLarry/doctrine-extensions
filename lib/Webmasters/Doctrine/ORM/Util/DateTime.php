<?php

namespace Webmasters\Doctrine\ORM\Util;

class DateTime
{
  protected $_raw = null;
  protected $_datetime = null;
  
  public function __construct($value)
  {
    if (is_string($value)) {
      $this->_raw = $value;
      $this->_convert2Object();
    } elseif ($value instanceof \DateTime) {
      $this->_datetime = $value;
    } elseif ($value instanceof DateTime) {
      $this->_raw = $value->getRaw();
      $this->_datetime = $value->getDateTime();
    }
  }
  
  protected function _convert2Object()
  {
    if ($this->_isValidDate($this->_raw)) {
      $this->_datetime = new \DateTime($this->_raw);
    }
  }
  
  protected function _isValidDate($str)
  {
    $stamp = strtotime($str);
    
    if ($stamp === false) {
      return false; 
    } elseif (checkdate(date('m', $stamp), date('d', $stamp), date('Y', $stamp))) { 
      return true; 
    }
    
    return false; 
  }

  public function getRaw()
  {
    return $this->_raw;
  }

  public function getDateTime()
  {
    return $this->_datetime;
  }
  
  public function format($format)
  {
    $result = $this->_raw;
    if ($this->isValid()) {
      $result = $this->_datetime->format($format);
    }
    
    return $result;
  }

  public function modify($modification)
  {
    if ($this->isValid()) {
      $this->_datetime->modify($modification);
    }
  }

  public function diff($datetime)
  {
    $result = false;
    if ($this->isValid() && $datetime->isValid()) {
      $result = $this->_datetime->diff($datetime->getDateTime());
    }

    return $result;
  }

  public function isValid()
  {
    return (!empty($this->_datetime) && ($this->_datetime instanceof \DateTime));
  }

  public function isValidClosingDate($datetime)
  {
    $diff = $this->diff($datetime);

    $result = false;
    if ($diff !== false) {
      $days = intval($diff->format('%r%a'));
      if ($days >= 0) {
        $result = true;
      }
    }

    return $result;
  }
}
