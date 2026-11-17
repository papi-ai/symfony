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

use InvalidArgumentException;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * The `papi` configuration: providers (each entry is the settings its factory takes), the agent,
 * its middleware, conversation storage and where queued job statuses are kept.
 */
class Configuration implements ConfigurationInterface
{
    /**
     * @return TreeBuilder<'array'>
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('papi');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->validate()
                ->always(static function (array $config): array {
                    $config['default_provider'] ??= isset($config['providers']['openai']) ? 'openai' : (array_key_first($config['providers']) ?? 'openai');

                    return $config;
                })
            ->end()
            ->validate()
                ->ifTrue(static fn (array $config): bool => $config['providers'] !== [] && !isset($config['providers'][$config['default_provider']]))
                ->then(static function (array $config): never {
                    throw new InvalidArgumentException(sprintf(
                        'The default provider "%s" has no entry under papi.providers. Configured: %s.',
                        $config['default_provider'],
                        implode(', ', array_map(static fn (string $name): string => '"' . $name . '"', array_keys($config['providers']))),
                    ));
                })
            ->end()
            ->children()
                ->scalarNode('default_provider')
                    ->defaultNull()
                    ->info('The entry under "providers" that the agent and ProviderInterface use; openai when configured, otherwise the first')
                ->end()
                ->arrayNode('providers')
                    ->info('Settings by provider name, as each provider\'s factory takes them (api_key, model, effort, ...)')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->ignoreExtraKeys(false)
                        ->children()
                            ->scalarNode('driver')
                                ->info('A provider factory class, for an entry named otherwise or a provider of your own')
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('agent')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('instructions')->defaultNull()->end()
                        ->integerNode('max_tokens')->defaultNull()->end()
                    ->end()
                ->end()
                ->arrayNode('middleware')
                    ->scalarPrototype()->end()
                    ->info('Middleware service IDs; a class that is not a service is registered with autowiring')
                ->end()
                ->arrayNode('conversation')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('store')
                            ->defaultValue('file')
                            ->info('Conversation store type: "file" or "doctrine"')
                        ->end()
                        ->scalarNode('path')
                            ->defaultValue('%kernel.project_dir%/var/papi/conversations')
                            ->info('Path for file-based conversation store')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('queue')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('status_pool')
                            ->defaultValue('cache.app')
                            ->info('Cache pool that keeps queued job statuses; share it between web and workers')
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
