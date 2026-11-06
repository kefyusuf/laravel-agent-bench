<?php

namespace App\Console\Commands;

use App\Services\StockReservationService;
use Illuminate\Console\Command;

class ReserveCommand extends Command
{
    protected $signature = 'flashsale:reserve {sku} {iterations=1} {--gate= : Redis key to poll until the drop opens}';

    protected $description = 'Reserve up to N units of a flash-sale SKU (load-worker entrypoint)';

    public function handle(StockReservationService $reservations): int
    {
        $gate = $this->option('gate');

        if (is_string($gate) && $gate !== '') {
            // Flash sales open at a moment's notice: hold the worker until the
            // drop opens, then everyone hits the service at the same time.
            while ((string) $reservations->redis()->get($gate) !== 'go') {
                usleep(5000);
            }
        }

        $granted = 0;
        $iterations = max(1, (int) $this->argument('iterations'));

        for ($i = 0; $i < $iterations; $i++) {
            if ($reservations->reserve((string) $this->argument('sku'))) {
                $granted++;
            }
        }

        $this->line("granted={$granted}");

        return self::SUCCESS;
    }
}
