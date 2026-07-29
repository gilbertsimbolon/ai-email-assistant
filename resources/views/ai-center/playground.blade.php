@extends('layouts.app')

@section('title', 'AI Playground | AI Center')

@section('content')
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">AI Playground</h5></div>
                <div class="card-body">
                    <p class="text-body">Tempel email/pesan pelanggan untuk menjalankan seluruh pipeline AI Center secara langsung (testing/debugging).</p>

                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ route('ai-center.playground.run') }}" method="POST">
                        @csrf

                        <div class="row">
                            <div class="col-md-6 mb-6">
                                <label class="form-label">Channel</label>
                                <select name="channel" class="form-select">
                                    @foreach (\App\Enums\ChannelType::cases() as $channel)
                                        <option value="{{ $channel->value }}" {{ old('channel', $old['channel'] ?? 'email') === $channel->value ? 'selected' : '' }}>
                                            {{ ucfirst($channel->value) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 mb-6">
                                <label class="form-label">Nama Pelanggan</label>
                                <input type="text" name="contact_name" class="form-control" value="{{ old('contact_name', $old['contact_name'] ?? '') }}">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-6">
                                <label class="form-label">Email Pelanggan</label>
                                <input type="email" name="contact_email" class="form-control" value="{{ old('contact_email', $old['contact_email'] ?? '') }}">
                            </div>
                            <div class="col-md-6 mb-6">
                                <label class="form-label">Subjek</label>
                                <input type="text" name="subject" class="form-control" value="{{ old('subject', $old['subject'] ?? '') }}">
                            </div>
                        </div>

                        <div class="mb-6">
                            <label class="form-label">Isi Email / Percakapan</label>
                            <textarea name="thread" rows="10" class="form-control @error('thread') is-invalid @enderror"
                                placeholder="Tempel isi email pelanggan di sini..." required>{{ old('thread', $old['thread'] ?? '') }}</textarea>
                            @error('thread') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="icon-base bx bx-play me-1"></i> Jalankan Pipeline
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            @if ($result)
                <div class="card mb-4">
                    <div class="card-header"><h5 class="mb-0">Hasil Pipeline</h5></div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tbody>
                                <tr><th>Detected Intent</th><td>{{ $result->intent?->name ?? '-' }}</td></tr>
                                <tr><th>Matched SOP</th><td>{{ $result->sop?->name ?? '-' }}</td></tr>
                                <tr><th>Matched Rule</th><td>{{ $result->rule?->name ?? '-' }}</td></tr>
                                <tr>
                                    <th>Matched Workflow Actions</th>
                                    <td>
                                        @forelse ($result->workflowActions as $action)
                                            <span class="badge bg-label-primary me-1">{{ $action->label }}</span>
                                        @empty
                                            -
                                        @endforelse
                                    </td>
                                </tr>
                                <tr>
                                    <th>Matched Knowledge Base</th>
                                    <td>
                                        @forelse ($result->knowledgeBases as $kb)
                                            <span class="badge bg-label-info me-1">{{ $kb->title }}</span>
                                        @empty
                                            -
                                        @endforelse
                                    </td>
                                </tr>
                                <tr><th>Matched Template</th><td>{{ $result->replyTemplate?->name ?? '-' }}</td></tr>
                                <tr><th>Token Usage</th><td>{{ $result->usage['total_tokens'] ?? '-' }}</td></tr>
                                <tr><th>Latency</th><td>{{ $result->latencyMs }} ms</td></tr>
                                <tr><th>Confidence Score</th><td>{{ $result->confidenceScore ?? '-' }}</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header"><h5 class="mb-0">Generated Prompt</h5></div>
                    <div class="card-body">
                        <pre class="p-4 rounded bg-body-secondary" style="white-space: pre-wrap;">{{ collect($result->prompt->messages)->map(fn ($m) => strtoupper($m['role']).":\n".$m['content'])->implode("\n\n---\n\n") }}</pre>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h5 class="mb-0">Generated Draft</h5></div>
                    <div class="card-body">
                        <pre class="p-4 rounded bg-body-secondary" style="white-space: pre-wrap;">{{ $result->draftContent }}</pre>
                    </div>
                </div>
            @else
                <div class="card">
                    <div class="card-body text-body">
                        Hasil pipeline akan tampil di sini setelah dijalankan.
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
