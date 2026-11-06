<?php

namespace Tests\Feature;

use App\Services\StockReservationService;
use Tests\TestCase;

/**
 * Encodes the oversell invariant DETERMINISTICALLY: the reservation decision
 * must be a single atomic server-side operation. CONFIG RESETSTAT isolates the
 * command counters of exactly one reserve() call — a check-then-act
 * implementation issues GET + DECR as separate client commands, while an
 * atomic fix executes the decision server-side (EVAL/EVALSHA).
 */
class ReservationAtomicityTest extends TestCase
{
    private StockReservationService $reservations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reservations = new StockReservationService;
        $this->reservations->redis()->del('stock:PS5', 'reserved:PS5');
        $this->reservations->redis()->set('stock:PS5', 40);
    }

    public function test_the_reservation_decision_is_one_atomic_server_side_operation(): void
    {
        $redis = $this->reservations->redis();

        $redis->rawCommand('CONFIG', 'RESETSTAT');

        $this->reservations->reserve('PS5');

        $stats = (array) $redis->info('commandstats');

        $atomicDecision = isset($stats['cmdstat_eval']) || isset($stats['cmdstat_evalsha']);

        $this->assertTrue(
            $atomicDecision,
            'The reservation decided on the client side (GET + DECR as separate commands): that window between the read and the decrement is the oversell race. The decision must be one atomic server-side operation (a Lua script executed with EVAL).',
        );
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

    public function test_the_endpoint_reports_sold_out_with_410(): void
    {
        $reservations = new StockReservationService;
        $reservations->redis()->set('stock:PS5', 5);
        for ($i = 0; $i < 5; $i++) {
            $reservations->reserve('PS5');
        }

        $this->postJson('/flashsale/PS5/reserve')->assertStatus(410);
    }
}
