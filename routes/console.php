<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('tasks:send-due-reminders')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

/**
 * Schedule the migrate:fresh --seed command to run daily at 3:00 AM Eastern
 * (America/New_York, the application timezone).
 * This is for the demo environment to ensure the database is always up to date.
 * This command is not run in production.
 */
Schedule::command('migrate:fresh --seed')->dailyAt('03:00')->environments(['staging']);
