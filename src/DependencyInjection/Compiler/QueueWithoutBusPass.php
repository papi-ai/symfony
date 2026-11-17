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

namespace PapiAI\Symfony\DependencyInjection\Compiler;

use PapiAI\Core\Contracts\QueueInterface;
use PapiAI\Symfony\Queue\JobStatuses;
use PapiAI\Symfony\Queue\MessengerQueue;
use PapiAI\Symfony\Queue\RunAgentJobHandler;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Takes the queue out again when Messenger is installed but the application runs no bus.
 */
final class QueueWithoutBusPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if ($container->has('messenger.default_bus')) {
            return;
        }

        $container->removeAlias(QueueInterface::class);

        foreach ([MessengerQueue::class, RunAgentJobHandler::class, JobStatuses::class] as $id) {
            $container->removeDefinition($id);
        }
    }
}
