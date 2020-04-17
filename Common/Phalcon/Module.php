<?php
namespace Group\Common\Phalcon;

abstract class Module
{
    protected $di = null;

    public function __construct(\Phalcon\DI $di)
    {
        $this->di = $di;
        $this->registerAutoloaders();
        $this->registerServices();
    }

    abstract public function registerAutoloaders();

    abstract public function registerServices();

}
