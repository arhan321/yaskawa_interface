<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| RMS YASKAWA - WEB ROUTES
|--------------------------------------------------------------------------
| Laravel 13 | PHP 8.5 | PostgreSQL 16
| Dashboard tanpa login
| Robot Logs + Shift Filter + CSV Export
|--------------------------------------------------------------------------
*/

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\RobotController;
use App\Http\Controllers\Web\RobotLogController;
use App\Http\Controllers\Web\AlarmController;
use App\Http\Controllers\Web\MaintenanceController;
use App\Http\Controllers\Web\RecommendationController;
use App\Http\Controllers\Web\AnalyticsController;
use App\Http\Controllers\Web\ConnectionController;
use App\Http\Controllers\Web\NotificationController;
use App\Http\Controllers\Web\HardwareController;
use App\Http\Controllers\Web\FeatureController;

// ============================================================
// 1. HOME
// ============================================================

Route::redirect('/', '/rms')->name('home');

// ============================================================
// 2. RMS DASHBOARD - NO LOGIN
// ============================================================

Route::prefix('rms')
    ->name('rms.')
    ->group(function (): void {

        // DASHBOARD OVERVIEW
        Route::get('/', [
            DashboardController::class, 'index'
        ])->name('overview');

        // ROBOT MONITORING
        Route::get('/robots', [
            RobotController::class, 'index'
        ])->name('robots.index');

        // ROBOT DETAIL
        Route::get('/robots/{robot}', [
            RobotController::class, 'show'
        ])
        ->whereNumber('robot')
        ->name('robots.show');

        // ====================================================
        // ROBOT LOG HISTORY - NEW FEATURE
        // ====================================================

        // Show all robot logs with filters
        Route::get('/logs', [
            RobotLogController::class, 'index'
        ])->name('logs.index');

        // Export filtered logs to CSV
        Route::get('/logs/export', [
            RobotLogController::class, 'export'
        ])
        ->middleware('throttle:10,1')
        ->name('logs.export');

        // ====================================================
        // ALARM MONITORING
        // ====================================================

        Route::get('/alarms', [
            AlarmController::class, 'index'
        ])->name('alarms.index');

        // PREVENTIVE MAINTENANCE
        Route::get('/maintenance', [
            MaintenanceController::class, 'index'
        ])->name('maintenance.index');

        // MAINTENANCE RECOMMENDATION
        Route::get('/recommendations', [
            RecommendationController::class, 'index'
        ])->name('recommendations.index');

        // ANALYTICS
        Route::get('/analytics', [
            AnalyticsController::class, 'index'
        ])->name('analytics.index');

        // CONNECTION MONITORING
        Route::get('/connections', [
            ConnectionController::class, 'index'
        ])->name('connections.index');

        // NOTIFICATION CENTER
        Route::get('/notifications', [
            NotificationController::class, 'index'
        ])->name('notifications.index');

        // HARDWARE INSIGHTS
        Route::get('/hardware', [
            HardwareController::class, 'index'
        ])->name('hardware.index');

        // FEATURE STATUS
        Route::get('/features', [
            FeatureController::class, 'index'
        ])->name('features.index');

    });
