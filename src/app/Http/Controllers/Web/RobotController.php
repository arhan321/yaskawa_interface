<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Models\Robot;
use App\Models\RobotController as ControllerModel;
use App\Services\Rms\RobotReachabilityProbe;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class RobotController extends BaseRmsWebController
{
    public function create(): View
    {
        return view('rms.robots.create', [
            'title' => 'Tambah Robot',
            'probePorts' => config('rms.robot_probe.udp_ports', [10040, 10041]),
        ]);
    }

    /** A UI preflight only; store() always probes again server-side. */
    public function probe(Request $request, RobotReachabilityProbe $probe): JsonResponse
    {
        $data = $request->validate(['ip_address' => ['required', 'ip', 'ipv4']]);
        $result = $probe->probe($data['ip_address']);

        return response()->json($result, $result['ok'] ? 200 : 422)
            ->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request, RobotReachabilityProbe $probe): RedirectResponse
    {
        $data = $request->validate([
            'controller_code' => ['required', 'string', 'max:80', 'unique:robot_controllers,controller_code'],
            'controller_model' => ['required', 'string', 'max:80'],
            'ip_address' => ['required', 'ip', 'ipv4', 'unique:robot_controllers,ip_address'],
            'location' => ['nullable', 'string', 'max:150'],
            'robot_code' => ['required', 'string', 'max:80', 'unique:robots,robot_code'],
            'name' => ['required', 'string', 'max:150'],
            'model' => ['required', 'string', 'max:80'],
            'control_group' => ['required', 'string', 'max:8', 'regex:/^R[1-9][0-9]*$/i'],
            'axis_count' => ['required', 'integer', 'between:1,16'],
            'line_name' => ['nullable', 'string', 'max:100'],
            'station_name' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $probeResult = $probe->probe($data['ip_address']);
        if (! $probeResult['ok']) {
            return back()->withErrors(['ip_address' => $probeResult['message']])->withInput();
        }

        $data['controller_code'] = strtoupper(trim($data['controller_code']));
        $data['robot_code'] = strtoupper(trim($data['robot_code']));
        $data['control_group'] = strtoupper(trim($data['control_group']));

        if (ControllerModel::query()->whereRaw('LOWER(controller_code) = LOWER(?)', [$data['controller_code']])->exists()) {
            return back()->withErrors(['controller_code' => 'Controller code tersebut sudah terdaftar.'])->withInput();
        }
        if (ControllerModel::query()->where('ip_address', $data['ip_address'])->exists()) {
            return back()->withErrors(['ip_address' => 'IP tersebut sudah terdaftar pada controller lain.'])->withInput();
        }
        if (Robot::query()->whereRaw('LOWER(robot_code) = LOWER(?)', [$data['robot_code']])->exists()) {
            return back()->withErrors(['robot_code' => 'Robot code tersebut sudah terdaftar.'])->withInput();
        }

        DB::transaction(function () use ($data, $probeResult): void {
            $controller = ControllerModel::create([
                'controller_code' => $data['controller_code'],
                'model' => $data['controller_model'],
                'ip_address' => $data['ip_address'],
                'location' => $data['location'] ?? null,
                'connection_status' => 'unknown',
                'network_verified_at' => now(),
                'network_verification_status' => $probeResult['status'],
                'network_verification_method' => $probeResult['method'],
                'network_verification_message' => $probeResult['message'],
                'network_verification_latency_ms' => $probeResult['latency_ms'],
                'notes' => $data['notes'] ?? null,
            ]);

            Robot::create([
                'controller_id' => $controller->id,
                'robot_code' => $data['robot_code'],
                'name' => $data['name'],
                'model' => $data['model'],
                'control_group' => $data['control_group'],
                'axis_count' => $data['axis_count'],
                'line_name' => $data['line_name'] ?? null,
                'station_name' => $data['station_name'] ?? null,
                'is_active' => true,
            ]);
        });

        return redirect()->route('rms.robots.index')
            ->with('success', 'Robot berhasil didaftarkan setelah IP merespons dari server Laravel. Collector YMConnect tetap wajib melakukan verifikasi controller saat mulai ingest.');
    }

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
            'success' => session('success'),
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
