<?php

namespace ContainerMsGnnVf;

use Symfony\Component\DependencyInjection\Argument\RewindableGenerator;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

/**
 * @internal This class has been auto-generated by the Symfony Dependency Injection Component.
 */
class get_ServiceLocator_Bnq4aHZService extends App_KernelDevDebugContainer
{
    /**
     * Gets the private '.service_locator.Bnq4aHZ' shared service.
     *
     * @return \Symfony\Component\DependencyInjection\ServiceLocator
     */
    public static function do($container, $lazyLoad = true)
    {
        return $container->privates['.service_locator.Bnq4aHZ'] = new \Symfony\Component\DependencyInjection\Argument\ServiceLocator($container->getService, [
            'entityManager' => ['services', 'doctrine.orm.default_entity_manager', 'getDoctrine_Orm_DefaultEntityManagerService', false],
            'post' => ['privates', '.errored..service_locator.Bnq4aHZ.App\\Entity\\Post', NULL, 'Cannot autowire service ".service_locator.Bnq4aHZ": it references class "App\\Entity\\Post" but no such service exists.'],
        ], [
            'entityManager' => '?',
            'post' => 'App\\Entity\\Post',
        ]);
    }
}
