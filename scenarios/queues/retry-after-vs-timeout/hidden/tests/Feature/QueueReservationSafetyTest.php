<?php

namespace Tests\Feature;

use App\Jobs\TranscodeVideo;
use App\Models\Media;
use Tests\TestCase;

/**
 * Encodes the worker-reservation invariant: a job whose timeout is longer than its
 * connection's retry_after can be reserved by a second worker while the first is
 * still running, which duplicates the job's side effects.
 */
class QueueReservationSafetyTest extends TestCase
{
    public function test_every_queued_job_times_out_before_its_connection_retries_it(): void
    {
        $retryAfter = (int) config('queue.connections.database.retry_after');

        $this->assertGreaterThan(
            0,
            $retryAfter,
            'The database queue connection must define retry_after.',
        );

        $jobs = [
            TranscodeVideo::class => (new TranscodeVideo(new Media))->timeout ?? 60,
        ];

        foreach ($jobs as $job => $timeout) {
            $this->assertGreaterThan(
                0,
                $timeout,
                "{$job} declares an unbounded timeout; a redelivery can start while an old run is still going.",
            );

            $this->assertGreaterThan(
                $timeout,
                $retryAfter,
                "{$job} runs for up to {$timeout}s but the database connection re-delivers jobs after {$retryAfter}s: two workers can run the same job concurrently and duplicate its side effects. retry_after must exceed every job timeout on the connection.",
            );
        }
    }
}
