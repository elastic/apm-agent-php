<?php

class Test {
    public function f()
    {
        print 'It`s test fiele';die();
        //return 'f';
    }
}

//\Elastic\Apm\Impl\AutoInstrument\PhpPartFacade::$singletonInstance->interceptionManager->loadPlugins();

$testObj = new Test();
$testObj->f();
