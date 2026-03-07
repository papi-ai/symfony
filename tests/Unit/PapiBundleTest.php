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

    it('accepts provider configuration', function () {
        $configuration = new Configuration();
        $processor = new Processor();

        $config = $processor->processConfiguration($configuration, [
            [
                'providers' => [
                    'openai' => [
                        'driver' => 'PapiAI\\OpenAI\\OpenAIProvider',
                        'api_key' => 'sk-test-key',
                        'model' => 'gpt-4o',
                    ],
                ],
            ],
        ]);

        expect($config['providers']['openai']['driver'])->toBe('PapiAI\\OpenAI\\OpenAIProvider');
        expect($config['providers']['openai']['api_key'])->toBe('sk-test-key');
        expect($config['providers']['openai']['model'])->toBe('gpt-4o');
    });

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
