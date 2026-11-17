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

/**
 * The Messenger message MessengerQueue sends: an agent job and the ID its status is kept under.
 */
final class RunAgentJob
{
    public function __construct(
        public readonly string $jobId,
        public readonly AgentJob $job,
    ) {
    }
}
