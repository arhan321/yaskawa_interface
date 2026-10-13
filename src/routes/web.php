<?php

/*
|--------------------------------------------------------------------------
| RMS YASKAWA - WEB ROUTES
|--------------------------------------------------------------------------
|
| Project  : Robot Management System (RMS)
| Laravel  : Laravel 13
| PHP      : PHP 8.5
| Database : PostgreSQL 16
| Server   : Nginx Docker - Port 8080
|
| Dashboard tanpa login
|
| Features:
| - Dashboard Overview
| - Robot Monitoring
| - Robot Detail
| - Robot Cockpit
| - Torque & Axis Health Monitoring
| - Threshold Visualization
| - Robot Log History
| - Multi-Robot & Shift Filter
| - CSV Export
| - Alarm Monitoring
| - Preventive Maintenance
| - Maintenance Recommendation
| - Data Analytics
| - Connection Monitoring
| - Notification Center
| - Hardware Insights
| - Feature Status
|
|--------------------------------------------------------------------------
*/

use Illuminate\Support\Facades\Route;

// ============================================================
// RMS WEB CONTROLLERS
// ============================================================

use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\RobotController;
use App\Http\Controllers\Web\RobotLogController;
use App\Http\Controllers\Web\CockpitController;
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

Route::redirect('/', '/rms')
    ->name('home');

// ============================================================
// 2. RMS DASHBOARD - NO LOGIN
// ============================================================

Route::prefix('rms')
    ->name('rms.')
    ->group(function (): void {

        // ====================================================
        // DASHBOARD OVERVIEW
        // ====================================================

        Route::get('/', [
            DashboardController::class,
            'index'
        ])->name('overview');

        // ====================================================
        // ROBOT MONITORING
        // ====================================================

        Route::get('/robots', [
            RobotController::class,
            'index'
        ])->name('robots.index');

        // ====================================================
        // ROBOT COCKPIT - YASKAWA EQUIPMENT HEALTH
        // ====================================================

        /*
         * Robot Cockpit includes:
         *
         * - Motor Torque Monitoring
         * - Axis S, L, U, R, B, T
         * - Torque History Graph
         * - Measurement & Variation
         * - Threshold Warning / Critical
         * - Axis Health Indicators
         * - Speed Reducer Condition
         * - Maintenance Health Information
         *
         * Read-only monitoring interface.
         */

        Route::get('/robots/{robot}/cockpit', [
            CockpitController::class,
            'show'
        ])
        ->whereNumber('robot')
        ->name('robots.cockpit');

        // ====================================================
        // ROBOT DETAIL
        // ====================================================

        Route::get('/robots/{robot}', [
            RobotController::class,
            'show'
        ])
        ->whereNumber('robot')
        ->name('robots.show');

        // ====================================================
        // ROBOT LOG HISTORY
        // ====================================================

        /*
         * Features:
         *
         * - All Robot Logs
         * - Multi-Robot Selection
         * - Robot ID Filtering
         * - Date Range Filtering
         * - Shift Filtering
         * - Custom Time Range
         * - Telemetry History
         * - Alarm History
         * - Connection History
         * - Maintenance History
         */

        Route::get('/logs', [
            RobotLogController::class,
            'index'
        ])->name('logs.index');

        // ====================================================
        // ROBOT LOG CSV EXPORT
        // ====================================================

        Route::get('/logs/export', [
            RobotLogController::class,
            'export'
        ])
        ->middleware('throttle:10,1')
        ->name('logs.export');

        // ====================================================
        // ALARM MONITORING
        // ====================================================

        Route::get('/alarms', [
            AlarmController::class,
            'index'
        ])->name('alarms.index');

        // ====================================================
        // PREVENTIVE MAINTENANCE
        // ====================================================

        Route::get('/maintenance', [
            MaintenanceController::class,
            'index'
        ])->name('maintenance.index');

        // ====================================================
        // MAINTENANCE RECOMMENDATION
        // ====================================================

        Route::get('/recommendations', [
            RecommendationController::class,
            'index'
        ])->name('recommendations.index');

        // ====================================================
        // DATA ANALYTICS
        // ====================================================

        Route::get('/analytics', [
            AnalyticsController::class,
            'index'
        ])->name('analytics.index');

        // ====================================================
        // CONNECTION MONITORING
        // ====================================================

        Route::get('/connections', [
            ConnectionController::class,
            'index'
        ])->name('connections.index');

        // ====================================================
        // NOTIFICATION CENTER
        // ====================================================

        Route::get('/notifications', [
            NotificationController::class,
            'index'
        ])->name('notifications.index');

        // ====================================================
        // HARDWARE INSIGHTS
        // ====================================================

        Route::get('/hardware', [
            HardwareController::class,
            'index'
        ])->name('hardware.index');

        // ====================================================
        // FEATURE STATUS
        // ====================================================

        Route::get('/features', [
            FeatureController::class,
            'index'
        ])->name('features.index');

    });

// ============================================================
// END OF RMS WEB ROUTES
// ============================================================
