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

namespace PapiAI\Symfony\DependencyInjection;

use PapiAI\Core\Contracts\ConversationStoreInterface;
use PapiAI\Core\Contracts\ProviderInterface;
use PapiAI\Core\Storage\FileConversationStore;
use PapiAI\Symfony\Storage\DoctrineConversationStore;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

class PapiExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $this->registerProviders($config, $container);
        $this->registerConversationStore($config, $container);
    }

    private function registerProviders(array $config, ContainerBuilder $container): void
    {
        $defaultProvider = $config['default_provider'];

        foreach ($config['providers'] ?? [] as $name => $providerConfig) {
            $definition = new Definition($providerConfig['driver']);
            $definition->setArgument('$apiKey', $providerConfig['api_key']);

            if ($providerConfig['model'] !== null) {
                $definition->setArgument('$model', $providerConfig['model']);
            }

            $serviceId = 'papi.provider.' . $name;
            $container->setDefinition($serviceId, $definition);

            if ($name === $defaultProvider) {
                $container->setAlias(ProviderInterface::class, $serviceId);
                $container->setAlias('papi.provider', $serviceId);
            }
        }
    }

    private function registerConversationStore(array $config, ContainerBuilder $container): void
    {
        $storeType = $config['conversation']['store'] ?? 'file';

        if ($storeType === 'doctrine') {
            $definition = new Definition(DoctrineConversationStore::class);
            $definition->setArgument('$connection', new Reference('doctrine.dbal.default_connection'));
            $definition->setArgument('$tableName', 'papi_conversations');
        } else {
            $definition = new Definition(FileConversationStore::class);
            $definition->setArgument('$directory', $config['conversation']['path']);
        }

        $container->setDefinition(ConversationStoreInterface::class, $definition);
        $container->setAlias('papi.conversation_store', ConversationStoreInterface::class);
    }
}
