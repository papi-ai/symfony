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

use PapiAI\Core\Contracts\ConversationStoreInterface;
use PapiAI\Core\Contracts\ProviderInterface;
use PapiAI\Core\Storage\FileConversationStore;
use PapiAI\Symfony\DependencyInjection\PapiExtension;
use PapiAI\Symfony\Storage\DoctrineConversationStore;
use Symfony\Component\DependencyInjection\ContainerBuilder;

describe('PapiExtension', function () {
    it('can be instantiated', function () {
        $extension = new PapiExtension();

        expect($extension)->toBeInstanceOf(PapiExtension::class);
    });

    it('has the correct alias', function () {
        $extension = new PapiExtension();

        expect($extension->getAlias())->toBe('papi');
    });

    it('registers file conversation store by default', function () {
        $extension = new PapiExtension();
        $container = new ContainerBuilder();

        $extension->load([
            [
                'providers' => [],
            ],
        ], $container);

        expect($container->hasDefinition(ConversationStoreInterface::class))->toBeTrue();

        $definition = $container->getDefinition(ConversationStoreInterface::class);
        expect($definition->getClass())->toBe(FileConversationStore::class);
    });

    it('registers doctrine conversation store when configured', function () {
        $extension = new PapiExtension();
        $container = new ContainerBuilder();

        $extension->load([
            [
                'providers' => [],
                'conversation' => [
                    'store' => 'doctrine',
                ],
            ],
        ], $container);

        expect($container->hasDefinition(ConversationStoreInterface::class))->toBeTrue();

        $definition = $container->getDefinition(ConversationStoreInterface::class);
        expect($definition->getClass())->toBe(DoctrineConversationStore::class);
    });

    it('registers provider services', function () {
        $extension = new PapiExtension();
        $container = new ContainerBuilder();

        $extension->load([
            [
                'providers' => [
                    'openai' => [
                        'driver' => 'PapiAI\\OpenAI\\OpenAIProvider',
                        'api_key' => 'sk-test',
                        'model' => 'gpt-4o',
                    ],
                ],
            ],
        ], $container);

        expect($container->hasDefinition('papi.provider.openai'))->toBeTrue();

        $definition = $container->getDefinition('papi.provider.openai');
        expect($definition->getClass())->toBe('PapiAI\\OpenAI\\OpenAIProvider');
        expect($definition->getArgument('$apiKey'))->toBe('sk-test');
        expect($definition->getArgument('$defaultModel'))->toBe('gpt-4o');
    });

    it('registers default provider alias', function () {
        $extension = new PapiExtension();
        $container = new ContainerBuilder();

        $extension->load([
            [
                'default_provider' => 'openai',
                'providers' => [
                    'openai' => [
                        'driver' => 'PapiAI\\OpenAI\\OpenAIProvider',
                        'api_key' => 'sk-test',
                        'model' => null,
                    ],
                ],
            ],
        ], $container);

        expect($container->hasAlias(ProviderInterface::class))->toBeTrue();
        expect($container->hasAlias('papi.provider'))->toBeTrue();
    });

    it('does not set model argument when model is null', function () {
        $extension = new PapiExtension();
        $container = new ContainerBuilder();

        $extension->load([
            [
                'providers' => [
                    'anthropic' => [
                        'driver' => 'PapiAI\\Anthropic\\AnthropicProvider',
                        'api_key' => 'sk-test',
                        'model' => null,
                    ],
                ],
            ],
        ], $container);

        $definition = $container->getDefinition('papi.provider.anthropic');

        expect(fn () => $definition->getArgument('$defaultModel'))
            ->toThrow(\OutOfBoundsException::class);
    });

    it('registers multiple providers', function () {
        $extension = new PapiExtension();
        $container = new ContainerBuilder();

        $extension->load([
            [
                'default_provider' => 'openai',
                'providers' => [
                    'openai' => [
                        'driver' => 'PapiAI\\OpenAI\\OpenAIProvider',
                        'api_key' => 'sk-openai',
                        'model' => 'gpt-4o',
                    ],
                    'anthropic' => [
                        'driver' => 'PapiAI\\Anthropic\\AnthropicProvider',
                        'api_key' => 'sk-anthropic',
                        'model' => 'claude-sonnet-4-20250514',
                    ],
                ],
            ],
        ], $container);

        expect($container->hasDefinition('papi.provider.openai'))->toBeTrue();
        expect($container->hasDefinition('papi.provider.anthropic'))->toBeTrue();

        // Only openai should be aliased as default
        $alias = $container->getAlias(ProviderInterface::class);
        expect((string) $alias)->toBe('papi.provider.openai');
    });

    it('registers conversation store alias', function () {
        $extension = new PapiExtension();
        $container = new ContainerBuilder();

        $extension->load([
            [
                'providers' => [],
            ],
        ], $container);

        expect($container->hasAlias('papi.conversation_store'))->toBeTrue();
    });

    it('loads services yaml configuration', function () {
        $extension = new PapiExtension();
        $container = new ContainerBuilder();

        $extension->load([
            [
                'providers' => [],
            ],
        ], $container);

        // The services.yaml defines MessengerQueue and DoctrineConversationStore
        expect($container->hasDefinition('PapiAI\\Symfony\\Queue\\MessengerQueue'))->toBeTrue();
        expect($container->hasDefinition('PapiAI\\Symfony\\Storage\\DoctrineConversationStore'))->toBeTrue();
    });
});
