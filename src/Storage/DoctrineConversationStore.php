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

namespace PapiAI\Symfony\Storage;

use Doctrine\DBAL\Connection;
use PapiAI\Core\Contracts\ConversationStoreInterface;
use PapiAI\Core\Conversation;

/**
 * Persists agent conversations to a relational database via Doctrine DBAL.
 *
 * Stores serialised conversation data as JSON in a configurable table,
 * supporting insert-or-update semantics so conversations can be resumed
 * across requests.
 */
class DoctrineConversationStore implements ConversationStoreInterface
{
    /**
     * @param Connection $connection The Doctrine DBAL connection used for persistence
     * @param string     $tableName  Database table name for storing conversations
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly string $tableName = 'papi_conversations',
    ) {
    }

    /**
     * Save or update a conversation in the database.
     *
     * Performs an upsert: inserts a new row if the conversation ID does not
     * exist, or updates the existing row otherwise.
     *
     * @param string       $id           Unique conversation identifier
     * @param Conversation $conversation The conversation to persist
     *
     * @return void
     *
     * @throws \JsonException          If the conversation data cannot be JSON-encoded
     * @throws \Doctrine\DBAL\Exception If the database query fails
     */
    public function save(string $id, Conversation $conversation): void
    {
        $data = json_encode($conversation->toArray(), JSON_THROW_ON_ERROR);
        $now = date('Y-m-d H:i:s');

        $exists = $this->connection->fetchOne(
            "SELECT COUNT(*) FROM {$this->tableName} WHERE id = ?",
            [$id],
        );

        if ($exists) {
            $this->connection->executeStatement(
                "UPDATE {$this->tableName} SET data = ?, updated_at = ? WHERE id = ?",
                [$data, $now, $id],
            );
        } else {
            $this->connection->executeStatement(
                "INSERT INTO {$this->tableName} (id, data, created_at, updated_at) VALUES (?, ?, ?, ?)",
                [$id, $data, $now, $now],
            );
        }
    }

    /**
     * Load a previously saved conversation by its ID.
     *
     * Returns null if the conversation does not exist or if the stored
     * data cannot be decoded into a valid conversation array.
     *
     * @param string $id Unique conversation identifier
     *
     * @return Conversation|null The restored conversation, or null if not found
     *
     * @throws \Doctrine\DBAL\Exception If the database query fails
     */
    public function load(string $id): ?Conversation
    {
        $row = $this->connection->fetchAssociative(
            "SELECT data FROM {$this->tableName} WHERE id = ?",
            [$id],
        );

        if ($row === false) {
            return null;
        }

        $data = json_decode($row['data'], true);

        if (!is_array($data)) {
            return null;
        }

        return Conversation::fromArray($data);
    }

    /**
     * Delete a conversation from the database.
     *
     * Silently succeeds if the conversation does not exist.
     *
     * @param string $id Unique conversation identifier to remove
     *
     * @return void
     *
     * @throws \Doctrine\DBAL\Exception If the database query fails
     */
    public function delete(string $id): void
    {
        $this->connection->executeStatement(
            "DELETE FROM {$this->tableName} WHERE id = ?",
            [$id],
        );
    }

    /**
     * List conversation IDs, ordered by most recently updated first.
     *
     * @param int $limit Maximum number of conversation IDs to return
     *
     * @return list<string> Conversation IDs sorted by last update descending
     *
     * @throws \Doctrine\DBAL\Exception If the database query fails
     */
    public function list(int $limit = 50): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT id FROM {$this->tableName} ORDER BY updated_at DESC LIMIT ?",
            [$limit],
        );

        return array_map(fn (array $row) => $row['id'], $rows);
    }
}
