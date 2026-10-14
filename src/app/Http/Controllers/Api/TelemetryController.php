<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\Robot;
use App\Models\RobotTelemetrySample;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Read historical samples; accepts normalized internal collector payloads only. */
class TelemetryController extends BaseRmsController
{
    public function index(Request $request, Robot $robot): JsonResponse
    {
        $filters = $this->queryPeriod($request);
        $query = $this->applyPeriod(
            $robot->telemetrySamples()->getQuery(), $filters, 'sampled_at'
        )->orderByDesc('sampled_at');
        return response()->json($query->paginate($filters['per_page'] ?? 50));
    }

    public function latest(Robot $robot): JsonResponse
    {
        return response()->json([
            'data' => $robot->telemetrySamples()->orderByDesc('sampled_at')->first(),
        ]);
    }

    /** For a later, authenticated internal ingestion endpoint (NOT public dashboard write). */
    public function store(Request $request): JsonResponse
    {
        $rules = [
            'robot_id' => ['required', 'integer', 'exists:robots,id'],
            'collector_session_id' => ['nullable', 'uuid'],
            'sequence' => ['nullable', 'integer', 'min:0'],
            'collector_uptime_seconds' => ['nullable', 'integer', 'min:0'],
            'sampled_at' => ['required', 'date'],
            'position_ok' => ['required', 'boolean'],
            'position_status_code' => ['nullable', 'integer'],
            'position_status_message' => ['nullable', 'string'],
            'position_sampled_at' => ['nullable', 'date'],
            'position_pulses' => ['nullable', 'array:S,L,U,R,B,T'],
            'torque_ok' => ['required', 'boolean'],
            'torque_status_code' => ['nullable', 'integer'],
            'torque_status_message' => ['nullable', 'string'],
            'torque_sampled_at' => ['nullable', 'date'],
            'torque_unit' => ['sometimes', 'in:sdk_raw'],
            'torque_axes' => ['nullable', 'array:S,L,U,R,B,T'],
            'raw_payload' => ['nullable', 'array'],
        ];
        foreach (['S', 'L', 'U', 'R', 'B', 'T'] as $axis) {
            $rules['position_pulses.'.$axis] = ['sometimes', 'nullable', 'numeric'];
            $rules['torque_axes.'.$axis] = ['sometimes', 'nullable', 'numeric'];
        }
        $data = $request->validate($rules);
        if (($data['position_ok'] ?? false) && count($data['position_pulses'] ?? []) !== 6) {
            return response()->json(['message' => 'Six SLURBT position_pulses values are required when position_ok=true'], 422);
        }
        if (($data['torque_ok'] ?? false) && count($data['torque_axes'] ?? []) !== 6) {
            return response()->json(['message' => 'Six SLURBT torque_axes values are required when torque_ok=true'], 422);
        }
        // Never store stale position/torque as successful values.
        if (!$data['position_ok']) $data['position_pulses'] = null;
        if (!$data['torque_ok']) $data['torque_axes'] = null;
        $data['torque_unit'] = 'sdk_raw';

        // Session ID + sequence together are an idempotency key; neither alone is sufficient.
        if (!empty($data['collector_session_id']) && array_key_exists('sequence', $data) && $data['sequence'] !== null) {
            $key = [
                'robot_id' => $data['robot_id'],
                'collector_session_id' => $data['collector_session_id'],
                'sequence' => $data['sequence'],
            ];
            $sample = RobotTelemetrySample::firstOrCreate($key, $data);
            return response()->json(['data' => $sample, 'created' => $sample->wasRecentlyCreated], $sample->wasRecentlyCreated ? 201 : 200);
        }
        return response()->json(['data' => RobotTelemetrySample::create($data), 'created' => true], 201);
    }
}
