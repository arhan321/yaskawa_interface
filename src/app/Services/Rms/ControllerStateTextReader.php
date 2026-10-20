<?php

declare(strict_types=1);

namespace App\Services\Rms;

use App\Models\Robot;
use App\Models\RobotControllerStateSample;

/** Display evidence from the structured collector state, never a safety-rated status. */
final class ControllerStateTextReader
{
    public function latest(Robot $robot): ?array
    {
        $attempt = $robot->telemetrySamples()
            ->where('received_at', '>=', now()->subSeconds(30))
            ->where('sampled_at', '>=', now()->subSeconds(60))
            ->orderByDesc('received_at')->orderByDesc('id')->limit(8)->get()
            ->first(static function ($sample): bool {
                $read = data_get($sample->raw_payload, 'state_read');

                return is_array($read) && (($read['read_ok'] ?? false) === true
                    || ($read['message'] ?? 'unavailable') !== 'unavailable');
            });
        if ($attempt === null || data_get($attempt->raw_payload, 'simulated') === true
            || data_get($attempt->raw_payload, 'state_read.read_ok') !== true) {
            return null;
        }
        $state = RobotControllerStateSample::query()->where('telemetry_sample_id', $attempt->id)->first();
        if ($state === null) {
            return null;
        }

        return [
            'source' => 'ymconnect_structured_state',
            'sampled_at' => $state->sampled_at?->toIso8601String(),
            'control_mode' => $state->control_mode,
            'cycle_mode' => $state->cycle_mode,
            'running' => $state->running,
            'servo_on' => $state->servo_on,
            'alarming' => $state->alarm,
            'hold' => $state->hold,
            'error' => $state->error,
            'verified' => false,
        ];
    }
}
