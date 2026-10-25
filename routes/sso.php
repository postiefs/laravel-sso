<?php

use Illuminate\Support\Facades\Route;
use Postiefs\Sso\Http\Controllers\CallbackController;
use Postiefs\Sso\Http\Controllers\EndedController;
use Postiefs\Sso\Http\Controllers\HeartbeatController;
use Postiefs\Sso\Http\Controllers\LoginController;
use Postiefs\Sso\Http\Controllers\LogoutController;
use Postiefs\Sso\Http\Middleware\EnsureSsoSession;

Route::middleware(config('postiefs-sso.routes.middleware', ['web']))
    ->prefix(config('postiefs-sso.routes.prefix', 'sso'))
    ->name('sso.')
    ->group(function () {
        Route::get('login', LoginController::class)->name('login');
        Route::get('callback', CallbackController::class)->name('callback');
        Route::get('ended', EndedController::class)->name('ended');
        Route::post('logout', LogoutController::class)->name('logout');
        Route::get('heartbeat', HeartbeatController::class)->middleware(EnsureSsoSession::class)->name('heartbeat');
    });
