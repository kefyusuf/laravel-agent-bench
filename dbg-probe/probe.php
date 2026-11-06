<?php

// Debug probe: hit the throttled endpoint 5 times against a booted app and
// print statuses. Lives outside scenarios/; not part of any scenario.

require '/app/var/dbg-digest/vendor/autoload.php';
$app = require '/app/var/dbg-digest/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

for ($i = 1; $i <= 5; $i++) {
    $req = Illuminate\Http\Request::create('/digest/7/send', 'POST');
    $res = $kernel->handle($req);
    echo "hit{$i}=" . $res->getStatusCode() . PHP_EOL;
    $kernel->terminate($req, $res);
}
