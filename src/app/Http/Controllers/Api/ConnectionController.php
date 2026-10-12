<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\Robot;
use App\Models\RobotConnectionEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConnectionController extends BaseRmsController
{
    public function index(Request $request, Robot $robot): JsonResponse
    {
        $filters = $this->queryPeriod($request);
        $query = $robot->connectionEvents();
        $this->applyPeriod($query->getQuery(), $filters, 'occurred_at');
        return response()->json($query->orderByDesc('occurred_at')->paginate($filters['per_page'] ?? 25));
    }

    /** Append event only; no robot-motion/servo control. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'robot_id' => ['required', 'integer', 'exists:robots,id'],
            'state' => ['required', 'in:connected,offline,reconnecting,stopped'],
            'message' => ['nullable', 'string'],
            'ip_address' => ['nullable', 'ip'],
            'occurred_at' => ['required', 'date'],
            'raw_payload' => ['nullable', 'array'],
        ]);
        $event = DB::transaction(function () use ($data) {
            $robot = Robot::query()->with('controller')->findOrFail($data['robot_id']);
            $event = RobotConnectionEvent::create($data);
            $controller = $robot->controller;
            // Avoid backdated replay overriding newer controller state.
            if ($controller && ($controller->last_seen_at === null
                || $event->occurred_at->greaterThanOrEqualTo($controller->last_seen_at))) {
                $controller->update([
                    'connection_status' => $event->state,
                    ...($event->state === 'connected' ? ['last_seen_at' => $event->occurred_at] : []),
                ]);
            }
            return $event;
        });
        return response()->json(['data' => $event], 201);
    }
}
