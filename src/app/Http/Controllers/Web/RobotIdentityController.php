<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Events\RmsRobotChanged;
use App\Models\Robot;
use App\Services\Rms\HomePositionMonitor;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Master data on the existing trusted LAN web flow. No controller writes. */
final class RobotIdentityController extends BaseRmsWebController
{
    public function edit(Robot $robot): View
    {
        $robot->load('controller');

        return view('rms.robots.identity', ['title' => 'Identitas — '.$robot->name,
            'robot' => $robot, 'axes' => HomePositionMonitor::AXES,
            'home' => data_get($robot->metadata, 'home_position', [])]);
    }

    public function update(Request $request, Robot $robot): RedirectResponse
    {
        $data = $request->validate([
            'serial_number' => ['required', 'string', 'max:120', Rule::unique('robots', 'serial_number')->ignore($robot->id)],
            'robot_type' => ['required', 'string', 'max:120'],
            'nameplate_date' => ['required', 'date_format:Y-m'],
        ]);
        $robot->update($data);
        $this->notify($robot, 'RobotIdentity');

        return back()->with('identity_success', 'Identitas robot berhasil disimpan.');
    }

    public function saveHome(Request $request, Robot $robot): RedirectResponse
    {
        $rules = [
            'home_axes' => ['required', 'array:S,L,U,R,B,T'],
            'tolerance_pulses' => ['required', 'integer', 'between:0,1000000'],
            'verified_by' => ['required', 'string', 'max:120'],
            'source_reference' => ['required', 'string', 'max:255'],
            'home_verified' => ['accepted'],
            'captured_sample_id' => ['nullable', 'integer', 'min:1'],
        ];
        foreach (HomePositionMonitor::AXES as $axis) {
            $rules['home_axes.'.$axis] = ['required', 'integer', 'between:-2147483648,2147483647'];
        }
        $data = $request->validate($rules);
        if (blank($robot->serial_number) || (int) $robot->axis_count !== 6) {
            throw ValidationException::withMessages(['home_axes' => 'Lengkapi serial number dan gunakan robot enam axis untuk referensi home ini.']);
        }
        $axes = array_map('intval', $data['home_axes']);
        if (! empty($data['captured_sample_id'])) {
            $sample = $robot->telemetrySamples()->find($data['captured_sample_id']);
            $recent = $sample?->received_at?->betweenIncluded(
                now()->subSeconds((int) config('rms.telemetry_stale_seconds', 30)), now()->addSeconds(5));
            if (! $recent || ! $sample?->sampled_at?->betweenIncluded(now()->subSeconds(60), now()->addSeconds(5)) || ! $sample?->position_ok || data_get($sample->raw_payload, 'simulated') === true
                || data_get($sample->raw_payload, 'group') !== $robot->control_group
                || ! in_array(data_get($sample->raw_payload, 'position_unit'), ['pulse', 'pulses'], true)) {
                throw ValidationException::withMessages(['captured_sample_id' => 'Posisi yang diambil sudah tidak segar/valid. Ambil ulang atau masukkan referensi pendant secara manual.']);
            }
            foreach (HomePositionMonitor::AXES as $axis) {
                if (! isset($sample->position_pulses[$axis]) || (float) $axes[$axis] !== (float) $sample->position_pulses[$axis]) {
                    throw ValidationException::withMessages(['home_axes' => 'Posisi hasil capture berubah. Ambil ulang atau gunakan input manual.']);
                }
            }
        }
        DB::transaction(function () use ($robot, $data, $axes): void {
            $locked = Robot::query()->lockForUpdate()->findOrFail($robot->id);
            if ($locked->serial_number !== $robot->serial_number || $locked->control_group !== $robot->control_group) {
                throw ValidationException::withMessages(['home_axes' => 'Identitas robot berubah. Muat ulang dan periksa kembali referensi home.']);
            }
            $metadata = $locked->metadata ?? [];
            $metadata['home_position'] = [
                'axes' => $axes, 'tolerance_pulses' => (int) $data['tolerance_pulses'],
                'group' => $locked->control_group, 'serial_number' => $locked->serial_number,
                'verified' => true, 'verified_by' => $data['verified_by'],
                'source_reference' => $data['source_reference'], 'verified_at' => now()->toIso8601String(),
                'captured_sample_id' => $data['captured_sample_id'] ?? null,
            ];
            $locked->update(['metadata' => $metadata]);
        });
        $this->notify($robot, 'HomePositionReference');

        return back()->with('identity_success', 'Referensi home position berhasil disimpan.');
    }

    private function notify(Robot $robot, string $source): void
    {
        if (! config('rms_live.enabled', false)) {
            return;
        }
        try {
            event(new RmsRobotChanged((int) $robot->id, $source));
        } catch (\Throwable $exception) {
            Log::warning('RMS identity live notification failed.', ['robot_id' => $robot->id,
                'exception' => $exception::class]);
        }
    }
}
