@extends('layouts.app')

@section('title', 'AI Parameters | AI Center')

@section('content')
    <div class="row">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bx bx-slider-alt me-1 text-primary"></i> AI Parameters</h5>
                    <small class="text-body">Mengatur parameter dari AI Model default: <strong>{{ $aiModel?->name ?? '-' }}</strong></small>
                </div>
                <div class="card-body">
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if (! $aiModel)
                        <p class="text-body mb-0">
                            Belum ada AI Model default. Tambahkan AI Model terlebih dahulu di menu
                            <a href="{{ route('ai-center.ai-models.create') }}">AI Models</a>.
                        </p>
                    @else
                        <form action="{{ route('ai-center.ai-parameters.update') }}" method="POST">
                            @csrf
                            @method('PUT')

                            <div class="row">
                                <div class="col-md-4 mb-6">
                                    <label class="form-label" for="temperature">Temperature</label>
                                    <input type="number" step="0.1" min="0" max="2"
                                        class="form-control @error('temperature') is-invalid @enderror" id="temperature"
                                        name="temperature" value="{{ old('temperature', $aiModel->temperature) }}" required>
                                    @error('temperature') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-md-4 mb-6">
                                    <label class="form-label" for="top_p">Top P</label>
                                    <input type="number" step="0.05" min="0" max="1"
                                        class="form-control @error('top_p') is-invalid @enderror" id="top_p"
                                        name="top_p" value="{{ old('top_p', $aiModel->top_p) }}">
                                    @error('top_p') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-md-4 mb-6">
                                    <label class="form-label" for="max_tokens">Max Tokens</label>
                                    <input type="number" step="1" min="1"
                                        class="form-control @error('max_tokens') is-invalid @enderror" id="max_tokens"
                                        name="max_tokens" value="{{ old('max_tokens', $aiModel->max_tokens) }}" required>
                                    @error('max_tokens') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-6">
                                    <label class="form-label" for="reasoning_effort">Reasoning Effort</label>
                                    <select class="form-select @error('reasoning_effort') is-invalid @enderror"
                                        id="reasoning_effort" name="reasoning_effort">
                                        <option value="">-</option>
                                        @foreach (['minimal', 'low', 'medium', 'high'] as $effort)
                                            <option value="{{ $effort }}"
                                                {{ old('reasoning_effort', $aiModel->reasoning_effort) === $effort ? 'selected' : '' }}>
                                                {{ ucfirst($effort) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('reasoning_effort') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-md-4 mb-6">
                                    <label class="form-label" for="presence_penalty">Presence Penalty</label>
                                    <input type="number" step="0.1" min="-2" max="2"
                                        class="form-control @error('presence_penalty') is-invalid @enderror" id="presence_penalty"
                                        name="presence_penalty" value="{{ old('presence_penalty', $aiModel->presence_penalty) }}">
                                    @error('presence_penalty') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-md-4 mb-6">
                                    <label class="form-label" for="frequency_penalty">Frequency Penalty</label>
                                    <input type="number" step="0.1" min="-2" max="2"
                                        class="form-control @error('frequency_penalty') is-invalid @enderror" id="frequency_penalty"
                                        name="frequency_penalty" value="{{ old('frequency_penalty', $aiModel->frequency_penalty) }}">
                                    @error('frequency_penalty') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="mb-6">
                                <label class="form-label" for="response_format">Response Format</label>
                                <select class="form-select @error('response_format') is-invalid @enderror"
                                    id="response_format" name="response_format" required>
                                    @foreach (\App\Enums\AiCenter\ResponseFormat::cases() as $format)
                                        <option value="{{ $format->value }}"
                                            {{ old('response_format', $aiModel->response_format?->value) === $format->value ? 'selected' : '' }}>
                                            {{ ucfirst($format->value) }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('response_format') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <button type="submit" class="btn btn-primary">Simpan</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
