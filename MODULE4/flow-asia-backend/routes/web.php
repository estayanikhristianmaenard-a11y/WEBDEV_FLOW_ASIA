<?php

use Illuminate\Support\Facades\Route;

if (config('module4.mode') === 'standalone') {
    Route::redirect('/', '/m4');
}
