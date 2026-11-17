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

use PapiAI\Core\Agent;
use PapiAI\Core\Contracts\AgentInterface;
use PapiAI\Core\Contracts\ConversationStoreInterface;
use PapiAI\Core\Contracts\ProviderFactoryInterface;
use PapiAI\Core\Contracts\ProviderInterface;
use PapiAI\Core\Contracts\QueueInterface;
use PapiAI\Core\Exception\ConfigurationException;
use PapiAI\Core\Factory\ComposerDiscovery;
use PapiAI\Core\Factory\ProviderRegistry;
use PapiAI\Core\Storage\FileConversationStore;
use PapiAI\Symfony\Queue\JobStatuses;
use PapiAI\Symfony\Queue\MessengerQueue;
use PapiAI\Symfony\Queue\RunAgentJob;
use PapiAI\Symfony\Queue\RunAgentJobHandler;
use PapiAI\Symfony\Storage\DoctrineConversationStore;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Registers each configured provider (built by its factory), the agent on the default one,
 * the conversation store and, when Messenger is installed, the queue.
 */
class PapiExtension extends Extension
{
    public const AGENT_TAG = 'papi.agent';

    private ?ProviderRegistry $installed = null;

    /**
     * @param iterable<ProviderFactoryInterface> $factories The installed providers' factories
     */
    public function __construct(
        private readonly iterable $factories = new ComposerDiscovery(),
    ) {
    }

    /**
     * @param array<array-key, array<array-key, mixed>> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        /** @var array{default_provider: string, providers: array<string, array<string, mixed>>, agent: array{instructions: string|null, max_tokens: int|null}, middleware: list<string>, conversation: array{store: string, path: string}, queue: array{status_pool: string}} $config */
        $config = $this->processConfiguration(new Configuration(), $configs);

        $this->registerProviders($config['providers'], $config['default_provider'], $container);
        $this->registerConversationStore($config['conversation'], $container);
        $container->registerForAutoconfiguration(AgentInterface::class)->addTag(self::AGENT_TAG);

        if ($container->hasAlias(ProviderInterface::class)) {
            $this->registerAgent($config['agent'], $config['middleware'], $container);
        }

        if (interface_exists(MessageBusInterface::class)) {
            $this->registerQueue($config['queue']['status_pool'], $container);
        }
    }

    /**
     * @param array<string, array<string, mixed>> $providers
     */
    private function registerProviders(array $providers, string $default, ContainerBuilder $container): void
    {
        foreach ($providers as $name => $settings) {
            $driver = $settings['driver'] ?? null;
            unset($settings['driver']);
            $factory = is_string($driver) ? $this->factoryNamedBy($name, $driver) : $this->installedFactoryOf($name);

            if (!$container->hasDefinition($factory)) {
                $container->register($factory, $factory);
            }

            $container->register('papi.provider.' . $name, ProviderInterface::class)
                ->setFactory([new Reference($factory), 'create'])
                ->setArguments([$settings]);
        }

        if (isset($providers[$default])) {
            $container->setAlias(ProviderInterface::class, 'papi.provider.' . $default);
            $container->setAlias('papi.provider', 'papi.provider.' . $default);
        }
    }

    /**
     * @return class-string<ProviderFactoryInterface>
     */
    private function factoryNamedBy(string $name, string $driver): string
    {
        foreach ([$driver, $driver . 'Factory'] as $class) {
            if (is_a($class, ProviderFactoryInterface::class, true)) {
                return $class;
            }
        }

        throw new ConfigurationException(sprintf(
            'Provider "%s" names the driver "%s", which is not a provider factory and has no "%sFactory" beside it.',
            $name,
            $driver,
            $driver,
        ));
    }

    /**
     * @return class-string<ProviderFactoryInterface>
     */
    private function installedFactoryOf(string $name): string
    {
        $this->installed ??= new ProviderRegistry($this->factories);
        $names = [];

        foreach ($this->installed->installed() as $factory) {
            if ($factory->name() === $name) {
                return $factory::class;
            }

            $names[] = '"' . $factory->name() . '"';
        }

        throw new ConfigurationException(sprintf(
            'Provider "%s" is not installed. Installed: %s.',
            $name,
            $names === [] ? 'none' : implode(', ', $names),
        ));
    }

    /**
     * @param array{instructions: string|null, max_tokens: int|null} $agent
     * @param list<string> $middleware
     */
    private function registerAgent(array $agent, array $middleware, ContainerBuilder $container): void
    {
        $container->register('papi.agent', Agent::class)
            ->setArguments(array_filter([
                '$provider' => new Reference('papi.provider'),
                '$model' => '',
                '$instructions' => $agent['instructions'],
                '$maxTokens' => $agent['max_tokens'],
                '$middleware' => array_map(static fn (string $id): Reference => new Reference($id), $middleware),
            ], static fn (mixed $argument): bool => $argument !== null))
            ->addTag(self::AGENT_TAG, ['key' => AgentInterface::class])
            ->addTag(self::AGENT_TAG, ['key' => Agent::class]);
        $container->setAlias(AgentInterface::class, 'papi.agent');
        $container->setAlias(Agent::class, 'papi.agent');
    }

    /**
     * @param array{store: string, path: string} $conversation
     */
    private function registerConversationStore(array $conversation, ContainerBuilder $container): void
    {
        if ($conversation['store'] === 'doctrine') {
            $container->register(ConversationStoreInterface::class, DoctrineConversationStore::class)
                ->setArguments([new Reference('doctrine.dbal.default_connection'), 'papi_conversations']);
        } else {
            $container->register(ConversationStoreInterface::class, FileConversationStore::class)
                ->setArguments([$conversation['path']]);
        }

        $container->setAlias('papi.conversation_store', ConversationStoreInterface::class);
    }

    private function registerQueue(string $statusPool, ContainerBuilder $container): void
    {
        $container->register(JobStatuses::class, JobStatuses::class)
            ->setArguments([new Reference($statusPool)]);
        $container->register(MessengerQueue::class, MessengerQueue::class)
            ->setArguments([new Reference('messenger.default_bus'), new Reference(JobStatuses::class)]);
        $container->register(RunAgentJobHandler::class, RunAgentJobHandler::class)
            ->setArguments([
                new ServiceLocatorArgument(new TaggedIteratorArgument(self::AGENT_TAG, indexAttribute: 'key', needsIndexes: true)),
                new Reference(JobStatuses::class),
            ])
            ->addTag('messenger.message_handler', ['handles' => RunAgentJob::class]);
        $container->setAlias(QueueInterface::class, MessengerQueue::class);
    }
}
