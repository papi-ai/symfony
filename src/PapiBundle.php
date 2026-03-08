<?php

/*
 * This file is part of PapiAI,
 * A simple but powerful PHP library for building AI agents.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PapiAI\Symfony;

use PapiAI\Symfony\DependencyInjection\PapiExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Symfony bundle that integrates PapiAI into Symfony applications.
 *
 * Registers AI provider services, conversation storage, and middleware
 * through Symfony's dependency injection container. Activate this bundle
 * to use PapiAI agents within a Symfony project.
 */
class PapiBundle extends AbstractBundle
{
    /**
     * Load bundle-specific service definitions from the YAML configuration.
     *
     * @param array<string, mixed>  $config    Processed bundle configuration values
     * @param ContainerConfigurator $container  Service configurator for importing definitions
     * @param ContainerBuilder      $builder    The container builder instance
     *
     * @return void
     */
    public function loadExtension(
        array $config,
        ContainerConfigurator $container,
        ContainerBuilder $builder,
    ): void {
        $container->import('../config/services.yaml');
    }

    /**
     * Return the custom container extension that processes papi configuration.
     *
     * @return ExtensionInterface|null The PapiExtension instance
     */
    public function getContainerExtension(): ?ExtensionInterface
    {
        return new PapiExtension();
    }
}
