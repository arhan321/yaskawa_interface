<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\Robot;
use App\Models\RobotAlarmEvent;
use App\Models\RobotAlarmSnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlarmController extends BaseRmsController
{
    /** Structured alarm events exist only once a trusted parser has identified them. */
    public function events(Request $request): JsonResponse
    {
        $filters = $this->queryPeriod($request);
        $other = $request->validate([
            'robot_id' => ['sometimes', 'integer', 'exists:robots,id'],
            'status' => ['sometimes', 'in:active,cleared,unknown'],
            'severity' => ['sometimes', 'string', 'max:32'],
        ]);
        $query = RobotAlarmEvent::query()->with('robot:id,robot_code,name');
        if (!empty($other['robot_id'])) $query->where('robot_id', $other['robot_id']);
        if (!empty($other['status'])) $query->where('status', $other['status']);
        if (!empty($other['severity'])) $query->where('severity', $other['severity']);
        $this->applyPeriod($query, $filters, 'started_at');
        return response()->json($query->orderByDesc('started_at')->paginate($filters['per_page'] ?? 25));
    }

    public function snapshots(Request $request, Robot $robot): JsonResponse
    {
        $filters = $this->queryPeriod($request);
        $query = $robot->alarmSnapshots();
        $this->applyPeriod($query->getQuery(), $filters, 'sampled_at');
        return response()->json($query->orderByDesc('sampled_at')->paginate($filters['per_page'] ?? 25));
    }

    public function latestSnapshot(Robot $robot): JsonResponse
    {
        return response()->json(['data' => $robot->alarmSnapshots()->orderByDesc('sampled_at')->first()]);
    }

    /** Do not infer active_alarm_count=0 from empty/failed sdk_text. */
    public function storeSnapshot(Request $request): JsonResponse
    {
        $data = $request->validate([
            'robot_id' => ['required', 'integer', 'exists:robots,id'],
            'sampled_at' => ['required', 'date'],
            'read_ok' => ['required', 'boolean'],
            'status_code' => ['nullable', 'integer'],
            'status_message' => ['nullable', 'string'],
            'sdk_text' => ['nullable', 'string'],
            'active_alarm_count' => ['nullable', 'integer', 'min:0'],
            'parsed_alarms' => ['nullable', 'array'],
        ]);
        if (!$data['read_ok']) {
            $data['sdk_text'] = null;
            $data['active_alarm_count'] = null;
            $data['parsed_alarms'] = null;
        }
        $key = ['robot_id' => $data['robot_id'], 'sampled_at' => $data['sampled_at']];
        $snapshot = RobotAlarmSnapshot::firstOrCreate($key, $data);
        return response()->json(['data' => $snapshot, 'created' => $snapshot->wasRecentlyCreated], $snapshot->wasRecentlyCreated ? 201 : 200);
    }
}
