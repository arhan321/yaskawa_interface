@extends('rms.layouts.app')
@section('heading', 'Alarm center')
@section('subtitle', 'Alarm event terstruktur dan raw snapshots dari PostgreSQL — status pembacaan dijaga terpisah.')
@section('auto_refresh', '30')
@section('content')
<div class="stats rms-stats-compact">
@include('rms.partials.metric',['label'=>'Alarm aktif teridentifikasi','value'=>$activeEventCount,'unit'=>'event','description'=>'Dari tabel robot_alarm_events'])
@include('rms.partials.metric',['label'=>'Raw alarm snapshots','value'=>$snapshots->count(),'unit'=>'terbaru','description'=>'Maks. 30 pembacaan SDK'])
</div>
<div class="panel"><form method="GET" class="rms-filter-form">
@include('rms.partials.filter-robots',['robots'=>$robots])
<label class="field">Status <select name="status" class="input">@foreach(['all','active','cleared','unknown'] as $val)<option @selected(($filter['status'] ?? 'all') === $val) value="{{ $val }}">{{ strtoupper($val) }}</option>@endforeach</select></label>
<label class="field">Kode/nama <input name="search" class="input" value="{{ $filter['search'] ?? '' }}" maxlength="100" placeholder="Cari alarm"></label><button type="submit" class="btn primary">Cari</button><a class="btn" href="{{ route('rms.alarms.index') }}">Reset</a></form></div>
<section class="panel rms-section-spaced"><div class="panel-header"><h2>Structured alarm events</h2><span class="rms-muted-small">{{ $events->total() }} record</span></div>
@if($events->isEmpty()) @include('rms.partials.no-data',['message'=>'Belum ada alarm event terstruktur. Raw SDK tidak otomatis berarti nol alarm.']) @else
<div class="table-wrap"><table class="data-table"><thead><tr><th>Robot</th><th>Started at (WIB)</th><th>Code</th><th>Alarm</th><th>Severity</th><th>Status</th></tr></thead><tbody>@foreach($events as $e)<tr><td>{{ $e->robot?->robot_code ?? '—' }}</td><td>{{ $e->started_at?->timezone('Asia/Jakarta')->format('d M Y H:i:s') ?? '—' }}</td><td>{{ $e->alarm_code ?: '—' }}</td><td>{{ $e->alarm_name ?: 'Belum diketahui' }}</td><td>{{ $e->severity ?: '—' }}</td><td><span class="badge rms-badge {{ $e->status }}">{{ strtoupper($e->status) }}</span></td></tr>@endforeach</tbody></table></div><div class="rms-pagination">{{ $events->links() }}</div>
@endif</section>
<section class="panel rms-section-spaced"><div class="panel-header"><div><h2>Raw active-alarm snapshots</h2><p>Untuk validasi parser atau investigasi error pembacaan</p></div></div><div class="table-wrap"><table class="data-table"><thead><tr><th>Robot</th><th>Sample (WIB)</th><th>Read status</th><th>Active count</th><th>SDK text</th></tr></thead><tbody>@forelse($snapshots as $s)<tr><td>{{ $s->robot?->robot_code ?? '—' }}</td><td>{{ $s->sampled_at?->timezone('Asia/Jakarta')->format('d M H:i:s') }}</td><td>@include('rms.partials.read-status',['ok'=>$s->read_ok])</td><td>{{ $s->active_alarm_count ?? 'unknown' }}</td><td class="rms-raw">{{ \Illuminate\Support\Str::limit($s->sdk_text ?: '—', 150) }}</td></tr>@empty<tr><td colspan="5">Belum ada alarm snapshot</td></tr>@endforelse</tbody></table></div></section>
<div class="rms-note">{{ $parsingNote }}</div>
@endsection
