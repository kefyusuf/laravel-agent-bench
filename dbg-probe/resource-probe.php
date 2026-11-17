<?php

// Debug probe: instantiate InvoiceResource directly. Lives outside scenarios/.

require '/app/var/dbg-wl5/vendor/autoload.php';
$app = require '/app/var/dbg-wl5/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$c = App\Models\Customer::create(['name' => 'Kim']);
$i = App\Models\Invoice::create(['customer_id' => $c->id, 'number' => 'X']);
$i->load('customer');
try {
    $res = (new App\Http\Resources\InvoiceResource($i))->toArray(new Illuminate\Http\Request());
    var_export($res);
} catch (Throwable $e) {
    echo 'THROWN: ' . get_class($e) . ': ' . $e->getMessage();
}
