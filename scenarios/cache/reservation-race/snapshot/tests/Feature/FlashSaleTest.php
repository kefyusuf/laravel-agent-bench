<?php

namespace Tests\Feature;

use App\Services\StockReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlashSaleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $reservations = new StockReservationService;
        $reservations->redis()->del('stock:PS5', 'reserved:PS5');
        $reservations->redis()->set('stock:PS5', 5);
    }

    public function test_a_reservation_is_granted_while_stock_remains(): void
    {
        $reservations = new StockReservationService;

        $this->assertTrue($reservations->reserve('PS5'));
        $this->assertSame(4, $reservations->stock('PS5'));
        $this->assertSame(1, $reservations->granted('PS5'));
    }

    public function test_reservations_stop_when_sold_out(): void
    {
        $reservations = new StockReservationService;

        for ($i = 0; $i < 5; $i++) {
            $this->assertTrue($reservations->reserve('PS5'));
        }

        $this->assertFalse($reservations->reserve('PS5'));
        $this->assertSame(0, $reservations->stock('PS5'));
        $this->assertSame(5, $reservations->granted('PS5'));
    }

    public function test_the_endpoint_reports_sold_out_with_410(): void
    {
        $reservations = new StockReservationService;
        for ($i = 0; $i < 5; $i++) {
            $reservations->reserve('PS5');
        }

        $this->postJson('/flashsale/PS5/reserve')->assertStatus(410);
        $this->postJson('/flashsale/PS5/reserve')->assertJson(['reserved' => false]);
    }
}
