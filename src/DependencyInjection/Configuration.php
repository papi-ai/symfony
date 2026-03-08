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

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Defines the configuration tree for the `papi` bundle.
 *
 * Exposes settings for AI providers (driver class, API key, model),
 * middleware pipeline, and conversation storage (file-based or Doctrine).
 */
class Configuration implements ConfigurationInterface
{
    /**
     * Build and return the configuration tree for the papi bundle.
     *
     * Defines nodes for default_provider, providers map, middleware list,
     * and conversation store settings (type and file path).
     *
     * @return TreeBuilder The fully configured tree builder
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('papi');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('default_provider')
                    ->defaultValue('openai')
                ->end()
                ->arrayNode('providers')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('driver')
                                ->isRequired()
                                ->info('The provider driver class (e.g. PapiAI\\OpenAI\\OpenAIProvider)')
                            ->end()
                            ->scalarNode('api_key')
                                ->isRequired()
                            ->end()
                            ->scalarNode('model')
                                ->defaultNull()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('middleware')
                    ->scalarPrototype()->end()
                    ->info('List of middleware service IDs')
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
            ->end();

        return $treeBuilder;
    }
}
