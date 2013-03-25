<?php

namespace Webmasters\Doctrine\ORM\Util;

class OptionsCollection
{
    protected $_options;

    public function __construct($options)
    {
        $this->_options = $options;
    }

    public function all()
    {
        return $this->_options;
    }

    public function has($key)
    {
        $hasOption = false;
        if (isset($this->_options[$key])) {
            $hasOption = true;
        }

        return $hasOption;
    }

    public function set($key, $value)
    {
        $this->_options[$key] = $value;
    }

    public function get($key)
    {
        if (!$this->has($key)) {
            throw new \Exception(
                sprintf('Option "%s" missing', $key)
            );
        }

        return $this->_options[$key];
    }
}
