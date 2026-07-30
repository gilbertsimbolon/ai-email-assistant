@extends('layouts.app')

@section('title', 'Settings | AI Email Assistant')

@section('content')
    <div class="row">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center bg-white">
                    <h5 class="mb-0"><i class="bx bxl-google me-1 text-primary"></i> Koneksi Gmail</h5>
                    <small class="text-body float-end">Sumber data inbox berasal dari akun Gmail yang terhubung.</small>
                </div>
                <div class="card-body">
                    @forelse ($gmailAccounts as $account)
                        <div class="d-flex justify-content-between align-items-center border rounded p-3 mb-3">
                            <div>
                                <div class="fw-semibold">
                                    <i class="icon-base bx bx-envelope me-1"></i>
                                    {{ $account->email }}
                                </div>
                                <small class="text-body">
                                    Terakhir sync:
                                    {{ $account->last_synced_at?->diffForHumans() ?? 'belum pernah' }}
                                </small>
                            </div>

                            <form action="{{ route('settings.gmail.disconnect', $account) }}" method="POST"
                                onsubmit="return confirm('Putuskan koneksi akun Gmail ini?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger btn-sm">Putuskan</button>
                            </form>
                        </div>
                    @empty
                        <div class="alert alert-warning mb-3">
                            Belum ada akun Gmail yang terhubung. Inbox tidak akan menampilkan data apa pun sampai
                            Anda menghubungkan minimal satu akun Gmail.
                        </div>
                    @endforelse

                    <a href="{{ route('settings.gmail.connect') }}" class="btn btn-primary">
                        <i class="icon-base bx bxl-google me-1"></i> Hubungkan Akun Gmail
                    </a>

                    @if (auth()->user()?->isAdmin())
                        <a href="{{ route('settings.gmail-config.index') }}" class="btn btn-outline-secondary ms-2">
                            <i class="icon-base bx bx-cog me-1"></i> Konfigurasi Gmail OAuth
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
