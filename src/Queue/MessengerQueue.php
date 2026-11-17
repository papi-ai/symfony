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
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Queues agent jobs on a Symfony Messenger bus.
 *
 * Each job travels as a RunAgentJob message, which RunAgentJobHandler runs; route that message
 * to an async transport to run it in a worker. Statuses are kept in JobStatuses, whose pool the
 * web process and the workers must share.
 */
class MessengerQueue implements QueueInterface
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly JobStatuses $statuses = new JobStatuses(),
    ) {
    }

    public function dispatch(AgentJob $job): string
    {
        $jobId = bin2hex(random_bytes(16));
        $this->statuses->record(new JobStatus($jobId, JobStatus::PENDING));
        $this->bus->dispatch(new RunAgentJob($jobId, $job));

        return $jobId;
    }

    public function status(string $jobId): JobStatus
    {
        return $this->statuses->of($jobId);
    }
}
