<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class InvoiceNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    public function test_a_paid_invoice_notification_is_queued_and_delivered(): void
    {
        $customer = Customer::create(['name' => 'Kim', 'billing_email' => 'kim@example.test']);
        $invoice = Invoice::create(['customer_id' => $customer->id, 'number' => 'INV-1', 'status' => 'open']);

        $this->postJson("/invoices/{$invoice->id}/paid")->assertOk();

        $this->artisan('queue:work', ['--once' => true, '--sleep' => '0'])->assertSuccessful();

        Notification::assertSentTo($customer, \App\Notifications\InvoicePaidNotification::class, 1);
    }
}
