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

namespace PapiAI\Symfony\Tests\Fixtures;

use PapiAI\Core\Contracts\ProviderInterface;
use PapiAI\Core\Factory\AbstractProviderFactory;
use PapiAI\Core\Factory\Setting;
use PapiAI\Core\Factory\SettingValues;

final class TestProviderFactory extends AbstractProviderFactory
{
    public function name(): string
    {
        return 'test';
    }

    public function package(): string
    {
        return 'papi-ai/test';
    }

    public function settings(): array
    {
        return [
            new Setting('api_key', true, 'Test key'),
            new Setting('model', false, 'Default model'),
        ];
    }

    protected function build(SettingValues $values): ProviderInterface
    {
        return new TestProvider($this->argumentsGiven([
            'api_key' => $values->stringOrNull('api_key'),
            'model' => $values->stringOrNull('model'),
        ]));
    }
}
