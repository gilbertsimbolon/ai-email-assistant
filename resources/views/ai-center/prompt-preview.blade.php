@extends('layouts.app')

@section('title', 'Prompt Preview | AI Center')

@section('content')
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white"><h5 class="mb-0">Prompt Preview</h5></div>
        <div class="card-body">
            <p class="text-body">
                Pilih percakapan untuk melihat hasil akhir Prompt AI yang dibentuk otomatis oleh sistem
                (Intent &rarr; SOP &rarr; Rule &rarr; Workflow &rarr; Knowledge Base &rarr; Template &rarr; AI Parameters &rarr; Conversation).
                Halaman ini bersifat read-only.
            </p>

            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-8">
                    <label class="form-label">Percakapan</label>
                    <select name="conversation" class="form-select" onchange="this.form.submit()">
                        <option value="">-- Pilih Percakapan --</option>
                        @foreach ($conversations as $conv)
                            <option value="{{ $conv->id }}" {{ $conversation?->id === $conv->id ? 'selected' : '' }}>
                                #{{ $conv->id }} — {{ $conv->contact_name ?? $conv->contact_email }} — {{ $conv->subject }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary">Lihat</button>
                </div>
            </form>
        </div>
    </div>

    @if ($sections)
        <div class="card shadow-sm">
            <div class="card-header bg-white"><h5 class="mb-0">System Prompt</h5></div>
            <div class="card-body">
                <pre class="p-4 rounded bg-body-secondary" style="white-space: pre-wrap;">{{ collect([
                    $sections['role'],
                    $sections['business_rules'],
                    $sections['sop'],
                    $sections['knowledge'],
                    $sections['template'],
                    $sections['generate_draft'],
                ])->filter(fn ($s) => trim($s) !== '')->implode("\n\n====================\n\n") }}</pre>
            </div>
        </div>

        <div class="card shadow-sm mt-4">
            <div class="card-header bg-white"><h5 class="mb-0">Customer Conversation (User Message)</h5></div>
            <div class="card-body">
                <pre class="p-4 rounded bg-body-secondary" style="white-space: pre-wrap;">{{ $sections['customer_conversation'] }}</pre>
            </div>
        </div>
    @endif
@endsection
