<?php

use Illuminate\Support\Facades\Route;

// API documentation (Swagger UI over docs/openapi.yaml)
Route::view('/api/docs', 'docs');
Route::get('/api/docs/openapi.yaml', fn () => response()->file(base_path('docs/openapi.yaml'), [
    'Content-Type' => 'application/yaml',
    'Cache-Control' => 'no-store',
]));

// Everything else is the React single-page app.
Route::view('/{any?}', 'app')->where('any', '^(?!api).*$');
