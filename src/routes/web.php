<?php

declare(strict_types=1);

/*
 |--------------------------------------------------------------------------
 | RMS Yaskawa - Web Routes
 |--------------------------------------------------------------------------
 | URLs: /rms/...
 | Controller namespace: App\Http\Controllers\Web
 | All dashboard pages require an authenticated Laravel session.
 | Blade templates live in resources/views/rms/... (not yet supplied).
 */

use App\Http\Controllers\Web\AlarmController;
use App\Http\Controllers\Web\AnalyticsController;
use App\Http\Controllers\Web\ConnectionController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\FeatureController;
use App\Http\Controllers\Web\HardwareController;
use App\Http\Controllers\Web\MaintenanceController;
use App\Http\Controllers\Web\NotificationController;
use App\Http\Controllers\Web\RecommendationController;
use App\Http\Controllers\Web\RobotController;
use Illuminate\Support\Facades\Route;

// Replace the default Laravel welcome-page route with the RMS entry point.
Route::redirect('/', '/rms')->name('home');

Route::middleware(['auth'])
    ->prefix('rms')
    ->name('rms.')
    ->group(function (): void {
        // Overview
        Route::get('/', [DashboardController::class, 'index'])
            ->name('overview');

        // Robot monitoring (numeric database ID for implicit model binding)
        Route::get('/robots', [RobotController::class, 'index'])
            ->name('robots.index');
        Route::get('/robots/{robot}', [RobotController::class, 'show'])
            ->whereNumber('robot')
            ->name('robots.show');

        // Alarm & maintenance
        Route::get('/alarms', [AlarmController::class, 'index'])
            ->name('alarms.index');
        Route::get('/maintenance', [MaintenanceController::class, 'index'])
            ->name('maintenance.index');
        Route::get('/recommendations', [RecommendationController::class, 'index'])
            ->name('recommendations.index');

        // Metrics & connectivity
        Route::get('/analytics', [AnalyticsController::class, 'index'])
            ->name('analytics.index');
        Route::get('/connections', [ConnectionController::class, 'index'])
            ->name('connections.index');

        // Supporting pages
        Route::get('/notifications', [NotificationController::class, 'index'])
            ->name('notifications.index');
        Route::get('/hardware', [HardwareController::class, 'index'])
            ->name('hardware.index');
        Route::get('/features', [FeatureController::class, 'index'])
            ->name('features.index');
    });
