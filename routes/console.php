<?php

use Illuminate\Console\Scheduling\Schedule;

/**
 * Schedule the migrate:fresh --seed command to run daily at 12:00 AM.
 * This is for the demo environment to ensure the database is always up to date.
 * This command is not run in production.
 */
Schedule::command('migrate:fresh --seed')->daily()->environments(['staging']);
