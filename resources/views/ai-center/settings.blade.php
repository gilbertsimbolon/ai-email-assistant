@extends('layouts.app')

@section('title', 'Settings | AI Center')

@section('content')
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">AI Center Settings</h5></div>
                <div class="card-body">
                    @if (session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ route('ai-center.settings.update') }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="mb-6">
                            <label class="form-label" for="confidence_review_threshold">Confidence Review Threshold</label>
                            <input type="number" step="0.01" min="0" max="1"
                                class="form-control @error('confidence_review_threshold') is-invalid @enderror"
                                id="confidence_review_threshold" name="confidence_review_threshold"
                                value="{{ old('confidence_review_threshold', $setting?->confidence_review_threshold ?? 0.70) }}" required>
                            <small class="text-body">Draft dengan confidence di bawah nilai ini wajib direview manusia.</small>
                            @error('confidence_review_threshold') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-6">
                                <label class="form-label" for="default_fallback_tone">Default Fallback Tone</label>
                                <select class="form-select @error('default_fallback_tone') is-invalid @enderror"
                                    id="default_fallback_tone" name="default_fallback_tone" required>
                                    @foreach ($tones as $tone)
                                        <option value="{{ $tone->value }}"
                                            {{ old('default_fallback_tone', $setting?->default_fallback_tone ?? 'professional') === $tone->value ? 'selected' : '' }}>
                                            {{ $tone->label() }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('default_fallback_tone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6 mb-6">
                                <label class="form-label" for="default_escalation_target">Default Escalation Target</label>
                                <select class="form-select @error('default_escalation_target') is-invalid @enderror"
                                    id="default_escalation_target" name="default_escalation_target" required>
                                    @foreach ($escalationTargets as $target)
                                        <option value="{{ $target->value }}"
                                            {{ old('default_escalation_target', $setting?->default_escalation_target ?? 'human_agent') === $target->value ? 'selected' : '' }}>
                                            {{ $target->label() }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('default_escalation_target') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-6">
                                <label class="form-label" for="company_name">Nama Perusahaan</label>
                                <input type="text" class="form-control @error('company_name') is-invalid @enderror"
                                    id="company_name" name="company_name" value="{{ old('company_name', $setting?->company_name) }}">
                                @error('company_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6 mb-6">
                                <label class="form-label" for="default_agent_name">Nama Agent Default</label>
                                <input type="text" class="form-control @error('default_agent_name') is-invalid @enderror"
                                    id="default_agent_name" name="default_agent_name" value="{{ old('default_agent_name', $setting?->default_agent_name) }}">
                                @error('default_agent_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
