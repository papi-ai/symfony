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

use PapiAI\Core\AgentRequest;
use PapiAI\Core\Contracts\MiddlewareInterface;
use PapiAI\Core\Response;

final class RecordingMiddleware implements MiddlewareInterface
{
    /** @var list<string> */
    public array $prompts = [];

    public function process(AgentRequest $request, callable $next): Response
    {
        $this->prompts[] = $request->prompt;

        return $next($request);
    }
}
