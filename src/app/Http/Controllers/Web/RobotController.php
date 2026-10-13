<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Models\Robot;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class RobotController extends BaseRmsWebController
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'connectivity' => ['sometimes', 'in:all,connected,offline,stale,unknown'],
        ]);
        $filter = $validated['connectivity'] ?? 'all';
        $rows = $this->robotCards($this->robotRegistry());
        if ($filter !== 'all') {
            $rows = $rows->where('connectivity', $filter)->values();
        }

        return view('rms.robots.index', [
            'title' => 'Robot Monitor',
            'robots' => $rows,
            'connectivityFilter' => $filter,
            'dataSource' => 'postgresql',
        ]);
    }

    /** Implicit binding: {robot} maps to numeric robots.id by default. */
    public function show(Robot $robot): View
    {
        $robot->load('controller');
        $card = $this->robotCards(new \Illuminate\Database\Eloquent\Collection([$robot]))->first();

        // Bound chart size to prevent pulling millions of 1-second samples.
        $history = $robot->telemetrySamples()
            ->orderByDesc('sampled_at')
            ->limit(120)
            ->get()
            ->reverse()
            ->values();

        return view('rms.robots.show', [
            'title' => $robot->name,
            'robot' => $robot,
            'card' => $card,
            'telemetryHistory' => $history,
            'operatingTimeHistory' => $robot->operatingTimeSamples()
                ->orderByDesc('sampled_at')->limit(25)->get(),
            'alarmSnapshots' => $robot->alarmSnapshots()
                ->orderByDesc('sampled_at')->limit(25)->get(),
            'alarmEvents' => $robot->alarmEvents()
                ->orderByDesc('started_at')->limit(25)->get(),
            'connectionEvents' => $robot->connectionEvents()
                ->orderByDesc('occurred_at')->limit(25)->get(),
            'latestHealthAssessment' => $robot->healthAssessments()
                ->orderByDesc('evaluated_at')->first(),
            'dataSource' => 'postgresql',
            'notAvailable' => ['job_monitoring', 'io_status', 'motion_state', 'hardware_life'],
        ]);
    }
}
