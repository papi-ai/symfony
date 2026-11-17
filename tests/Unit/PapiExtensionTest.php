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

use PapiAI\Core\Agent;
use PapiAI\Core\Contracts\AgentInterface;
use PapiAI\Core\Contracts\ConversationStoreInterface;
use PapiAI\Core\Contracts\ProviderInterface;
use PapiAI\Core\Contracts\QueueInterface;
use PapiAI\Core\Exception\ConfigurationException;
use PapiAI\Core\Role;
use PapiAI\Core\Storage\FileConversationStore;
use PapiAI\Symfony\DependencyInjection\PapiExtension;
use PapiAI\Symfony\Storage\DoctrineConversationStore;
use PapiAI\Symfony\Tests\Fixtures\RecordingMiddleware;
use PapiAI\Symfony\Tests\Fixtures\TestProvider;
use PapiAI\Symfony\Tests\Fixtures\TestProviderFactory;
use Symfony\Component\DependencyInjection\ContainerBuilder;

describe('PapiExtension', function () {
    it('has the correct alias', function () {
        expect((new PapiExtension())->getAlias())->toBe('papi');
    });

    it('builds each provider through its installed factory, with the settings it was given', function () {
        $container = compiledContainer([
            'default_provider' => 'test',
            'providers' => ['test' => ['api_key' => 'k', 'model' => 'm']],
        ]);

        expect($container->get('papi.provider.test')->settings)->toBe(['api_key' => 'k', 'model' => 'm']);
        expect($container->get(ProviderInterface::class))->toBe($container->get('papi.provider.test'));
        expect($container->get('papi.provider'))->toBe($container->get('papi.provider.test'));
    });

    it('resolves environment variables in the settings when the provider is built', function () {
        $_SERVER['PAPI_TEST_KEY'] = 'from-the-environment';

        $container = compiledContainer([
            'default_provider' => 'test',
            'providers' => ['test' => ['api_key' => '%env(PAPI_TEST_KEY)%']],
        ]);

        expect($container->get('papi.provider.test')->settings)->toBe(['api_key' => 'from-the-environment']);

        unset($_SERVER['PAPI_TEST_KEY']);
    });

    it('builds an entry of its own name through the driver it names', function () {
        $container = compiledContainer([
            'default_provider' => 'fast',
            'providers' => [
                'fast' => ['driver' => TestProviderFactory::class, 'api_key' => 'k', 'model' => 'small'],
                'smart' => ['driver' => TestProviderFactory::class, 'api_key' => 'k', 'model' => 'large'],
            ],
        ]);

        expect($container->get('papi.provider.fast')->settings['model'])->toBe('small');
        expect($container->get('papi.provider.smart')->settings['model'])->toBe('large');
    });

    it('reads a provider class given as the driver as the factory beside it', function () {
        $container = compiledContainer([
            'default_provider' => 'fast',
            'providers' => ['fast' => ['driver' => TestProvider::class, 'api_key' => 'k']],
        ]);

        expect($container->get('papi.provider.fast')->settings)->toBe(['api_key' => 'k']);
    });

    it('refuses a provider that is not installed', function () {
        compiledContainer([
            'default_provider' => 'gogle',
            'providers' => ['gogle' => ['api_key' => 'k']],
        ]);
    })->throws(ConfigurationException::class, 'Provider "gogle" is not installed. Installed: "test".');

    it('refuses a driver that is not a provider factory', function () {
        compiledContainer([
            'default_provider' => 'test',
            'providers' => ['test' => ['driver' => stdClass::class, 'api_key' => 'k']],
        ]);
    })->throws(ConfigurationException::class, 'Provider "test" names the driver "stdClass", which is not a provider factory and has no "stdClassFactory" beside it.');

    it('compiles without Doctrine or Messenger', function () {
        $container = compiledContainer([
            'default_provider' => 'test',
            'providers' => ['test' => ['api_key' => 'k']],
        ]);

        expect($container->get(ConversationStoreInterface::class))->toBeInstanceOf(FileConversationStore::class);
        expect($container->has(QueueInterface::class))->toBeFalse();
    });

    it('stores conversations with Doctrine when asked', function () {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());

        (new PapiExtension())->load([['conversation' => ['store' => 'doctrine']]], $container);

        expect($container->getDefinition(ConversationStoreInterface::class)->getClass())->toBe(DoctrineConversationStore::class);
        expect($container->hasAlias('papi.conversation_store'))->toBeTrue();
    });

    it('gives the agent the default provider, its instructions, output ceiling and middleware', function () {
        $container = compiledContainer([
            'default_provider' => 'test',
            'providers' => ['test' => ['api_key' => 'k']],
            'agent' => ['instructions' => 'Answer in one word.', 'max_tokens' => 512],
            'middleware' => [RecordingMiddleware::class],
        ]);

        $container->get(AgentInterface::class)->run('Capital of Portugal?');

        $provider = $container->get('papi.provider.test');
        expect($provider->lastOptions['maxTokens'])->toBe(512);
        expect($provider->lastOptions)->not->toHaveKey('model');
        expect($provider->lastMessages[0]->role)->toBe(Role::System);
        expect($provider->lastMessages[0]->content)->toBe('Answer in one word.');
        expect($container->get(RecordingMiddleware::class)->prompts)->toBe(['Capital of Portugal?']);
        expect($container->get(Agent::class))->toBe($container->get(AgentInterface::class));
        expect($container->get('papi.agent'))->toBe($container->get(AgentInterface::class));
    });

    it('registers no agent when no provider is configured', function () {
        $container = compiledContainer([]);

        expect($container->has(AgentInterface::class))->toBeFalse();
        expect($container->has(ProviderInterface::class))->toBeFalse();
    });
});
