<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Models\MaintenanceLog;
use App\Models\MaintenancePlan;
use App\Models\Robot;
use App\Models\RobotOperatingTimeSample;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class MaintenanceController extends BaseRmsWebController
{
    public function index(Request $request): View
    {
        $filter = $request->validate([
            'robot_id' => ['nullable', 'integer', 'exists:robots,id'],
            'active' => ['nullable', 'in:all,yes,no'],
        ]);

        $plansQuery = MaintenancePlan::query()->with('robot');
        $logsQuery = MaintenanceLog::query()->with(['robot', 'maintenancePlan']);
        if (!empty($filter['robot_id'])) {
            $plansQuery->where('robot_id', $filter['robot_id']);
            $logsQuery->where('robot_id', $filter['robot_id']);
        }
        if (($filter['active'] ?? 'all') === 'yes') {
            $plansQuery->where('is_active', true);
        } elseif (($filter['active'] ?? 'all') === 'no') {
            $plansQuery->where('is_active', false);
        }

        $plans = $plansQuery->orderBy('next_due_at')->orderBy('id')
            ->paginate(20, ['*'], 'plan_page')->withQueryString();
        $logs = $logsQuery->orderByDesc('created_at')
            ->paginate(20, ['*'], 'log_page')->withQueryString();

        $timeSamples = $this->latestPerRobot(
            RobotOperatingTimeSample::class,
            collect($plans->items())->pluck('robot_id')->unique()->values(),
            'sampled_at',
            ['time_type' => 'ServoPowerOnTimeRobot']
        );

        $dueStatuses = [];
        foreach ($plans->items() as $plan) {
            $time = $timeSamples->get($plan->robot_id);
            $verifiedHours = $time !== null && $time->read_ok && $time->value_seconds !== null
                ? $time->value_seconds / 3600
                : null;
            $dueByHours = $plan->next_due_operating_hours !== null && $verifiedHours !== null
                ? $verifiedHours >= (float) $plan->next_due_operating_hours
                : null;
            $dueByDate = $plan->next_due_at !== null
                ? $plan->next_due_at->lessThanOrEqualTo(now())
                : null;
            $dueStatuses[$plan->id] = [
                'due_by_hours' => $dueByHours,
                'due_by_date' => $dueByDate,
                'verified_operating_hours' => $verifiedHours,
                'due' => $plan->is_active && ($dueByHours === true || $dueByDate === true),
            ];
        }

        return view('rms.maintenance.index', [
            'title' => 'Preventive Maintenance',
            'robots' => Robot::query()->orderBy('robot_code')->get(['id', 'robot_code', 'name']),
            'plans' => $plans,
            'logs' => $logs,
            'dueStatuses' => $dueStatuses,
            'filter' => $filter,
            'note' => 'Maintenance intervals are entered by staff; unverified SDK operating-time units are not converted.',
        ]);
    }
}
