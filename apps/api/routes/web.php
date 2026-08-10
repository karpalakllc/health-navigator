<?php

use Illuminate\Support\Facades\Route;

/*
 * This application serves the JSON API and the Filament admin panel; it has no
 * public web UI of its own. The root previously rendered the stock Laravel
 * welcome page, which is not something a production API host should answer with.
 */
Route::redirect('/', '/admin');
