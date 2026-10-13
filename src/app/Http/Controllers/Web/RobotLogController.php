<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceLog;
use App\Models\MaintenanceRecommendation;
use App\Models\Robot;
use App\Models\RobotAlarmEvent;
use App\Models\RobotAlarmSnapshot;
use App\Models\RobotConnectionEvent;
use App\Models\RobotHealthAssessment;
use App\Models\RobotOperatingTimeSample;
use App\Models\RobotTelemetrySample;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * RMS Yaskawa: read-only, cross-robot logs with WIB time filters.
 * Works with the original 11 RMS migrations/models. No robot commands.
 */
final class RobotLogController extends Controller
{
    private const TZ = 'Asia/Jakarta';
    private const PAGE_SIZES = [25, 50, 100];
    private const CSV_LIMIT = 50000;

    // Quick preset for user-selected important events (excluding high-frequency telemetry).
    private const IMPORTANT_EVENTS = ['alarm_event', 'connection', 'health', 'maintenance', 'recommendation'];

    // Expressions are hard-coded: never construct SQL identifiers from request input.
    private const SOURCES = [
        'telemetry' => [RobotTelemetrySample::class, 'robot_telemetry_samples', 'sampled_at'],
        'operating' => [RobotOperatingTimeSample::class, 'robot_operating_time_samples', 'sampled_at'],
        'alarm_snapshot' => [RobotAlarmSnapshot::class, 'robot_alarm_snapshots', 'sampled_at'],
        'alarm_event' => [RobotAlarmEvent::class, 'robot_alarm_events', 'started_at'],
        'connection' => [RobotConnectionEvent::class, 'robot_connection_events', 'occurred_at'],
        'health' => [RobotHealthAssessment::class, 'robot_health_assessments', 'evaluated_at'],
        // Completed_at is preferred; in-progress actions use started_at, otherwise created_at.
        'maintenance' => [MaintenanceLog::class, 'maintenance_logs', 'COALESCE(completed_at, started_at, created_at)'],
        'recommendation' => [MaintenanceRecommendation::class, 'maintenance_recommendations', 'generated_at'],
    ];

    // Avoid loading large collector raw_payload JSONB for every visible row.
    private const DETAIL_COLUMNS = [
        'telemetry' => ['id', 'sequence', 'position_ok', 'position_status_code', 'position_pulses', 'torque_ok', 'torque_status_code', 'torque_unit', 'torque_axes'],
        'operating' => ['id', 'time_type', 'read_ok', 'status_code', 'status_message', 'value_raw', 'value_seconds'],
        'alarm_snapshot' => ['id', 'read_ok', 'sdk_text', 'active_alarm_count', 'status_code', 'status_message'],
        'alarm_event' => ['id', 'status', 'alarm_code', 'alarm_name', 'severity', 'subcode', 'source', 'cleared_at', 'details'],
        'connection' => ['id', 'state', 'message', 'ip_address'],
        'health' => ['id', 'health_status', 'summary', 'rule_version', 'assessment_source', 'indicators'],
        'maintenance' => ['id', 'result', 'action_type', 'performed_by', 'findings', 'action_taken', 'started_at', 'completed_at', 'verified_by', 'verified_at'],
        'recommendation' => ['id', 'status', 'title', 'priority', 'recommendation_text', 'source', 'evidence'],
    ];

    public function index(Request $request)
    {
        $filters = $this->filters($request);
        // Cursor pagination handles a busy telemetry table better than large OFFSET pages.
        $page = $this->query($filters)
            ->cursorPaginate($filters['per_page'])
            ->withQueryString();

        return view('rms.logs.index', [
            'title' => 'Robot log history',
            'robots' => Robot::query()->orderBy('robot_code')->get(['id', 'robot_code', 'name']),
            'entries' => $this->hydrateRows(collect($page->items())),
            'page' => $page,
            'filters' => $filters,
            'types' => $this->types(),
            'exportUrl' => route('rms.logs.export', $this->queryParams($filters)),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $filename = 'rms-robot-logs-' . CarbonImmutable::now(self::TZ)->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($filters): void {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                return;
            }

            // UTF-8 BOM for Excel users (including Indonesian text).
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Waktu WIB', 'Robot', 'Tipe Log', 'Status', 'Ringkasan', 'Detail JSON']);

            $written = 0;
            $truncated = false;
            $source = $this->query($filters)->limit(self::CSV_LIMIT + 1)->cursor();
            foreach ($source->chunk(200) as $batch) {
                foreach ($this->hydrateRows(collect($batch)) as $entry) {
                    if ($written >= self::CSV_LIMIT) {
                        $truncated = true;
                        break 2;
                    }
                    fputcsv($out, [
                        $this->safeCsv($entry['time_wib']),
                        $this->safeCsv($entry['robot_code']),
                        $this->safeCsv($entry['type_label']),
                        $this->safeCsv($entry['status']),
                        $this->safeCsv($entry['summary']),
                        $this->safeCsv((string) json_encode($entry['details'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)),
                    ]);
                    ++$written;
                }
            }

            if ($truncated) {
                fputcsv($out, ['DIPOTONG', '', '', '',
                    'CSV dibatasi 50.000 baris. Persempit tanggal/jam atau pilih robot tertentu untuk memperoleh sisa log.', '']);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @return array<string, string> Individually selectable PostgreSQL log sources. */
    private function types(): array
    {
        return [
            'telemetry' => 'Telemetry (position & torque)',
            'alarm_event' => 'Alarm events',
            'alarm_snapshot' => 'Alarm snapshots (SDK)',
            'connection' => 'Connection events',
            'operating' => 'Operating time',
            'health' => 'Equipment health',
            'maintenance' => 'Maintenance journal',
            'recommendation' => 'Maintenance recommendation',
        ];
    }

    private function filters(Request $request): array
    {
        $v = $request->validate([
            // robot_ids[] is the new checkbox multi-select input.
            // robot_id is accepted for old links from the individual robot page.
            'robot_ids' => ['sometimes', 'array', 'max:500'],
            'robot_ids.*' => ['required', 'integer', 'distinct', Rule::exists('robots', 'id')],
            'robot_id' => ['nullable', 'integer', Rule::exists('robots', 'id')],
            // types[] is a multi-select. Empty/absent means all sources.
            // Legacy ?type=... links continue to work when types[] is absent.
            'types' => ['sometimes', 'array', 'max:8'],
            'types.*' => ['required', 'string', 'distinct', Rule::in(array_keys(self::SOURCES))],
            'type' => ['nullable', 'string', Rule::in(array_merge(['all', 'events'], array_keys(self::SOURCES)))],
            'preset' => ['nullable', Rule::in(['shift_pagi', 'full_day', 'custom'])],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'from' => ['nullable', 'date_format:Y-m-d\\TH:i'],
            'to' => ['nullable', 'date_format:Y-m-d\\TH:i'],
            'per_page' => ['nullable', 'integer', Rule::in(self::PAGE_SIZES)],
        ]);

        $preset = $v['preset'] ?? 'shift_pagi';
        $day = $v['date'] ?? CarbonImmutable::now(self::TZ)->format('Y-m-d');

        if ($preset === 'custom') {
            if (empty($v['from']) || empty($v['to'])) {
                throw ValidationException::withMessages([
                    'from' => 'Jam mulai dan jam selesai wajib diisi untuk rentang manual.',
                ]);
            }
            $fromLocal = CarbonImmutable::createFromFormat('!Y-m-d\\TH:i', $v['from'], self::TZ);
            $toLocal = CarbonImmutable::createFromFormat('!Y-m-d\\TH:i', $v['to'], self::TZ);
        } else {
            $fromLocal = CarbonImmutable::parse($day . ($preset === 'shift_pagi' ? ' 07:15:00' : ' 00:00:00'), self::TZ);
            $toLocal = CarbonImmutable::parse($day . ($preset === 'shift_pagi' ? ' 16:00:00' : ' 23:59:00'), self::TZ);
        }

        if ($fromLocal === false || $toLocal === false || $fromLocal->greaterThan($toLocal)) {
            throw ValidationException::withMessages(['to' => 'Jam selesai harus sama atau setelah jam mulai.']);
        }
        if (($toLocal->getTimestamp() - $fromLocal->getTimestamp()) > 7 * 86400) {
            throw ValidationException::withMessages(['to' => 'Rentang maksimal 7 hari per pencarian.']);
        }

        // Empty array = all robots (no WHERE IN restriction).
        // Preserve old ?robot_id=123 deep links when robot_ids[] is absent.
        $selectedIds = array_key_exists('robot_ids', $v)
            ? array_map('intval', $v['robot_ids'])
            : (isset($v['robot_id']) ? [(int) $v['robot_id']] : []);
        $selectedIds = array_values(array_unique($selectedIds));
        sort($selectedIds, SORT_NUMERIC);

        // Explicit multi-selection wins over legacy type= links.
        // No selection means all sources, consistent with the robot filter.
        if (array_key_exists('types', $v)) {
            $selectedTypes = $v['types'];
        } else {
            $selectedTypes = match ($v['type'] ?? 'all') {
                'all' => [],
                'events' => self::IMPORTANT_EVENTS,
                default => [$v['type']],
            };
        }

        // Deterministic order for URLs, selected chips, and CSV links.
        $selectedTypes = array_values(array_intersect(array_keys(self::SOURCES), $selectedTypes));

        return [
            'robot_ids' => $selectedIds,
            'types' => $selectedTypes,
            'preset' => $preset,
            'date' => $day,
            'from' => $fromLocal->format('Y-m-d\\TH:i'),
            'to' => $toLocal->format('Y-m-d\\TH:i'),
            // End time includes the whole selected minute (e.g. up to 16:00:59.999).
            'from_utc' => $fromLocal->utc()->toIso8601String(),
            'until_utc' => $toLocal->addMinute()->utc()->toIso8601String(),
            'per_page' => (int) ($v['per_page'] ?? 50),
        ];
    }

    private function queryParams(array $f): array
    {
        return array_filter([
            'robot_ids' => $f['robot_ids'] !== [] ? $f['robot_ids'] : null,
            'types' => $f['types'] !== [] ? $f['types'] : null,
            'preset' => $f['preset'],
            'date' => $f['date'],
            'from' => $f['preset'] === 'custom' ? $f['from'] : null,
            'to' => $f['preset'] === 'custom' ? $f['to'] : null,
            'per_page' => $f['per_page'],
        ], static fn ($value) => $value !== null && $value !== '');
    }

    private function query(array $f): Builder
    {
        $selected = $f['types'] === [] ? array_keys(self::SOURCES) : $f['types'];

        $combined = null;
        foreach ($selected as $type) {
            [, $table, $timestamp] = self::SOURCES[$type];
            $part = DB::table($table)
                ->selectRaw("id AS record_id, robot_id, ($timestamp AT TIME ZONE 'UTC') AS logged_at_utc, '$type'::text AS log_type")
                ->whereRaw("$timestamp >= ? AND $timestamp < ?", [$f['from_utc'], $f['until_utc']]);
            if ($f['robot_ids'] !== []) {
                $part->whereIn('robot_id', $f['robot_ids']);
            }
            if ($combined === null) {
                $combined = $part;
            } else {
                $combined->unionAll($part);
            }
        }

        return DB::query()->fromSub($combined, 'rms_log_index')
            ->orderByDesc('logged_at_utc')
            ->orderByDesc('log_type')
            ->orderByDesc('record_id');
    }

    /** @return array<int, array<string, mixed>> */
    private function hydrateRows(Collection $index): array
    {
        if ($index->isEmpty()) {
            return [];
        }
        $robots = Robot::query()
            ->whereIn('id', $index->pluck('robot_id')->unique()->all())
            ->get(['id', 'robot_code', 'name'])->keyBy('id');

        // Fetch only one batch per data source, never one query per log row.
        $records = [];
        foreach (self::SOURCES as $type => [$model]) {
            $ids = $index->where('log_type', $type)->pluck('record_id')->all();
            if ($ids !== []) {
                $records[$type] = $model::query()->whereIn('id', $ids)->get(self::DETAIL_COLUMNS[$type])->keyBy('id');
            }
        }

        $entries = [];
        foreach ($index as $row) {
            $model = $records[$row->log_type][(int) $row->record_id] ?? null;
            if ($model === null) {
                continue;
            }
            $robot = $robots[(int) $row->robot_id] ?? null;
            [$status, $tone, $summary, $details] = $this->describe($row->log_type, $model);
            $localTime = CarbonImmutable::parse((string) $row->logged_at_utc, 'UTC')->setTimezone(self::TZ);
            $entries[] = [
                'key' => $row->log_type . '-' . $row->record_id,
                'record_id' => (int) $row->record_id,
                'robot_id' => (int) $row->robot_id,
                'robot_code' => $robot?->robot_code ?? ('ID ' . $row->robot_id),
                'robot_name' => $robot?->name ?? 'Robot tidak ditemukan',
                'time_wib' => $localTime->format('d/m/Y H:i:s'),
                'type' => $row->log_type,
                'type_label' => $this->types()[$row->log_type] ?? $row->log_type,
                'status' => $status,
                'tone' => $tone,
                'summary' => $summary,
                'details' => $details,
            ];
        }

        return $entries;
    }

    /** @return array{string, string, string, array<string, mixed>} */
    private function describe(string $type, object $r): array
    {
        return match ($type) {
            'telemetry' => [
                $r->position_ok && $r->torque_ok ? 'OK' : (($r->position_ok || $r->torque_ok) ? 'PARTIAL' : 'READ FAILED'),
                $r->position_ok && $r->torque_ok ? 'ok' : (($r->position_ok || $r->torque_ok) ? 'warning' : 'danger'),
                'Pembacaan position PULSE & motor torque (SDK raw)',
                [
                    'sequence' => $r->sequence,
                    'position_ok' => $r->position_ok,
                    'position_status_code' => $r->position_status_code,
                    'position_pulses' => $r->position_pulses,
                    'torque_ok' => $r->torque_ok,
                    'torque_status_code' => $r->torque_status_code,
                    'torque_unit' => $r->torque_unit,
                    'torque_axes' => $r->torque_axes,
                ],
            ],
            'operating' => [
                $r->read_ok ? 'READ OK' : 'READ FAILED',
                $r->read_ok ? 'ok' : 'warning',
                $r->time_type . ': ' . ($r->read_ok ? ($r->value_raw ?? '(nilai tidak ada)') : 'pembacaan gagal'),
                ['raw_value' => $r->value_raw, 'value_seconds' => $r->value_seconds, 'status_code' => $r->status_code, 'message' => $r->status_message],
            ],
            'alarm_snapshot' => [
                $r->read_ok ? 'READ OK' : 'READ FAILED',
                $r->read_ok ? 'ok' : 'warning',
                $r->read_ok ? 'Snapshot active alarm (bukan jaminan tidak ada alarm)' : 'Gagal membaca active alarms',
                ['sdk_text' => $r->sdk_text, 'active_alarm_count' => $r->active_alarm_count, 'status_code' => $r->status_code, 'message' => $r->status_message],
            ],
            'alarm_event' => [
                strtoupper((string) $r->status),
                $r->status === 'active' ? 'danger' : 'neutral',
                ($r->alarm_code ?: 'Alarm') . ' — ' . ($r->alarm_name ?: 'Alarm event'),
                ['severity' => $r->severity, 'subcode' => $r->subcode, 'source' => $r->source, 'cleared_at' => $r->cleared_at?->toIso8601String(), 'details' => $r->details],
            ],
            'connection' => [
                strtoupper((string) $r->state),
                $r->state === 'connected' ? 'ok' : 'warning',
                $r->message ?: 'Perubahan status koneksi controller',
                ['state' => $r->state, 'ip_address' => $r->ip_address],
            ],
            'health' => [
                strtoupper((string) $r->health_status),
                $r->health_status === 'healthy' ? 'ok' : (in_array($r->health_status, ['critical', 'offline'], true) ? 'danger' : 'warning'),
                $r->summary ?: 'Equipment health assessment',
                ['rule_version' => $r->rule_version, 'assessment_source' => $r->assessment_source, 'indicators' => $r->indicators],
            ],
            'maintenance' => [
                strtoupper((string) $r->result),
                $r->result === 'completed' ? 'ok' : 'neutral',
                'Maintenance ' . $r->action_type . ' oleh ' . $r->performed_by,
                ['findings' => $r->findings, 'action_taken' => $r->action_taken,
                    'started_at' => $r->started_at?->toIso8601String(),
                    'completed_at' => $r->completed_at?->toIso8601String(),
                    'verified_by' => $r->verified_by, 'verified_at' => $r->verified_at?->toIso8601String()],
            ],
            'recommendation' => [
                strtoupper((string) $r->status),
                $r->status === 'open' ? 'warning' : 'neutral',
                $r->title . ' (' . $r->priority . ')',
                ['recommendation' => $r->recommendation_text, 'priority' => $r->priority, 'source' => $r->source, 'evidence' => $r->evidence],
            ],
            default => ['UNKNOWN', 'neutral', 'Unknown log type', []],
        };
    }

    private function safeCsv(string $text): string
    {
        // Prevent spreadsheet formula injection from robot or operator-supplied text.
        return preg_match('/^[\s\x00-\x1F]*[=+\-@]/u', $text) ? "'" . $text : $text;
    }
}
