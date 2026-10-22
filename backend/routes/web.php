<?php

use Illuminate\Support\Facades\Route;

// El backend solo expone la API (/api/*). La interfaz vive en /frontend.
Route::get('/', fn () => response()->json(['app' => config('app.name'), 'api' => url('/api')]));
