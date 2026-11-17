<?php

// Debug probe: full HTTP round trip for the when-loaded scenario.
// Lives outside scenarios/; not part of any scenario.

require '/app/var/dbg-wl5/vendor/autoload.php';
$app = require '/app/var/dbg-wl5/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$c = App\Models\Customer::create(['name' => 'Kim']);
$i = App\Models\Invoice::create(['customer_id' => $c->id, 'number' => 'X']);
$response = $kernel->handle(Illuminate\Http\Request::create('/invoices/' . $i->id . '?include=customer', 'GET'));
echo 'STATUS=' . $response->getStatusCode() . "\n";
echo 'BODY=' . $response->getContent() . "\n";
