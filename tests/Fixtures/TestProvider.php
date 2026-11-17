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

namespace PapiAI\Symfony\Tests\Fixtures;

use PapiAI\Core\Contracts\ProviderInterface;
use PapiAI\Core\Message;
use PapiAI\Core\Response;

/**
 * Remembers the settings it was built with and the last request it was sent.
 */
final class TestProvider implements ProviderInterface
{
    /** @var list<Message> */
    public array $lastMessages = [];

    /** @var array<string, mixed> */
    public array $lastOptions = [];

    /**
     * @param array<string, mixed> $settings
     */
    public function __construct(
        public readonly array $settings = [],
    ) {
    }

    public function chat(array $messages, array $options = []): Response
    {
        $this->lastMessages = $messages;
        $this->lastOptions = $options;

        return new Response(text: 'Lisbon');
    }

    public function stream(array $messages, array $options = []): iterable
    {
        return [];
    }

    public function supportsTool(): bool
    {
        return false;
    }

    public function supportsVision(): bool
    {
        return false;
    }

    public function supportsStructuredOutput(): bool
    {
        return false;
    }

    public function getName(): string
    {
        return 'test';
    }
}
