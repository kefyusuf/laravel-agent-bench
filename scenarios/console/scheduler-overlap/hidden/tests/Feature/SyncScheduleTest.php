<?php

namespace Tests\Feature;

use App\Console\Commands\SyncMarketplaceCommand;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Encodes the scheduling invariant of the marketplace sync: the overlap mutex
 * must outlive the task's documented worst-case runtime, otherwise a second
 * hourly run starts while the first is still executing and imports run twice.
 */
class SyncScheduleTest extends TestCase
{
    public function test_the_sync_schedule_holds_the_mutex_longer_than_the_documented_runtime(): void
    {
        $event = $this->syncEvent();

        $this->assertNotNull($event, 'app:sync-marketplace is not scheduled.');

        $this->assertTrue(
            (bool) $event->withoutOverlapping,
            'The marketplace sync has no overlap mutex: a second hourly run starts while the first is still executing and imports run twice.',
        );

        $this->assertGreaterThanOrEqual(
            SyncMarketplaceCommand::MAX_RUNTIME_MINUTES,
            $event->expiresAt,
            'The overlap mutex expires after ' . $event->expiresAt . ' minutes, but the sync legitimately runs up to ' . SyncMarketplaceCommand::MAX_RUNTIME_MINUTES . ' minutes: the mutex drops mid-run and a second hourly run starts a duplicate import.',
        );
    }

    public function test_the_sync_runs_hourly(): void
    {
        $event = $this->syncEvent();

        $this->assertSame('0 * * * *', $event->expression);
    }

    private function syncEvent()
    {
        /** @var Schedule $schedule */
        $schedule = $this->app->make(Schedule::class);

        foreach ($schedule->events() as $event) {
            if (str_contains($event->command ?? '', 'app:sync-marketplace')) {
                return $event;
            }
        }

        return null;
    }
}
