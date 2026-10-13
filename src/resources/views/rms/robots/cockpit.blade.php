@extends('rms.layouts.app')
@section('heading', 'Robot Cockpit')
@section('subtitle', 'Monitoring detail torque, posisi, threshold terverifikasi, dan inspeksi speed reducer — bukan algoritma OEM Yaskawa Cockpit.')
@section('auto_refresh', 'off')
@section('content')
<link rel="stylesheet" href="{{ asset('rms/css/cockpit.css') }}">
@php
$axes = ['S','L','U','R','B','T'];
$latestTime = $latest?->sampled_at?->timezone('Asia/Jakarta')->format('d M Y H:i:s');
$isDemo = \Illuminate\Support\Str::startsWith($robot->robot_code, 'DEMO_') || \Illuminate\Support\Str::contains($robot->name, '[DEMO]');
@endphp
<div class="cp-root" data-cockpit>
  <div class="cp-toolbar">
    <div class="cp-links"><a href="{{ route('rms.robots.show',$robot) }}">← Robot detail</a><a href="{{ route('rms.robots.index') }}">Robot monitor</a>@if(Route::has('rms.logs.index'))<a href="{{ route('rms.logs.index', ['robot_id'=>$robot->id]) }}">Log robot</a>@endif</div>
    <div class="cp-tags">
      @if($isDemo)<span class="cp-pill cp-demo">DATA DEMO</span>@endif
      <span class="cp-pill {{ $fresh?'cp-ok':'cp-muted' }}">{{ $fresh?'TELEMETRY FRESH':'TELEMETRY STALE / UNKNOWN' }}</span>
    </div>
  </div>
  <div class="cp-head">
    <div><div class="cp-eyebrow">YASKAWA EQUIPMENT HEALTH / READ-ONLY</div><h2>{{ $robot->name }}</h2><p>{{ $robot->robot_code }} · {{ $robot->model }} · {{ $robot->control_group }} · {{ $robot->station_name ?: 'Station belum diatur' }}</p></div>
    <div class="cp-controller"><div>CONTROLLER</div><strong>{{ $robot->controller?->model ?? '—' }}</strong><small>{{ $robot->controller?->ip_address ?? 'IP belum didaftarkan' }}</small></div>
  </div>
  <div class="cp-metrics">
    <div><span>Sampel telemetry terakhir</span><strong>{{ $latestTime ?? 'Belum tersedia' }}</strong><small>Zona waktu WIB</small></div>
    <div><span>Servo operating hours</span><strong>{{ $operating?->read_ok && $operating->value_seconds !== null ? number_format($operating->value_seconds/3600, 2, ',', '.') . ' h' : 'Belum terverifikasi' }}</strong><small>Hanya konversi dari value_seconds terverifikasi</small></div>
    <div><span>Open threshold events</span><strong>{{ $openEvents->count() }}</strong><small>Berasal dari threshold engine; bukan alarm YRC1000</small></div>
    <div><span>Active structured alarms</span><strong>{{ $alarms->count() }}</strong><small>Jika parser event alarm sudah tersedia</small></div>
  </div>

  <div class="cp-columns">
    <section class="cp-panel cp-axis-panel">
      <header><div><span class="cp-kicker">CONTROL GROUP {{ $robot->control_group }}</span><h3>Axis monitoring</h3></div><span class="cp-panel-sub">S · L · U · R · B · T</span></header>
      <div class="cp-robot-illustration" aria-hidden="true">
        <svg viewBox="0 0 260 156" width="100%" height="145" role="img">
          <defs><linearGradient id="cpRobotBlue" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#75b0d7"/><stop offset="1" stop-color="#1e5f95"/></linearGradient></defs>
          <ellipse cx="130" cy="143" rx="94" ry="9" fill="#e4ecf1"/>
          <path d="M56 141h66l-12-22H70z" fill="#1c537e"/><path d="M72 120V94h33v26" fill="url(#cpRobotBlue)" stroke="#1d5d92" stroke-width="3"/>
          <path d="m88 98-22-43 23-12 24 47z" fill="url(#cpRobotBlue)" stroke="#1d5d92" stroke-width="3"/><path d="m76 48 78-23 12 24-77 23z" fill="url(#cpRobotBlue)" stroke="#1d5d92" stroke-width="3"/>
          <path d="m157 33 41 35-12 15-45-36z" fill="url(#cpRobotBlue)" stroke="#1d5d92" stroke-width="3"/><path d="m195 75 29 12-7 19-30-13z" fill="#276b9b" stroke="#1d5d92" stroke-width="3"/>
          <circle cx="88" cy="97" r="17" fill="#d4ecfc" stroke="#216596" stroke-width="6"/><circle cx="76" cy="51" r="14" fill="#d4ecfc" stroke="#216596" stroke-width="6"/>
          <circle cx="156" cy="39" r="13" fill="#d4ecfc" stroke="#216596" stroke-width="6"/><path d="m218 102-8 21m5-19 13 17" stroke="#245e8c" stroke-width="5" stroke-linecap="round"/>
        </svg>
        <div class="cp-robot-caption">Skema ilustrasi robot · bukan digital twin</div>
      </div>
      <div class="cp-axis-list">
        @foreach($axes as $axis)
          @php($d=$axisStatus[$axis])
          <div class="cp-axis-item">
            <span class="cp-axis-letter">{{ $axis }}</span>
            <div class="cp-axis-measure"><strong>{{ $d['value'] !== null ? number_format($d['value'],4,',','.') : '—' }}</strong><small>Torque SDK raw · Pulse {{ is_numeric($d['pulse']) ? number_format((float)$d['pulse'],0,',','.') : '—' }}</small></div>
            <span class="cp-state cp-{{ $d['status'] }}">{{ strtoupper(str_replace('_',' ', $d['status'])) }}</span>
          </div>
        @endforeach
      </div>
      <div class="cp-smallnote">Indikator berdasarkan satu pembacaan terakhir. Status event alarm threshold memakai 3 sampel berurutan dan hysteresis; keduanya bisa berbeda. Tanpa rule tervalidasi: NOT CONFIGURED.</div>
    </section>

    <section class="cp-panel cp-torque-panel">
      <header><div><span class="cp-kicker">MEASUREMENT HISTORY</span><h3>Motor torque — SLURBT</h3></div><span class="cp-panel-sub">Unit SDK RAW, bukan %/Nm</span></header>
      <div class="cp-chart-controls">
        <div class="cp-segment" role="group" aria-label="Mode grafik"><button type="button" data-mode="measurement" class="selected">Measurement</button><button type="button" data-mode="variation">Variation Δ</button></div>
        <div class="cp-periods" aria-label="Rentang historis">
          @foreach(['1h','8h','24h'] as $p)<a href="{{ route('rms.robots.cockpit', ['robot'=>$robot->id,'period'=>$p]) }}" class="{{ $period===$p?'selected':'' }}">{{ $p }}</a>@endforeach
        </div>
      </div>
      <div class="cp-chart-frame"><canvas id="cp-torque-chart" width="940" height="420" role="img" aria-label="Grafik enam sumbu torque dari PostgreSQL"></canvas><div id="cp-torque-empty" class="cp-empty" hidden>Belum tersedia sampel torque valid pada rentang ini.</div></div>
      <div class="cp-chart-legend" id="cp-torque-legend">@foreach($axes as $axis)<button type="button" data-axis="{{ $axis }}" class="is-selected"><span class="cp-dot cp-dot-{{ $axis }}"></span>{{ $axis }}</button>@endforeach</div>
      <div class="cp-chart-bottom"><div>Pilih axis untuk menampilkan/menyembunyikan garis. Garis threshold hanya ditampilkan untuk axis dengan rule terverifikasi, pada mode Measurement.</div><div><label for="cp-focus-axis">Threshold axis</label><select id="cp-focus-axis">@foreach($axes as $axis)<option value="{{ $axis }}">{{ $axis }}</option>@endforeach</select></div></div>
      <div class="cp-smallnote">Variation Δ = perbedaan dibanding sampel valid pertama dalam rentang terpilih; ini grafik selisih sederhana, <strong>bukan algoritma Yaskawa Cockpit/OEM</strong>.</div>
    </section>
  </div>

  <div class="cp-columns cp-second-row">
    <section class="cp-panel"><header><div><span class="cp-kicker">CONFIGURED LIMITS</span><h3>Torque threshold per axis</h3></div><span class="cp-panel-sub">Approved rules only</span></header>
      <div class="cp-table-wrap"><table class="cp-table"><thead><tr><th>Axis</th><th>Warning |raw|</th><th>Critical |raw|</th><th>Hysteresis</th><th>Status</th></tr></thead><tbody>
      @foreach($axes as $axis)
        @php($rule=$rules->get($axis))
        <tr><td><strong>{{ $axis }}</strong></td><td>{{ $rule && $rule->isApproved()?number_format((float)$rule->warning_magnitude,3,',','.'):'—' }}</td><td>{{ $rule && $rule->isApproved()?number_format((float)$rule->critical_magnitude,3,',','.'):'—' }}</td><td>{{ $rule && $rule->isApproved()?$rule->clear_hysteresis_percent.'%':'—' }}</td><td><span class="cp-state {{ $rule && $rule->isApproved()?'cp-normal':'cp-not_configured' }}">{{ $rule && $rule->isApproved()?'APPROVED':'NOT CONFIGURED' }}</span></td></tr>
      @endforeach
      </tbody></table></div>
      <div class="cp-smallnote">Konfigurasi rule via Artisan CLI berizin administrator. Dashboard sengaja tidak menyediakan form ubah batas tanpa autentikasi. Batas harus disahkan teknisi maintenance dan sesuai satuan SDK.</div>
    </section>
    <section class="cp-panel"><header><div><span class="cp-kicker">COMPONENT CONDITION</span><h3>Speed reducer life</h3></div><span class="cp-panel-sub">Verified OEM evidence</span></header>
      <div class="cp-chart-controls"><label for="cp-reducer-axis">Reducer / axis</label><select id="cp-reducer-axis">@foreach($axes as $axis)<option value="{{ $axis }}">Axis {{ $axis }}</option>@endforeach</select></div>
      <div class="cp-chart-frame cp-reducer-chart"><canvas id="cp-reducer-chart" width="940" height="360" role="img" aria-label="Histori hasil inspeksi speed reducer"></canvas><div id="cp-reducer-empty" class="cp-empty" hidden>Belum ada pengukuran speed reducer terverifikasi dari sumber OEM.</div></div>
      <div class="cp-reducer-meta" id="cp-reducer-meta"></div>
      <div class="cp-smallnote">Remaining life hanya ditampilkan jika nilai itu diberikan dan diverifikasi dari sumber OEM/inspeksi. RMS tidak menghitung prediksi umur komponen dari torque mentah.</div>
    </section>
  </div>

  <div class="cp-columns cp-last-row">
    <section class="cp-panel"><header><div><span class="cp-kicker">THRESHOLD EVENT HISTORY</span><h3>Warning & critical events</h3></div><span class="cp-panel-sub">{{ $eventHistory->count() }} record terbaru</span></header>
      <div class="cp-table-wrap"><table class="cp-table"><thead><tr><th>Time WIB</th><th>Axis</th><th>Severity</th><th>Status</th><th>Peak |raw|</th></tr></thead><tbody>
        @forelse($eventHistory as $event)<tr><td>{{ $event->started_at?->timezone('Asia/Jakarta')->format('d M H:i:s') }}</td><td>{{ $event->axis }}</td><td><span class="cp-state cp-{{ $event->severity }}">{{ strtoupper($event->severity) }}</span></td><td>{{ $event->status }}</td><td>{{ number_format((float)$event->peak_magnitude,4,',','.') }}</td></tr>
        @empty<tr><td colspan="5">Belum ada pelanggaran threshold yang tercatat. Ini tidak berarti robot dipastikan sehat.</td></tr>@endforelse
      </tbody></table></div>
    </section>
    <section class="cp-panel"><header><div><span class="cp-kicker">MAINTENANCE REVIEW</span><h3>Data quality & action</h3></div></header>
      <div class="cp-quality"><div><strong>YMConnect telemetry</strong><span>{{ $fresh?'Sampel segar':'Tidak segar atau belum tersedia' }}</span></div><div><strong>Approved torque rules</strong><span>{{ $rules->filter(fn($r)=>$r->isApproved())->count() }} / 6 axis</span></div><div><strong>Reducer verified measurements</strong><span>{{ $reducerByAxis->filter()->count() }} / 6 axis</span></div><div><strong>Controller running/idle</strong><span>Belum diintegrasikan</span></div><div><strong>Job & I/O</strong><span>Belum diintegrasikan</span></div></div>
      <div class="cp-smallnote">Saat koneksi/telemetry STALE, kondisi robot harus diperlakukan UNKNOWN. Keputusan perawatan tetap memerlukan inspeksi manusia dan referensi OEM.</div>
    </section>
  </div>
</div>
<script>
window.__RMS_COCKPIT__ = {{ \Illuminate\Support\Js::from($cockpitPayload) }};
</script>
<script src="{{ asset('rms/js/cockpit.js') }}" defer></script>
@endsection
