<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Models\Robot;
use App\Models\RobotAlarmEvent;
use App\Models\RobotAlarmSnapshot;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AlarmController extends BaseRmsWebController
{
    public function index(Request $request): View
    {
        $filter = $request->validate([
            'robot_id' => ['nullable', 'integer', 'exists:robots,id'],
            'status' => ['nullable', 'in:all,active,cleared,unknown'],
            'severity' => ['nullable', 'string', 'max:32'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $query = RobotAlarmEvent::query()->with('robot');
        if (!empty($filter['robot_id'])) {
            $query->where('robot_id', $filter['robot_id']);
        }
        if (!empty($filter['status']) && $filter['status'] !== 'all') {
            $query->where('status', $filter['status']);
        }
        if (!empty($filter['severity']) && $filter['severity'] !== 'all') {
            $query->where('severity', $filter['severity']);
        }
        if (!empty($filter['search'])) {
            $needle = '%' . addcslashes($filter['search'], '%_\\') . '%';
            $query->where(static function ($q) use ($needle): void {
                $q->where('alarm_code', 'ILIKE', $needle)
                    ->orWhere('alarm_name', 'ILIKE', $needle);
            });
        }

        $snapshotQuery = RobotAlarmSnapshot::query()->with('robot');
        if (!empty($filter['robot_id'])) {
            $snapshotQuery->where('robot_id', $filter['robot_id']);
        }

        return view('rms.alarms.index', [
            'title' => 'Alarm Center',
            'robots' => Robot::query()->orderBy('robot_code')->get(['id', 'robot_code', 'name']),
            'events' => $query->orderByDesc('started_at')->paginate(20, ['*'], 'event_page')->withQueryString(),
            'snapshots' => $snapshotQuery->orderByDesc('sampled_at')->limit(30)->get(),
            'activeEventCount' => RobotAlarmEvent::query()->where('status', 'active')->count(),
            'filter' => $filter,
            'parsingNote' => 'SDK raw alarm snapshots do not imply structured/verified alarm events.',
        ]);
    }
}
