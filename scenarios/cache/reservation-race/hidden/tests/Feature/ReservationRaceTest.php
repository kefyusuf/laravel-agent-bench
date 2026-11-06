<?php

namespace Tests\Feature;

use App\Services\StockReservationService;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * Encodes the oversell invariant under REAL concurrency: several worker
 * processes hit the reservation service simultaneously through the same
 * entrypoint production uses (the load-worker artisan command).
 */
class ReservationRaceTest extends TestCase
{
    private StockReservationService $reservations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reservations = new StockReservationService;
        $this->reservations->redis()->del('stock:PS5', 'reserved:PS5');
        $this->reservations->redis()->set('stock:PS5', 40);
    }

    public function test_sequential_reservations_stop_at_zero(): void
    {
        $granted = 0;
        for ($i = 0; $i < 45; $i++) {
            if ($this->reservations->reserve('PS5')) {
                $granted++;
            }
        }

        $this->assertSame(40, $granted, 'Exactly the stock must be granted, sequentially.');
        $this->assertSame(0, $this->reservations->stock('PS5'));
    }

    public function test_concurrent_workers_never_oversell(): void
    {
        $workers = 8;
        $iterations = 15; // 120 attempts against 40 units

        // Every worker holds until the drop opens, then hits at the same moment.
        $processes = [];
        for ($w = 0; $w < $workers; $w++) {
            $processes[] = Process::start(
                'php ' . base_path('artisan') . ' flashsale:reserve PS5 ' . $iterations . ' --gate=race:start',
            );
        }

        usleep(700_000); // let every worker reach the gate
        $this->reservations->redis()->set('race:start', 'go');

        foreach ($processes as $process) {
            $process->wait();
        }

        $this->reservations->redis()->del('race:start');

        $stock = $this->reservations->stock('PS5');
        $granted = $this->reservations->granted('PS5');

        $this->assertGreaterThanOrEqual(
            0,
            $stock,
            "Stock ended at {$stock}: more reservations were granted than units existed (oversell). The read-decide-decrement sequence must be atomic.",
        );

        $this->assertSame(
            40,
            $granted,
            "Granted reservations: {$granted} against 40 units (stock ended at {$stock}) — concurrent workers oversold the drop.",
        );
    }
}
