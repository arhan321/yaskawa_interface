@extends('rms.layouts.app')
@section('heading', 'Identitas & home position')
@section('subtitle', $robot->name)
@section('content')
<div class="back"><a class="btn text" href="{{ route('rms.robots.show', $robot) }}">← Kembali ke detail robot</a></div>
@if(session('identity_success'))<div class="notice rms-success" role="status">{{ session('identity_success') }}</div>@endif
@if($errors->any())<div class="notice warn" role="alert"><strong>Data belum disimpan.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<section class="panel">
  <div class="panel-header"><div><h2>Identitas robot</h2><p>Salin dari nameplate manipulator yang sesuai dengan robot ini.</p></div><span class="badge">{{ $robot->robot_code }}</span></div>
  <form class="rms-identity-form" method="POST" action="{{ route('rms.robots.identity.update', $robot) }}">
    @csrf @method('PUT')
    <div class="registry-grid">
      <label class="field"><span>Serial Number *</span><input class="input" name="serial_number" value="{{ old('serial_number', $robot->serial_number) }}" maxlength="120" required autocomplete="off"></label>
      <label class="field"><span>Type *</span><input class="input" name="robot_type" value="{{ old('robot_type', $robot->robot_type) }}" maxlength="120" required><small>Type pada nameplate, terpisah dari nama model di registry.</small></label>
      <label class="field"><span>Date (nameplate) *</span><input class="input" type="month" name="nameplate_date" value="{{ old('nameplate_date', $robot->nameplate_date) }}" required><small>Format bulan dan tahun. Contoh nameplate 2020.03 berarti Maret 2020.</small></label>
    </div>
    <div class="registry-actions"><button type="submit" class="btn primary">Simpan identitas</button></div>
  </form>
</section>
<section class="panel rms-section-spaced" id="home-position">
  <div class="panel-header"><div><h2>Referensi home position</h2><p>Bandingkan enam axis terhadap posisi acuan dari pendant/job yang telah diperiksa teknisi.</p></div><span class="badge">POSITION REFERENCE</span></div>
  <p class="rms-muted-small">Nilai 0 pulse bukan otomatis posisi home. Isi acuan untuk {{ $robot->control_group }} dan serial number robot ini. Mengganti serial number memerlukan pemeriksaan ulang referensi home.</p>
  <form method="POST" action="{{ route('rms.robots.home.update', $robot) }}" data-rms-home-form data-snapshot-url="{{ route('rms.live.snapshot', ['robot_id' => $robot->id]) }}">
    @csrf @method('PUT')
    <input type="hidden" name="captured_sample_id" value="{{ old('captured_sample_id') }}" data-rms-captured-sample>
    <div class="rms-home-inputs">
      @foreach($axes as $axis)
      <label class="field"><span>{{ $axis }} (pulse) *</span><input class="input mono" type="number" step="1" min="-2147483648" max="2147483647" name="home_axes[{{ $axis }}]" data-rms-home-axis="{{ $axis }}" value="{{ old('home_axes.'.$axis, data_get($home, 'axes.'.$axis)) }}" required></label>
      @endforeach
    </div>
    <div class="registry-actions"><button type="button" class="btn" data-rms-capture-home>Ambil posisi saat ini</button><span class="rms-muted-small" data-rms-capture-status role="status">Atau masukkan referensi pendant secara manual.</span></div>
    <div class="registry-grid">
      <label class="field"><span>Toleransi setiap axis (pulse) *</span><input class="input" type="number" name="tolerance_pulses" min="0" max="1000000" step="1" value="{{ old('tolerance_pulses', data_get($home, 'tolerance_pulses', 0)) }}" required><small>0 berarti kecocokan persis. Tentukan toleransi sesuai kebutuhan teknisi.</small></label>
      <label class="field"><span>Diperiksa oleh *</span><input class="input" name="verified_by" maxlength="120" value="{{ old('verified_by', data_get($home, 'verified_by')) }}" required></label>
      <label class="field field-wide"><span>Sumber referensi *</span><input class="input" name="source_reference" maxlength="255" value="{{ old('source_reference', data_get($home, 'source_reference')) }}" placeholder="Nama job dan titik home pada pendant" required></label>
    </div>
    <label class="rms-home-confirm"><input type="checkbox" name="home_verified" value="1" required> Saya telah memeriksa acuan home dan toleransinya untuk robot ini.</label>
    <div class="registry-actions"><button class="btn primary" type="submit">Simpan referensi home</button></div>
  </form>
</section>
<script src="{{ asset('rms/js/robot-identity.js') }}?v=20261022" defer></script>
@endsection
