@extends('rms.layouts.app')
@section('heading', 'Preventive maintenance')
@section('subtitle', 'Jadwal maintenance berbasis tanggal/jam terverifikasi dan jurnal pekerjaan teknisi.')
@section('content')
<div class="panel"><form method="GET" class="rms-filter-form">@include('rms.partials.filter-robots',['robots'=>$robots])<label class="field">Plan aktif <select name="active" class="input">@foreach(['all'=>'Semua','yes'=>'Aktif','no'=>'Nonaktif'] as $k=>$v)<option value="{{ $k }}" @selected(($filter['active'] ?? 'all') === $k)>{{ $v }}</option>@endforeach</select></label><button class="btn primary" type="submit">Filter</button><a class="btn" href="{{ route('rms.maintenance.index') }}">Reset</a></form></div>
<section class="rms-section-spaced"><div class="section-heading"><h2>Maintenance plans <span class="count">{{ $plans->total() }}</span></h2></div><div class="rms-pm-grid">
@forelse($plans as $plan)
 @php($due = $dueStatuses[$plan->id] ?? ['due'=>false, 'verified_operating_hours'=>null, 'due_by_hours'=>null])
 <article class="pm-card {{ $due['due'] ? 'due' : '' }}"><div class="between"><span class="badge rms-badge {{ $due['due'] ? 'warning' : 'connected' }}">{{ !$plan->is_active ? 'INACTIVE' : ($due['due'] ? 'DUE' : 'SCHEDULED') }}</span><span class="mono">#{{ $plan->id }}</span></div><h3>{{ $plan->title }}</h3><p>{{ $plan->description ?: 'Belum ada deskripsi pekerjaan' }}</p><div class="rms-kv-list"><div><span>Robot</span><strong>{{ $plan->robot?->robot_code ?? '—' }}</strong></div><div><span>Basis</span><strong>{{ $plan->basis }}</strong></div><div><span>Due date</span><strong>{{ $plan->next_due_at?->timezone('Asia/Jakarta')->format('d M Y H:i') ?? '—' }}</strong></div><div><span>Due operating hours</span><strong>{{ $plan->next_due_operating_hours ?? '—' }}</strong></div><div><span>Verified hours</span><strong>{{ $due['verified_operating_hours'] !== null ? number_format($due['verified_operating_hours'], 2) : 'Belum tersedia' }}</strong></div></div></article>
@empty <div class="panel">@include('rms.partials.no-data',['message'=>'Belum ada maintenance plan di PostgreSQL.'])</div> @endforelse
</div><div class="rms-pagination">{{ $plans->links() }}</div></section>
<section class="panel rms-section-spaced"><div class="panel-header"><h2>Maintenance journal</h2><span class="rms-muted-small">{{ $logs->total() }} record</span></div>
@if($logs->isEmpty()) @include('rms.partials.no-data',['message'=>'Belum ada maintenance log tersimpan.']) @else
<div class="table-wrap"><table class="data-table"><thead><tr><th>Robot</th><th>Waktu pekerjaan</th><th>Teknisi</th><th>Jenis / hasil</th><th>Temuan</th><th>Verifikasi</th></tr></thead><tbody>@foreach($logs as $l)<tr><td>{{ $l->robot?->robot_code ?? '—' }}</td><td>{{ $l->completed_at?->timezone('Asia/Jakarta')->format('d M Y H:i') ?? ($l->created_at?->timezone('Asia/Jakarta')->format('d M Y H:i') ?? '—') }}</td><td>{{ $l->performed_by }}</td><td>{{ $l->action_type }} · {{ $l->result }}</td><td>{{ \Illuminate\Support\Str::limit($l->findings ?: '—',90) }}</td><td>{{ $l->verified_by ?: 'Belum diverifikasi' }}</td></tr>@endforeach</tbody></table></div><div class="rms-pagination">{{ $logs->links() }}</div> @endif
</section>
<div class="rms-note">{{ $note }}. Pembuatan/edit plan dilakukan melalui endpoint backend berizin, bukan langsung dari halaman read-only ini.</div>
@endsection
