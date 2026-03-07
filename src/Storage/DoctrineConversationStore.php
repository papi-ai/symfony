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

class DoctrineConversationStore implements ConversationStoreInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $tableName = 'papi_conversations',
    ) {
    }

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

    public function delete(string $id): void
    {
        $this->connection->executeStatement(
            "DELETE FROM {$this->tableName} WHERE id = ?",
            [$id],
        );
    }

    public function list(int $limit = 50): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT id FROM {$this->tableName} ORDER BY updated_at DESC LIMIT ?",
            [$limit],
        );

        return array_map(fn (array $row) => $row['id'], $rows);
    }
}
