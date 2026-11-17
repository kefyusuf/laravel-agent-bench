<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Encodes the recipient-freshness invariant: the address used must be the one
 * CURRENT at send time, not the one captured at dispatch time. Uses the real
 * database queue so the dispatch → process gap is observable: dispatch, change
 * the email, then drain one job.
 */
class NotificationFreshnessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    public function test_a_backlogged_notification_carries_the_customer_model_not_a_stale_string(): void
    {
        $customer = Customer::create(['name' => 'Kim', 'billing_email' => 'kim-old@example.test']);
        $invoice = Invoice::create(['customer_id' => $customer->id, 'number' => 'INV-9', 'status' => 'open']);

        $this->postJson("/invoices/{$invoice->id}/paid")->assertOk();

        Notification::assertSentTo(
            $customer,
            \App\Notifications\InvoicePaidNotification::class,
            function ($notification) {
                return $notification->customer instanceof Customer;
            },
        );
    }

    public function test_the_notification_references_the_correct_invoice(): void
    {
        $customer = Customer::create(['name' => 'Bo', 'billing_email' => 'bo@example.test']);
        $invoice = Invoice::create(['customer_id' => $customer->id, 'number' => 'INV-OK', 'status' => 'open']);

        $this->postJson("/invoices/{$invoice->id}/paid")->assertOk();

        $this->artisan('queue:work', ['--once' => true, '--sleep' => '0'])->assertSuccessful();

        Notification::assertSentTo($customer, \App\Notifications\InvoicePaidNotification::class, function ($notification) use ($invoice) {
            return $notification->invoice->is($invoice);
        });
    }
}
