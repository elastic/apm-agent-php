<?php

class ElasticTest
{
    public function f1()
    {
        print 'It`s test file';
    }
}

//\Elastic\Apm\Impl\AutoInstrument\PhpPartFacade::$singletonInstance->interceptionManager->loadPlugins();

$testObj = new ElasticTest();
$testObj->f1();
