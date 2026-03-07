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

namespace PapiAI\Symfony\Queue;

use PapiAI\Core\AgentJob;
use PapiAI\Core\Contracts\QueueInterface;
use PapiAI\Core\JobStatus;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;

class MessengerQueue implements QueueInterface
{
    public function __construct(
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function dispatch(AgentJob $job): string
    {
        $jobId = bin2hex(random_bytes(16));

        $envelope = new Envelope($job, [
            new TransportMessageIdStamp($jobId),
        ]);

        $this->bus->dispatch($envelope);

        return $jobId;
    }

    public function status(string $jobId): JobStatus
    {
        // Basic implementation — Messenger does not natively track job status.
        // In a production setup, you would store job status in a database
        // or use a transport that supports status tracking.
        return new JobStatus(
            jobId: $jobId,
            status: JobStatus::PENDING,
        );
    }
}
