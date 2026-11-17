<?php

// Debug probe: dump what Mail::fake actually recorded for a queued notification.

require '/app/var/dbg-se3/vendor/autoload.php';
$app = require '/app/var/dbg-se3/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

Illuminate\Support\Facades\Mail::fake();

$c = App\Models\Customer::create(['name' => 'Kim', 'billing_email' => 'kim-old@example.test']);
$i = App\Models\Invoice::create(['customer_id' => $c->id, 'number' => 'INV-Z', 'status' => 'open']);

$c->notify(new App\Notifications\InvoicePaidNotification($i, $c->billing_email));

// drain the queued job in-process
$exit = Illuminate\Support\Facades\Artisan::call('queue:work', ['--once' => true, '--sleep' => '0']);
echo "worker exit=$exit\n";

$fake = Illuminate\Support\Facades\Mail::getFacadeRoot();
$ref = new ReflectionClass($fake);
foreach ($ref->getProperties() as $prop) {
    $prop->setAccessible(true);
    $val = $prop->getValue($fake);
    if (is_array($val) && count($val) > 0) {
        echo "property {$prop->getName()}:\n";
        foreach ($val as $k => $entry) {
            if (is_object($entry)) {
                echo "  [$k] " . get_class($entry) . "\n";
                $mailable = $entry;
                $mref = new ReflectionClass($mailable);
                foreach ($mref->getProperties() as $mp) {
                    $mp->setAccessible(true);
                    $mv = $mp->getValue($mailable);
                    if (is_array($mv) && count($mv) > 0 && $mp->getName() !== 'attachments' && $mp->getName() !== 'data') {
                        echo "    -> {$mp->getName()}: " . substr(var_export($mv, true), 0, 160) . "\n";
                    }
                }
            } else {
                echo "  [$k] " . substr(var_export($entry, true), 0, 120) . "\n";
            }
        }
    }
}
