<?php

use App\Jobs\DispatchOutboxEvents;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| VoteSys Scheduled Tasks
|--------------------------------------------------------------------------
|
| Run the Laravel scheduler via:
|   php artisan schedule:run   (called every minute by cron / Supervisor)
|
| Supervisor worker command example:
|   php artisan queue:work redis --queue=votesys-events,votesys-voting,
|       votesys-notifications,votesys-analytics,votesys-elections
|       --tries=3 --timeout=60
|
*/

// Dispatch pending outbox events to the DEORIS Event Hub every minute.
Schedule::job(new DispatchOutboxEvents)
    ->everyMinute()
    ->name('votesys:dispatch-outbox')
    ->withoutOverlapping();
