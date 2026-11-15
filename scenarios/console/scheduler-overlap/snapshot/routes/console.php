<?php

use App\Console\Commands\SyncMarketplaceCommand;
use Illuminate\Support\Facades\Schedule;

Schedule::command('app:sync-marketplace')
    ->hourly()
    ->withoutOverlapping(10)
    ->onOneServer();
