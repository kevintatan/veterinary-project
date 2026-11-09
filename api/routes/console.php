<?php

use Illuminate\Support\Facades\Schedule;

// Times in the business timezone; data is stored in UTC.
Schedule::command('subscriptions:expire-trials')->dailyAt('00:10')->timezone('America/Bogota');
Schedule::command('subscriptions:check-periods')->dailyAt('00:15')->timezone('America/Bogota');

// The lock is also computed on read (ClinicalRecord::isLocked), this only persists it.
Schedule::command('clinical-records:lock')->hourly();

// Automatic reminders (Fase 4c): queue what is due; the queue worker sends it.
Schedule::command('reminders:dispatch')->everyMinute()->withoutOverlapping();
Schedule::command('inventory:alerts')->dailyAt('07:00')->timezone('America/Bogota');
