# PapiAI Symfony Bundle

Symfony bridge for [PapiAI](https://github.com/papi-ai/papi-core) -- integrate AI agents into your Symfony application with full dependency injection, configuration, and service wiring.

## Installation

```bash
composer require papi-ai/symfony
```

## Bundle Registration

If you are not using Symfony Flex, register the bundle manually in `config/bundles.php`:

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

## Usage

### Injecting a Provider

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
            ['role' => 'user', 'content' => $message],
        ]);

        return $response->getText();
    }
}
```

### Using Conversation Storage

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

Install Symfony Messenger:

```bash
composer require symfony/messenger
```

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
        $job = new AgentJob(
            agentClass: $agentClass,
            prompt: $prompt,
        );

        return $this->queue->dispatch($job);
    }
}
```

## License

MIT License. See [LICENSE](LICENSE) for details.
