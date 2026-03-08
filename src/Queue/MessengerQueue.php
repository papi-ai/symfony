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

/**
 * Dispatches agent jobs asynchronously via Symfony Messenger.
 *
 * Wraps the Messenger message bus to enqueue AgentJob instances for
 * background processing, allowing long-running AI agent tasks to
 * execute outside the HTTP request cycle.
 */
class MessengerQueue implements QueueInterface
{
    /**
     * @param MessageBusInterface $bus The Symfony Messenger bus used to dispatch jobs
     */
    public function __construct(
        private readonly MessageBusInterface $bus,
    ) {
    }

    /**
     * Dispatch an agent job onto the Messenger bus for asynchronous processing.
     *
     * Generates a unique 32-character hex job ID, attaches it as a transport
     * stamp, and dispatches the job through the message bus.
     *
     * @param AgentJob $job The agent job to enqueue
     *
     * @return string The generated job ID for later status tracking
     */
    public function dispatch(AgentJob $job): string
    {
        $jobId = bin2hex(random_bytes(16));

        $envelope = new Envelope($job, [
            new TransportMessageIdStamp($jobId),
        ]);

        $this->bus->dispatch($envelope);

        return $jobId;
    }

    /**
     * Retrieve the current status of a dispatched job.
     *
     * Note: This is a basic implementation that always returns PENDING,
     * since Symfony Messenger does not natively track job status. Override
     * or extend this with a database-backed status store for production use.
     *
     * @param string $jobId The job ID returned by dispatch()
     *
     * @return JobStatus The current status of the job (always PENDING in this implementation)
     */
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
