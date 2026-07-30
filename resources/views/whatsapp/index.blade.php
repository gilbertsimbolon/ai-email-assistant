@extends('layouts.app')

@section('title', 'WhatsApp | AI Email Assistant')

@section('content')
<div class="card shadow-sm">
    <div class="card-body d-flex flex-column align-items-center justify-content-center text-center py-5" style="min-height: 60vh;">

        <div class="mb-4">
            <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-label-success" style="width: 120px; height: 120px;">
                <i class="bx bxl-whatsapp" style="font-size: 64px; color: #25D366;"></i>
            </span>
        </div>

        <h4 class="fw-bold mb-2">Channel WhatsApp</h4>
        <span class="badge bg-label-warning mb-3 px-3 py-2">
            <i class="bx bx-time-five me-1"></i> Dalam Tahap Pengembangan
        </span>

        <p class="text-secondary mb-0" style="max-width: 480px;">
            Kami sedang membangun integrasi WhatsApp agar percakapan pelanggan dari WhatsApp
            bisa dikelola dan dibalas dengan bantuan AI, sama seperti channel Email.
            Fitur ini akan segera hadir di sini.
        </p>

        <a href="{{ route('inbox.index') }}" class="btn btn-outline-primary mt-4">
            <i class="bx bx-envelope me-1"></i> Kembali ke Inbox Email
        </a>
    </div>
</div>
@endsection
