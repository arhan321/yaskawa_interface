@extends('rms.layouts.app')
@section('heading', 'Robot monitor')
@section('subtitle', 'Daftar robot dari tabel robots serta pembacaan terbaru dari PostgreSQL.')
@section('auto_refresh', 'off')
@section('content')
@if(session('success'))<div class="notice rms-success" role="status">✓ {{ session('success') }}</div>@endif
<div class="toolbar"><form method="GET" class="rms-filter-form"><label class="field">Status koneksi <select name="connectivity" class="input"><option value="all">Semua status</option>@foreach(['connected','offline','stale','unknown'] as $s)<option @selected($connectivityFilter === $s) value="{{ $s }}">{{ strtoupper($s) }}</option>@endforeach</select></label><button class="btn" type="submit">Terapkan</button><a href="{{ route('rms.robots.index') }}" class="btn text">Reset</a></form><span class="rms-muted-small">{{ $robots->count() }} robot ditemukan</span><a href="{{ route('rms.robots.create') }}" class="btn primary">＋ Tambah robot</a></div>
@if($robots->isEmpty()) @include('rms.partials.no-data',['message'=>'Tidak ada robot sesuai filter, atau registry database masih kosong.'])
@else <div class="robot-grid rms-robot-grid">@foreach($robots as $item) @include('rms.partials.robot-card',['item'=>$item]) @endforeach</div> @endif
<div class="rms-note">CONNECTED berarti collector memiliki data valid dan segar; <strong>bukan berarti robot sedang bergerak/RUN</strong>. Klik kartu untuk position PULSE dan torque SDK raw.</div>
@endsection
