<?php


use Illuminate\Support\Facades\Route;

// ============================================================
// RMS WEB CONTROLLERS
// ============================================================

use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\RobotController;
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

// Direct redirect to RMS dashboard
Route::redirect('/', '/rms')->name('home');

// ============================================================
// 2. RMS DASHBOARD - NO AUTHENTICATION
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
        // ROBOT DETAIL
        // ====================================================

        Route::get('/robots/{robot}', [
            RobotController::class,
            'show'
        ])
        ->whereNumber('robot')
        ->name('robots.show');

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
        // ANALYTICS
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
