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

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers, with autowiring, each middleware the agent names by a class that is not yet a service.
 */
final class MiddlewareServicesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('papi.agent')) {
            return;
        }

        /** @var list<Reference> $middleware */
        $middleware = $container->getDefinition('papi.agent')->getArguments()['$middleware'] ?? [];

        foreach ($middleware as $reference) {
            $id = (string) $reference;

            if (!$container->has($id) && class_exists($id)) {
                $container->register($id, $id)->setAutowired(true);
            }
        }
    }
}
