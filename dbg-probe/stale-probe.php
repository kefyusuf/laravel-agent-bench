<?php

// Debug probe: full round trip for the stale-envelope scenario.

require '/app/var/dbg-se2/vendor/autoload.php';
$app = require '/app/var/dbg-se2/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

Illuminate\Support\Facades\Mail::fake();

$c = App\Models\Customer::create(['name' => 'Kim', 'billing_email' => 'kim-old@example.test']);
$i = App\Models\Invoice::create(['customer_id' => $c->id, 'number' => 'INV-X', 'status' => 'open']);

$response = $kernel->handle(Illuminate\Http\Request::create('/invoices/' . $i->id . '/paid', 'POST'));
echo 'endpoint status=' . $response->getStatusCode() . "\n";

echo 'jobs rows=' . Illuminate\Support\Facades\DB::table('jobs')->count() . "\n";

echo "before change: queue size=" . Illuminate\Support\Facades\Queue::size() . "\n";
$c->forceFill(['billing_email' => 'kim-new@example.test'])->save();

$exit = Illuminate\Support\Facades\Artisan::call('queue:work', ['--once' => true, '--sleep' => '0']);
echo "worker exit=$exit\n";
echo "worker output=" . Illuminate\Support\Facades\Artisan::output() . "\n";

foreach (Mail::queuedMailables() as $m) {
    echo "queued mailable: " . get_class($m) . "\n";
}
foreach (Illuminate\Support\Facades\Mail::queuedMailables() as $m) {
    echo "Mail queued: " . get_class($m) . "\n";
}
echo "sent count=" . count(Mail::sentMailables()) . "\n";
