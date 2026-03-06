<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('otp:purge')->daily();
