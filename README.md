# PapiAI Symfony Bundle

[![CI](https://github.com/papi-ai/symfony/workflows/CI/badge.svg)](https://github.com/papi-ai/symfony/actions?query=workflow%3ACI) [![Latest Version](https://img.shields.io/packagist/v/papi-ai/symfony.svg)](https://packagist.org/packages/papi-ai/symfony) [![Total Downloads](https://img.shields.io/packagist/dt/papi-ai/symfony.svg)](https://packagist.org/packages/papi-ai/symfony) [![PHP Version](https://img.shields.io/packagist/php-v/papi-ai/symfony.svg)](https://packagist.org/packages/papi-ai/symfony) [![License](https://img.shields.io/packagist/l/papi-ai/symfony.svg)](https://packagist.org/packages/papi-ai/symfony)

Symfony bridge for [PapiAI](https://github.com/papi-ai/papi-core) -- integrate AI agents into your Symfony application with full dependency injection, configuration, and service wiring.

## Installation

Install the bundle and at least one provider:

```bash
composer require papi-ai/symfony papi-ai/anthropic
```

## Bundle Registration

With Symfony Flex, the bundle is auto-registered. Otherwise add it to `config/bundles.php`:

```php
return [
    // ...
    PapiAI\Symfony\PapiBundle::class => ['all' => true],
];
```

## Configuration

Create `config/packages/papi.yaml`:

```yaml
papi:
    default_provider: anthropic

    providers:
        anthropic:
            api_key: '%env(ANTHROPIC_API_KEY)%'
            effort: high
            replay_thinking: drop_on_edit

        ollama:
            base_url: '%env(OLLAMA_BASE_URL)%'
            model: qwen3

    agent:
        instructions: 'You answer questions about our catalogue.'
        max_tokens: 8192

    middleware:
        - PapiAI\Core\Middleware\LoggingMiddleware
        - app.middleware.rate_limit

    conversation:
        store: file
        path: '%kernel.project_dir%/var/papi/conversations'
```

### Providers

An entry is named after its provider (`openai`, `anthropic`, `google`, `grok`, `deepseek`, `mistral`, `groq`, `cohere`, `azure-openai`, `ollama`) and is built by that provider's own factory, so every setting the provider offers is available and nothing else is accepted. A provider that is not installed fails the container build. Leave `model` out to get the provider's current default.

`default_provider` is the entry the agent and `ProviderInterface` use. Left out, it is `openai` when configured, otherwise the first entry.

An entry with a `driver` can take any name, which is how one provider is configured twice. The driver is the provider's factory class; a provider class works too, built by the factory beside it:

```yaml
papi:
    providers:
        fast:
            driver: PapiAI\Anthropic\AnthropicProviderFactory
            api_key: '%env(ANTHROPIC_API_KEY)%'
            model: claude-haiku-5-5
```

A provider of your own is configured the same way, with a factory that implements `PapiAI\Core\Contracts\ProviderFactoryInterface` (extending `PapiAI\Core\Factory\AbstractProviderFactory` is the short way).

### Middleware

Middleware are service IDs. A class that is not a service yet is registered with autowiring, so the core middleware can be listed by class.

## Services

| Service | What it is |
|---|---|
| `PapiAI\Core\Contracts\ProviderInterface`, `papi.provider` | The default provider |
| `papi.provider.<name>` | Each configured provider |
| `PapiAI\Core\Contracts\AgentInterface`, `PapiAI\Core\Agent`, `papi.agent` | The agent on the default provider |
| `PapiAI\Core\Contracts\ConversationStoreInterface`, `papi.conversation_store` | The configured conversation store |
| `PapiAI\Core\Contracts\QueueInterface` | `MessengerQueue`, when Messenger is enabled |

## Usage

### Injecting the Agent

```php
use PapiAI\Core\Contracts\AgentInterface;

final class AskController
{
    public function __construct(
        private readonly AgentInterface $agent,
    ) {
    }

    public function __invoke(string $question): string
    {
        return $this->agent->run($question)->text;
    }
}
```

### Injecting a Provider

```php
use PapiAI\Core\Contracts\ProviderInterface;
use PapiAI\Core\Message;

final class ChatController
{
    public function __construct(
        private readonly ProviderInterface $provider,
    ) {
    }

    public function chat(string $message): string
    {
        return $this->provider->chat([Message::user($message)])->text;
    }
}
```

### Using Conversation Storage

```php
use PapiAI\Core\Contracts\ConversationStoreInterface;
use PapiAI\Core\Conversation;

final class ConversationService
{
    public function __construct(
        private readonly ConversationStoreInterface $store,
    ) {
    }

    public function saveConversation(string $id, Conversation $conversation): void
    {
        $this->store->save($id, $conversation);
    }

    public function loadConversation(string $id): ?Conversation
    {
        return $this->store->load($id);
    }
}
```

### Using Doctrine Conversation Store

Install Doctrine DBAL and configure the store:

```bash
composer require doctrine/dbal
```

```yaml
papi:
    conversation:
        store: doctrine
```

Create the conversations table in your database:

```sql
CREATE TABLE papi_conversations (
    id VARCHAR(255) PRIMARY KEY,
    data JSON NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
);
```

### Using Messenger Queue

With Messenger enabled, `QueueInterface` dispatches each job as a `PapiAI\Symfony\Queue\RunAgentJob` message, handled by the bundle. Route it to an async transport to run jobs in a worker:

```yaml
framework:
    messenger:
        transports:
            async: '%env(MESSENGER_TRANSPORT_DSN)%'
        routing:
            PapiAI\Symfony\Queue\RunAgentJob: async
```

A job names its agent by service ID: `PapiAI\Core\Contracts\AgentInterface` or `PapiAI\Core\Agent` for the configured one, or the class of any service implementing `AgentInterface`.

```php
use PapiAI\Core\AgentJob;
use PapiAI\Core\Contracts\AgentInterface;
use PapiAI\Core\Contracts\QueueInterface;

final class AgentService
{
    public function __construct(
        private readonly QueueInterface $queue,
    ) {
    }

    public function ask(string $prompt): string
    {
        return $this->queue->dispatch(new AgentJob(agentClass: AgentInterface::class, prompt: $prompt));
    }

    public function answer(string $jobId): ?string
    {
        return $this->queue->status($jobId)->result?->text;
    }
}
```

Statuses, with the full response once a job completes, are kept in the `cache.app` pool. Workers on other hosts need a shared pool:

```yaml
framework:
    cache:
        pools:
            cache.papi_jobs:
                adapter: cache.adapter.redis

papi:
    queue:
        status_pool: cache.papi_jobs
```

## Upgrading to 0.16

- `MessengerQueue` sends `PapiAI\Symfony\Queue\RunAgentJob` instead of `PapiAI\Core\AgentJob`, and the bundle handles it. Move any Messenger routing from `AgentJob` to `RunAgentJob`, or jobs run synchronously in the request. A handler of your own for `AgentJob` no longer receives jobs.
- `DoctrineConversationStore` is registered only with `conversation.store: doctrine`. Inject `ConversationStoreInterface` rather than the class.
- Each provider is built by its own factory, so an entry may hold only the settings its provider takes. Any other key fails the container build. A `driver` naming a provider class keeps working when a factory sits beside it, as it does for every official provider; a provider of your own needs a factory implementing `PapiAI\Core\Contracts\ProviderFactoryInterface`.
- `default_provider` must name a configured entry, or the container build fails.
- `papi.provider.<name>` is created by its provider's factory: its definition's class is `ProviderInterface` and its one argument is the settings array.

## Requirements

- PHP 8.2+
- Symfony 6.4, 7 or 8
- `papi-ai/papi-core` ^0.15.4, and at least one provider package

## License

MIT License. See [LICENSE](LICENSE) for details.
