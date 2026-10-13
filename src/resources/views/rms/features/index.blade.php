@extends('rms.layouts.app')
@section('heading', 'Development status')
@section('subtitle', 'Peta kemampuan platform berdasarkan pengujian dan rencana pengembangan.')
@section('content')
<div class="rms-feature-grid">@foreach($features as $feature)<article class="capability-card"><span class="badge rms-badge {{ $feature['stage'] === 'poc_tested' ? 'connected' : ($feature['stage'] === 'needs_investigation' ? 'warning' : 'unknown') }}">{{ str_replace('_',' ',strtoupper($feature['stage'])) }}</span><h3>{{ $feature['name'] }}</h3><p>{{ match($feature['stage']) { 'poc_tested' => 'Fungsi pernah diuji pada proof-of-concept, bukan validasi seluruh production.', 'sdk_supported' => 'SDK mendukung pembacaan; end-to-end monitoring belum diverifikasi.', 'partially_tested' => 'Fungsi diuji sebagian; perlu verifikasi tambahan.', 'schema_prepared' => 'Tabel database dan kode aplikasi sudah disiapkan.', 'needs_investigation' => 'Masih memerlukan investigasi engineering.', default => 'Termasuk ruang lingkup/rencana pengembangan.' } }}</p></article>@endforeach</div>
<div class="rms-note">{{ $note }}</div>
@endsection
