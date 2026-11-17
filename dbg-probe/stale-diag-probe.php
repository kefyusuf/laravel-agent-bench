<?php

// Debug probe: the stale-envelope flow with full queue diagnostics.

require '/app/var/dbg-se3/vendor/autoload.php';
$app = require '/app/var/dbg-se3/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

Illuminate\Support\Facades\Mail::fake();

// wipe state so the probe is idempotent
Illuminate\Support\Facades\DB::table('jobs')->delete();
Illuminate\Support\Facades\DB::table('failed_jobs')->delete();
Illuminate\Support\Facades\DB::table('invoices')->delete();
Illuminate\Support\Facades\DB::table('customers')->delete();

$c = App\Models\Customer::create(['name' => 'Kim', 'billing_email' => 'kim-old@example.test']);
$i = App\Models\Invoice::create(['customer_id' => $c->id, 'number' => 'INV-X', 'status' => 'open']);

$response = $kernel->handle(Illuminate\Http\Request::create('/invoices/' . $i->id . '/paid', 'POST'));
echo 'endpoint status=' . $response->getStatusCode() . "\n";
if ($response->getStatusCode() !== 200) {
    echo 'BODY: ' . $response->getContent() . "\n";
}
echo 'jobs rows=' . Illuminate\Support\Facades\DB::table('jobs')->count() . "\n";
echo 'failed rows=' . Illuminate\Support\Facades\DB::table('failed_jobs')->count() . "\n";

$exit = Illuminate\Support\Facades\Artisan::call('queue:work', ['--once' => true, '--sleep' => '0']);
echo "worker exit=$exit\n";
echo "worker output:\n" . Illuminate\Support\Facades\Artisan::output() . "\n";
echo 'jobs rows after=' . Illuminate\Support\Facades\DB::table('jobs')->count() . "\n";
echo 'failed rows after=' . Illuminate\Support\Facades\DB::table('failed_jobs')->count() . "\n";
echo 'mail sent count=' . count(Illuminate\Support\Facades\Mail::sentMailables()) . "\n";
foreach (Illuminate\Support\Facades\Mail::sentMailables() as $m) {
    echo 'sent class: ' . get_class($m) . "\n";
}
echo "probe done\n";
foreach (Illuminate\Support\Facades\Mail::sentMailables() as $m) {
    echo 'sent class: ' . get_class($m) . "\n";
}
