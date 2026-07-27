# Symfony Bridge

Symfony bridge for PapiAI. Integrate AI agents into your Symfony application with full dependency injection, configuration, and service wiring.

## Installation

```bash
composer require papi-ai/symfony papi-ai/anthropic
```

## Bundle Registration

With Symfony Flex, the bundle is auto-registered. Otherwise add to `config/bundles.php`:

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
    default_provider: openai

    providers:
        openai:
            driver: PapiAI\OpenAI\OpenAIProvider
            api_key: '%env(OPENAI_API_KEY)%'
            model: gpt-4o

        anthropic:
            driver: PapiAI\Anthropic\AnthropicProvider
            api_key: '%env(ANTHROPIC_API_KEY)%'
            model: claude-sonnet-4-20250514

    middleware:
        - app.middleware.logging
        - app.middleware.rate_limit

    conversation:
        store: file
        path: '%kernel.project_dir%/var/papi/conversations'
```

## Dependency Injection

Inject the provider directly into your services and controllers:

```php
use PapiAI\Core\Contracts\ProviderInterface;

class ChatController
{
    public function __construct(
        private ProviderInterface $provider,
    ) {}

    public function chat(string $message): string
    {
        $response = $this->provider->chat([
            Message::user($message),
        ]);
        return $response->text;
    }
}
```

## Conversation Storage

### File-based (Default)

Conversations are stored as JSON files. Configure the path in your YAML config:

```yaml
papi:
    conversation:
        store: file
        path: '%kernel.project_dir%/var/papi/conversations'
```

### Doctrine Store

Install Doctrine DBAL and switch the store:

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

Use the store via dependency injection:

```php
use PapiAI\Core\Contracts\ConversationStoreInterface;
use PapiAI\Core\Conversation;

class ConversationService
{
    public function __construct(
        private ConversationStoreInterface $store,
    ) {}

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

## Messenger Queue

Install Symfony Messenger:

```bash
composer require symfony/messenger
```

Dispatch agent jobs via the queue:

```php
use PapiAI\Core\Contracts\QueueInterface;
use PapiAI\Core\AgentJob;

class AgentService
{
    public function __construct(
        private QueueInterface $queue,
    ) {}

    public function dispatchJob(string $agentClass, string $prompt): string
    {
        return $this->queue->dispatch(new AgentJob(
            agentClass: $agentClass,
            prompt: $prompt,
        ));
    }
}
```

## Requirements

- PHP 8.2+
- Symfony 6.4 or 7.x
- `papi-ai/papi-core` ^0.14
