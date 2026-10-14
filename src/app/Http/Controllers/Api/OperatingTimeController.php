<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\Robot;
use App\Models\RobotOperatingTimeSample;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OperatingTimeController extends BaseRmsController
{
    public function index(Request $request, Robot $robot): JsonResponse
    {
        $filters = $this->queryPeriod($request);
        $types = $request->validate(['time_type' => ['sometimes', 'string', 'max:80']]);
        $query = $robot->operatingTimeSamples();
        if (!empty($types['time_type'])) $query->where('time_type', $types['time_type']);
        $this->applyPeriod($query->getQuery(), $filters, 'sampled_at');
        return response()->json($query->orderByDesc('sampled_at')->paginate($filters['per_page'] ?? 25));
    }

    public function latest(Robot $robot): JsonResponse
    {
        return response()->json(['data' => $robot->operatingTimeSamples()
            ->where('time_type', 'ServoPowerOnTimeRobot')
            ->orderByDesc('sampled_at')->first()]);
    }

    /** value_seconds must be supplied ONLY when SDK unit is independently verified. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'robot_id' => ['required', 'integer', 'exists:robots,id'],
            'time_type' => ['required', 'string', 'max:80'],
            'sampled_at' => ['required', 'date'],
            'read_ok' => ['required', 'boolean'],
            'status_code' => ['nullable', 'integer'],
            'status_message' => ['nullable', 'string'],
            'value_raw' => ['nullable', 'string'],
            'value_seconds' => ['nullable', 'integer', 'min:0'],
            'raw_payload' => ['nullable', 'array'],
        ]);
        if (!$data['read_ok']) {
            $data['value_raw'] = null;
            $data['value_seconds'] = null;
        }
        $key = [
            'robot_id' => $data['robot_id'],
            'time_type' => $data['time_type'],
            'sampled_at' => $data['sampled_at'],
        ];
        $sample = RobotOperatingTimeSample::firstOrCreate($key, $data);
        return response()->json(['data' => $sample, 'created' => $sample->wasRecentlyCreated], $sample->wasRecentlyCreated ? 201 : 200);
    }
}
