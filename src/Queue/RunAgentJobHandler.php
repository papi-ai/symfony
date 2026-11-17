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

use PapiAI\Core\Contracts\AgentInterface;
use PapiAI\Core\JobStatus;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Throwable;

/**
 * Runs a queued job on the agent it names and records how it went.
 *
 * Agents are the services tagged papi.agent: the bundle's own agent under AgentInterface and
 * Agent, and every service implementing AgentInterface under its service ID.
 */
final class RunAgentJobHandler
{
    public function __construct(
        private readonly ContainerInterface $agents,
        private readonly JobStatuses $statuses,
    ) {
    }

    public function __invoke(RunAgentJob $message): void
    {
        $this->statuses->record(new JobStatus($message->jobId, JobStatus::RUNNING));

        try {
            $response = $this->agentNamed($message->job->agentClass)->run($message->job->prompt, $message->job->options);
        } catch (Throwable $failure) {
            $this->statuses->record(new JobStatus($message->jobId, JobStatus::FAILED, error: $failure->getMessage()));

            return;
        }

        $this->statuses->record(new JobStatus($message->jobId, JobStatus::COMPLETED, result: $response));
    }

    private function agentNamed(string $id): AgentInterface
    {
        if (!$this->agents->has($id)) {
            throw new RuntimeException(sprintf('No agent "%s" is registered. Implement AgentInterface on it, or tag it "papi.agent".', $id));
        }

        $agent = $this->agents->get($id);

        if (!$agent instanceof AgentInterface) {
            throw new RuntimeException(sprintf('Service "%s" is a %s, not an agent.', $id, get_debug_type($agent)));
        }

        return $agent;
    }
}
