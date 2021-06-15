<?php

/*
 * Isotope eCommerce for Contao Open Source CMS
 *
 * Copyright (C) 2009 - 2019 terminal42 gmbh & Isotope eCommerce Workgroup
 *
 * @link       https://isotopeecommerce.org
 * @license    https://opensource.org/licenses/lgpl-3.0.html
 */

namespace Isotope\ContaoManager;

use Contao\ManagerPlugin\Config\ConfigPluginInterface;
use Isotope\DependencyInjection\Compiler\RegisterHookListenersPass;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class Plugin implements ConfigPluginInterface
{
    /**
     * {@inheritdoc}
     */
    public function registerContainerConfiguration(LoaderInterface $loader, array $managerConfig)
    {
        $loader->load(__DIR__.'/../../../config/services.yaml');

        $loader->load(function (ContainerBuilder $container) {
            $container->addCompilerPass(new RegisterHookListenersPass());
        });
    }
}
