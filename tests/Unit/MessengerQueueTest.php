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

use PapiAI\Core\Agent;
use PapiAI\Core\AgentJob;
use PapiAI\Core\Contracts\AgentInterface;
use PapiAI\Core\Contracts\QueueInterface;
use PapiAI\Core\JobStatus;
use PapiAI\Symfony\Queue\MessengerQueue;
use PapiAI\Symfony\Queue\RunAgentJob;
use PapiAI\Symfony\Queue\RunAgentJobHandler;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;

function withSyncBus(ContainerBuilder $container): void
{
    $container->register('cache.app', ArrayAdapter::class);
    $container->register('messenger.default_bus', MessageBus::class)->setArguments([[
        new Definition(HandleMessageMiddleware::class, [
            new Definition(HandlersLocator::class, [[RunAgentJob::class => [new Reference(RunAgentJobHandler::class)]]]),
        ]),
    ]]);
}

describe('MessengerQueue', function () {
    it('reports a job pending until a worker picks it up', function () {
        $queue = new MessengerQueue(new MessageBus());

        $status = $queue->status($queue->dispatch(new AgentJob(Agent::class, 'Hi')));

        expect($status->isPending())->toBeTrue();
    });

    it('reports an unknown job as pending', function () {
        expect((new MessengerQueue(new MessageBus()))->status('unknown')->status)->toBe(JobStatus::PENDING);
    });

    describe('in a container with a Messenger bus', function () {
        it('runs the job on the agent it names and keeps the answer', function () {
            $container = compiledContainer([
                'default_provider' => 'test',
                'providers' => ['test' => ['api_key' => 'k']],
            ], withSyncBus(...));
            $queue = $container->get(QueueInterface::class);

            $status = $queue->status($queue->dispatch(new AgentJob(AgentInterface::class, 'Capital of Portugal?')));

            expect($status->isCompleted())->toBeTrue();
            expect($status->result?->text)->toBe('Lisbon');
        });

        it('records why a job failed', function () {
            $container = compiledContainer([
                'default_provider' => 'test',
                'providers' => ['test' => ['api_key' => 'k']],
            ], withSyncBus(...));
            $queue = $container->get(QueueInterface::class);

            $status = $queue->status($queue->dispatch(new AgentJob('App\\Agent\\Missing', 'Hi')));

            expect($status->isFailed())->toBeTrue();
            expect($status->error)->toContain('App\\Agent\\Missing');
        });

        it('refuses to run a job on a tagged service that is not an agent', function () {
            $container = compiledContainer([
                'default_provider' => 'test',
                'providers' => ['test' => ['api_key' => 'k']],
            ], function (ContainerBuilder $container): void {
                withSyncBus($container);
                $container->register('app.not_an_agent', stdClass::class)->addTag('papi.agent');
            });
            $queue = $container->get(QueueInterface::class);

            $status = $queue->status($queue->dispatch(new AgentJob('app.not_an_agent', 'Hi')));

            expect($status->error)->toBe('Service "app.not_an_agent" is a stdClass, not an agent.');
        });

        it('keeps statuses in the pool it is given', function () {
            $container = compiledContainer([
                'default_provider' => 'test',
                'providers' => ['test' => ['api_key' => 'k']],
                'queue' => ['status_pool' => 'cache.papi_jobs'],
            ], function (ContainerBuilder $container): void {
                withSyncBus($container);
                $container->register('cache.papi_jobs', ArrayAdapter::class);
            });

            $jobId = $container->get(QueueInterface::class)->dispatch(new AgentJob(Agent::class, 'Hi'));

            expect($container->get('cache.papi_jobs')->hasItem('papi.job.' . $jobId))->toBeTrue();
            expect($container->get('cache.app')->hasItem('papi.job.' . $jobId))->toBeFalse();
        });
    });
});
