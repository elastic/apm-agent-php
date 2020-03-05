<?php
namespace ElasticApm;

class AutoloadedFromExtension
{
    /** @var string */
    private static $wasCalledByExtensionFlag = false;

    public static function callFromExtension()
    {
        // printf("Hi from ext-elasticapm!\n");
        syslog(LOG_INFO, "Hi from ext-elasticapm!\n");

        self::$wasCalledByExtensionFlag = true;
    }

    public static function wasCalledByExtension(): bool
    {
        return self::$wasCalledByExtensionFlag;
    }
}
