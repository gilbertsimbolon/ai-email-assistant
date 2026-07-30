@extends('layouts.app')

@section('title', 'Konfigurasi AI | AI Email Assistant')

@section('content')
    <div class="row">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center bg-white">
                    <h5 class="mb-0"><i class="bx bx-cog me-1 text-primary"></i> Konfigurasi AI Provider</h5>
                </div>
                <div class="card-body">
                    <form id="ai-config-form" action="{{ route('settings.ai-config.update') }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="mb-6">
                            <label class="form-label" for="provider">AI Provider</label>
                            <select class="form-select @error('provider') is-invalid @enderror" id="provider"
                                name="provider" required>
                                @foreach ($providers as $option)
                                    <option value="{{ $option->value }}"
                                        {{ old('provider', $provider->value) === $option->value ? 'selected' : '' }}>
                                        {{ $option->label() }}
                                    </option>
                                @endforeach
                            </select>
                            @error('provider')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-6 form-password-toggle">
                            <label class="form-label" for="api_key">API Key</label>
                            <div class="input-group">
                                <input type="password" class="form-control @error('api_key') is-invalid @enderror"
                                    id="api_key" name="api_key"
                                    placeholder="{{ $hasApiKey ? 'Biarkan kosong untuk mempertahankan nilai saat ini' : 'Masukkan API Key' }}">
                                <span class="input-group-text cursor-pointer" onclick="toggleApiKeyVisibility()">
                                    <i class="icon-base bx bx-hide" id="api_key_toggle_icon"></i>
                                </span>
                                @error('api_key')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <small class="text-body">
                                @if ($hasApiKey)
                                    API Key sudah tersimpan. Kosongkan field ini jika tidak ingin mengubahnya.
                                @else
                                    Belum ada API Key yang tersimpan.
                                @endif
                            </small>
                        </div>

                        <div class="mb-6">
                            <label class="form-label" for="base_url">Base URL</label>
                            <input type="text" class="form-control @error('base_url') is-invalid @enderror"
                                id="base_url" name="base_url" value="{{ old('base_url', $baseUrl) }}"
                                placeholder="https://api.openai.com/v1">
                            @error('base_url')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-6">
                            <label class="form-label" for="model">Model</label>
                            <input type="text" class="form-control @error('model') is-invalid @enderror" id="model"
                                name="model" value="{{ old('model', $model) }}" placeholder="gpt-4o" required>
                            @error('model')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-6">
                                <label class="form-label" for="temperature">Temperature</label>
                                <input type="number" step="0.1" min="0" max="2"
                                    class="form-control @error('temperature') is-invalid @enderror" id="temperature"
                                    name="temperature" value="{{ old('temperature', $temperature) }}" required>
                                @error('temperature')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4 mb-6">
                                <label class="form-label" for="max_tokens">Max Tokens</label>
                                <input type="number" step="1" min="1"
                                    class="form-control @error('max_tokens') is-invalid @enderror" id="max_tokens"
                                    name="max_tokens" value="{{ old('max_tokens', $maxTokens) }}" required>
                                @error('max_tokens')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4 mb-6">
                                <label class="form-label" for="timeout">Timeout (detik)</label>
                                <input type="number" step="1" min="1"
                                    class="form-control @error('timeout') is-invalid @enderror" id="timeout"
                                    name="timeout" value="{{ old('timeout', $timeout) }}" required>
                                @error('timeout')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="form-check form-switch mb-6">
                            <input type="hidden" name="enabled" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" id="enabled" name="enabled"
                                value="1" {{ old('enabled', $enabled) ? 'checked' : '' }}>
                            <label class="form-check-label" for="enabled">Aktifkan Integrasi AI</label>
                        </div>

                        <div id="test-connection-result" class="alert d-none" role="alert"></div>

                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-primary" id="test-connection-btn"
                                onclick="testAiConnection()">
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
        function toggleApiKeyVisibility() {
            const input = document.getElementById('api_key');
            const icon = document.getElementById('api_key_toggle_icon');
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            icon.classList.toggle('bx-hide', !isPassword);
            icon.classList.toggle('bx-show', isPassword);
        }

        function testAiConnection() {
            const button = document.getElementById('test-connection-btn');
            const resultBox = document.getElementById('test-connection-result');
            const originalLabel = button.innerHTML;

            button.disabled = true;
            button.innerHTML = 'Menguji...';
            resultBox.classList.add('d-none');

            fetch('{{ route('settings.ai-config.test-connection') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({
                    provider: document.getElementById('provider').value,
                    api_key: document.getElementById('api_key').value,
                    base_url: document.getElementById('base_url').value,
                    model: document.getElementById('model').value,
                    temperature: document.getElementById('temperature').value,
                    max_tokens: document.getElementById('max_tokens').value,
                    timeout: document.getElementById('timeout').value,
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
