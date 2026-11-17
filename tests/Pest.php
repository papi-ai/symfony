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

use PapiAI\Symfony\DependencyInjection\PapiExtension;
use PapiAI\Symfony\PapiBundle;
use PapiAI\Symfony\Tests\Fixtures\PublicForTestsPass;
use PapiAI\Symfony\Tests\Fixtures\TestProviderFactory;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Compiles a container the way a kernel does, with the bundle's passes and only the test provider installed.
 *
 * @param array<string, mixed> $config What config/packages/papi.yaml would hold
 * @param (Closure(ContainerBuilder): void)|null $prepare Adds what the application would define
 */
function compiledContainer(array $config, ?Closure $prepare = null): ContainerBuilder
{
    $container = new ContainerBuilder();
    $container->setParameter('kernel.project_dir', sys_get_temp_dir());
    (new PapiBundle())->build($container);
    $container->registerExtension(new PapiExtension([new TestProviderFactory()]));
    $container->loadFromExtension('papi', $config);

    if ($prepare !== null) {
        $prepare($container);
    }

    $container->addCompilerPass(new PublicForTestsPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, -1000);
    $container->compile(resolveEnvPlaceholders: true);

    return $container;
}
