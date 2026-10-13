<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#152c27">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>{{ $title ?? 'Dashboard' }} — RMS Yaskawa</title>
  <link rel="stylesheet" href="{{ asset('rms/css/prototype-base.css') }}">
  <link rel="stylesheet" href="{{ asset('rms/css/laravel.css') }}">
  <link rel="stylesheet" href="{{ asset('rms/css/logs.css') }}">
  <script src="{{ asset('rms/js/dashboard.js') }}" defer></script>
  @if(request()->routeIs('rms.logs.*'))
    <script src="{{ asset('rms/js/logs.js') }}" defer></script>
  @endif
</head>
<body>
<a href="#main" class="skip-link">Lewati ke konten</a>
@php
  $navigation = [
    ['route' => 'rms.overview', 'label' => 'Overview', 'symbol' => '▦'],
    ['route' => 'rms.robots.index', 'label' => 'Robot monitor', 'symbol' => '♧'],
    ['route' => 'rms.logs.index', 'label' => 'Robot logs', 'symbol' => '≡'],
    ['route' => 'rms.alarms.index', 'label' => 'Alarm center', 'symbol' => '♢'],
    ['route' => 'rms.maintenance.index', 'label' => 'Maintenance', 'symbol' => '▤'],
    ['route' => 'rms.recommendations.index', 'label' => 'Recommendation', 'symbol' => '✧'],
    ['route' => 'rms.analytics.index', 'label' => 'Data analytics', 'symbol' => '▥'],
    ['route' => 'rms.connections.index', 'label' => 'Data & koneksi', 'symbol' => '◇'],
    ['route' => 'rms.notifications.index', 'label' => 'Notifikasi', 'symbol' => '◎'],
    ['route' => 'rms.hardware.index', 'label' => 'Hardware insights', 'symbol' => '⌘'],
    ['route' => 'rms.features.index', 'label' => 'Status fitur', 'symbol' => '▧'],
  ];
@endphp
<div class="rms-app" data-rms-page="{{ Route::currentRouteName() }}">
  <aside class="sidebar" id="rms-sidebar" aria-label="Navigasi RMS">
    <a class="brand" href="{{ route('rms.overview') }}"><span class="brandmark">R</span><span><span class="brand-name">RMS<span class="brand-dot">.</span></span><small>YASKAWA ROBOT HEALTH</small></span></a>
    <div class="workspace"><div class="workspace-icon">▤</div><div><strong>Equipment health</strong><p>Yaskawa · Monitoring</p></div></div>
    <nav class="sidebar-scroll">
      <div class="nav-label">WORKSPACE</div>
      @foreach($navigation as $n)
        @if($loop->index === 7)<div class="nav-label nav-second">SYSTEM</div>@endif
        <a class="nav-item {{ request()->routeIs($n['route']) || ($n['route']==='rms.robots.index' && request()->routeIs('rms.robots.show')) ? 'active' : '' }}" href="{{ route($n['route']) }}">
          <span class="nav-symbol" aria-hidden="true">{{ $n['symbol'] }}</span>{{ $n['label'] }}
        </a>
      @endforeach
    </nav>
    <div class="sidebar-bottom">
      <div class="collector"><strong class="collector-name"><span class="dot"></span>RMS DATABASE</strong><p>PostgreSQL 16 · Laravel</p><span class="collector-hint">Status koneksi tidak sama dengan status gerak robot</span></div>
      <div class="sidebar-foot">RMS · YASKAWA <span style="float:right">WEB</span></div>
    </div>
  </aside>
  <div class="shell">
    <header class="topbar">
      <div class="flex gap"><button class="icon-btn mobile-menu" id="rms-menu-button" aria-label="Buka navigasi" aria-expanded="false" type="button">☰</button><span class="breadcrumb">RMS / <strong>{{ $title ?? 'Monitoring' }}</strong></span></div>
      <div class="top-actions">
        <span class="source-label"><span class="dot"></span>POSTGRESQL DATA</span>
        <span class="account-name">RMS Monitoring</span>
      </div>
    </header>
    <main id="main" class="main" tabindex="-1">
      <div class="page-heading"><div><div class="eyebrow">RMS / YASKAWA EQUIPMENT HEALTH</div><h1>@yield('heading', $title ?? 'Monitoring')</h1><p class="subtitle">@yield('subtitle', 'Data dari backend Laravel dan PostgreSQL — bukan simulasi.')</p></div>
        <div class="rms-toolbar"><span class="rms-last-update" id="rms-last-update" title="Waktu render server">Server: {{ now()->timezone('Asia/Jakarta')->format('d M Y H:i:s') }} WIB</span><button type="button" class="btn rms-refresh" id="rms-refresh-button" aria-label="Muat ulang data">↻ Refresh</button></div>
      </div>
      <div id="rms-refresh-message" class="rms-refresh-message" role="status" aria-live="polite"></div>
      <section id="rms-dynamic-content" data-rms-live-region data-rms-refresh="@yield('auto_refresh', 'off')">
        @yield('content')
      </section>
      <footer class="page-footer"><span>RMS Yaskawa · Equipment Health Monitoring</span><span>Read-only web dashboard · Waktu WIB</span></footer>
    </main>
  </div>
</div>
</body>
</html>
