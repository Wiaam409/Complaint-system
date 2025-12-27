<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Console\Scheduling\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
*/
Artisan::command('inspire', function () {
    $this->comment('Test scheduler');
});

app()->booted(function () {
    app(Schedule::class)
        ->command('backup:run')
        ->everyMinute()
        ->withoutOverlapping();
});
