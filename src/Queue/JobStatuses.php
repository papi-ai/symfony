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

use PapiAI\Core\JobStatus;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * The latest status of each queued job, kept in a cache pool that the web process and the
 * workers share.
 */
final class JobStatuses
{
    public function __construct(
        private readonly CacheItemPoolInterface $pool = new ArrayAdapter(),
    ) {
    }

    public function record(JobStatus $status): void
    {
        $this->pool->save($this->pool->getItem($this->keyOf($status->jobId))->set($status));
    }

    /**
     * A job with no status recorded is reported pending.
     */
    public function of(string $jobId): JobStatus
    {
        $status = $this->pool->getItem($this->keyOf($jobId))->get();

        return $status instanceof JobStatus ? $status : new JobStatus($jobId, JobStatus::PENDING);
    }

    private function keyOf(string $jobId): string
    {
        return 'papi.job.' . $jobId;
    }
}
