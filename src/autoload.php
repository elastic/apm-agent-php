<?php
namespace ElasticApm;

class ElasticApm
{
    public static function test()
    {
        // printf("Hi from ext-elasticapm!\n");
        syslog(LOG_INFO, "Hi from ext-elasticapm!\n");
    }
}
