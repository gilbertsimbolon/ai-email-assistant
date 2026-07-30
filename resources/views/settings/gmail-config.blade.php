@extends('layouts.app')

@section('title', 'Konfigurasi Gmail | AI Email Assistant')

@section('content')
    <div class="row">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center bg-white">
                    <h5 class="mb-0"><i class="bx bxl-google me-1 text-primary"></i> Konfigurasi Gmail OAuth</h5>
                    <small class="text-body float-end">
                        Sumber saat ini:
                        <span class="badge {{ $source === 'database' ? 'bg-label-success' : 'bg-label-warning' }}">
                            {{ $source === 'database' ? 'Database' : '.env (fallback)' }}
                        </span>
                    </small>
                </div>
                <div class="card-body">
                    <form id="gmail-config-form" action="{{ route('settings.gmail-config.update') }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="mb-6">
                            <label class="form-label" for="client_id">Google Client ID</label>
                            <input type="text" class="form-control @error('client_id') is-invalid @enderror"
                                id="client_id" name="client_id" value="{{ old('client_id', $clientId) }}"
                                placeholder="xxxxxxxx.apps.googleusercontent.com" required>
                            @error('client_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-6 form-password-toggle">
                            <label class="form-label" for="client_secret">Google Client Secret</label>
                            <div class="input-group">
                                <input type="password" class="form-control @error('client_secret') is-invalid @enderror"
                                    id="client_secret" name="client_secret"
                                    placeholder="{{ $hasClientSecret ? 'Biarkan kosong untuk mempertahankan nilai saat ini' : 'Masukkan Client Secret' }}">
                                <span class="input-group-text cursor-pointer" onclick="toggleSecretVisibility()">
                                    <i class="icon-base bx bx-hide" id="client_secret_toggle_icon"></i>
                                </span>
                                @error('client_secret')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <small class="text-body">
                                @if ($hasClientSecret)
                                    Client Secret sudah tersimpan. Kosongkan field ini jika tidak ingin mengubahnya.
                                @else
                                    Belum ada Client Secret yang tersimpan.
                                @endif
                            </small>
                        </div>

                        <div class="mb-6">
                            <label class="form-label" for="redirect_uri">Redirect URI</label>
                            <input type="text" class="form-control @error('redirect_uri') is-invalid @enderror"
                                id="redirect_uri" name="redirect_uri" value="{{ old('redirect_uri', $redirectUri) }}"
                                placeholder="{{ url('/settings/gmail/callback') }}" required>
                            @error('redirect_uri')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-check form-switch mb-6">
                            <input type="hidden" name="enabled" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" id="enabled" name="enabled"
                                value="1" {{ old('enabled', $enabled) ? 'checked' : '' }}>
                            <label class="form-check-label" for="enabled">Aktifkan Integrasi Gmail</label>
                        </div>

                        <div id="test-connection-result" class="alert d-none" role="alert"></div>

                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-primary" id="test-connection-btn"
                                onclick="testGmailConnection()">
                                <i class="icon-base bx bx-plug me-1"></i> Test Connection
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="icon-base bx bx-save me-1"></i> Save Configuration
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleSecretVisibility() {
            const input = document.getElementById('client_secret');
            const icon = document.getElementById('client_secret_toggle_icon');
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            icon.classList.toggle('bx-hide', !isPassword);
            icon.classList.toggle('bx-show', isPassword);
        }

        function testGmailConnection() {
            const button = document.getElementById('test-connection-btn');
            const resultBox = document.getElementById('test-connection-result');
            const originalLabel = button.innerHTML;

            button.disabled = true;
            button.innerHTML = 'Menguji...';
            resultBox.classList.add('d-none');

            fetch('{{ route('settings.gmail-config.test-connection') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({
                    client_id: document.getElementById('client_id').value,
                    client_secret: document.getElementById('client_secret').value,
                    redirect_uri: document.getElementById('redirect_uri').value,
                }),
            })
                .then((response) => response.json())
                .then((data) => {
                    resultBox.classList.remove('d-none', 'alert-success', 'alert-danger');
                    resultBox.classList.add(data.success ? 'alert-success' : 'alert-danger');
                    resultBox.textContent = data.message;
                })
                .catch(() => {
                    resultBox.classList.remove('d-none', 'alert-success');
                    resultBox.classList.add('alert-danger');
                    resultBox.textContent = 'Gagal menghubungi server.';
                })
                .finally(() => {
                    button.disabled = false;
                    button.innerHTML = originalLabel;
                });
        }
    </script>
@endsection
