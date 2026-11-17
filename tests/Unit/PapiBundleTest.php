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

use PapiAI\Symfony\DependencyInjection\Configuration;
use PapiAI\Symfony\DependencyInjection\PapiExtension;
use PapiAI\Symfony\PapiBundle;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

describe('PapiBundle', function () {
    it('can be instantiated', function () {
        $bundle = new PapiBundle();

        expect($bundle)->toBeInstanceOf(PapiBundle::class);
    });

    it('returns PapiExtension as container extension', function () {
        $bundle = new PapiBundle();
        $extension = $bundle->getContainerExtension();

        expect($extension)->toBeInstanceOf(PapiExtension::class);
    });
});

describe('Configuration', function () {
    it('builds configuration tree with defaults', function () {
        $configuration = new Configuration();
        $processor = new Processor();

        $config = $processor->processConfiguration($configuration, []);

        expect($config['default_provider'])->toBe('openai');
        expect($config['providers'])->toBe([]);
        expect($config['middleware'])->toBe([]);
        expect($config['conversation']['store'])->toBe('file');
    });

    it('passes each provider entry through as its settings', function () {
        $config = (new Processor())->processConfiguration(new Configuration(), [[
            'default_provider' => 'ollama',
            'providers' => [
                'ollama' => ['base_url' => 'http://ollama:11434', 'max_tokens' => 2048, 'effort' => 'low'],
                'anthropic' => ['driver' => 'PapiAI\\Anthropic\\AnthropicProvider', 'api_key' => 'sk', 'replay_thinking' => 'drop_on_edit'],
            ],
        ]]);

        expect($config['providers']['ollama'])->toBe(['base_url' => 'http://ollama:11434', 'max_tokens' => 2048, 'effort' => 'low']);
        expect($config['providers']['anthropic'])->toBe(['driver' => 'PapiAI\\Anthropic\\AnthropicProvider', 'api_key' => 'sk', 'replay_thinking' => 'drop_on_edit']);
    });

    it('defaults to openai when it is configured, otherwise to the first entry', function () {
        $configured = static fn (array $providers): string => (new Processor())->processConfiguration(new Configuration(), [['providers' => $providers]])['default_provider'];

        expect($configured(['anthropic' => ['api_key' => 'a'], 'openai' => ['api_key' => 'o']]))->toBe('openai');
        expect($configured(['google' => ['api_key' => 'g'], 'anthropic' => ['api_key' => 'a']]))->toBe('google');
    });

    it('refuses a default provider that has no entry', function () {
        (new Processor())->processConfiguration(new Configuration(), [[
            'default_provider' => 'google',
            'providers' => ['anthropic' => ['api_key' => 'sk']],
        ]]);
    })->throws(InvalidConfigurationException::class, 'The default provider "google" has no entry under papi.providers. Configured: "anthropic".');

    it('accepts conversation store configuration', function () {
        $configuration = new Configuration();
        $processor = new Processor();

        $config = $processor->processConfiguration($configuration, [
            [
                'conversation' => [
                    'store' => 'doctrine',
                    'path' => '/tmp/conversations',
                ],
            ],
        ]);

        expect($config['conversation']['store'])->toBe('doctrine');
        expect($config['conversation']['path'])->toBe('/tmp/conversations');
    });

    it('accepts middleware configuration', function () {
        $configuration = new Configuration();
        $processor = new Processor();

        $config = $processor->processConfiguration($configuration, [
            [
                'middleware' => [
                    'app.middleware.logging',
                    'app.middleware.rate_limit',
                ],
            ],
        ]);

        expect($config['middleware'])->toBe([
            'app.middleware.logging',
            'app.middleware.rate_limit',
        ]);
    });
});
