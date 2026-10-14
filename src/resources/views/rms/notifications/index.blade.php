@extends('rms.layouts.app')
@section('heading', 'Notification center')
@section('subtitle', 'Rencana pengiriman notifikasi maintenance dan alarm; konfigurasi belum tersedia di schema saat ini.')
@section('content')
<div class="notice warn"><strong>Notification delivery: DISABLED</strong> — tidak ada email yang dikirim dari halaman ini.</div>
<div class="two-col rms-section-spaced"><section class="panel"><h2>Notification rules</h2>@include('rms.partials.no-data',['title'=>'Rule belum dikonfigurasi','message'=>$message])</section><section class="panel"><h2>Delivery history</h2>@include('rms.partials.no-data',['title'=>'Log pengiriman belum tersedia','message'=>'Perlu migration notification_rules dan notification_delivery_logs serta layanan email.'])</section></div>
<div class="rms-note">Dashboard tidak menyimpan aturan di localStorage dan tidak menampilkan notifikasi palsu. Fitur menunggu backend penyimpanan, queue dan mail transport.</div>
@endsection
