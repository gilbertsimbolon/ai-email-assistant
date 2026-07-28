@if ($analysis)
    @php
        $sentiment = $analysis->sentiment->value ?? $analysis->sentiment ?? 'neutral';
        $badgeColor = $sentiment === 'positive' ? 'success' : ($sentiment === 'negative' ? 'danger' : 'warning');
    @endphp
    <div class="row g-2">
        <div class="col-sm-6">
            <label class="form-label text-muted small fw-bold mb-0">CUSTOMER INTENT</label>
            <p class="fw-semibold mb-0 text-dark">{{ $analysis->customer_intent ?? '-' }}</p>
        </div>
        <div class="col-sm-6">
            <label class="form-label text-muted small fw-bold mb-0">SENTIMEN</label>
            <div><span class="badge bg-{{ $badgeColor }}">{{ ucfirst($sentiment) }}</span></div>
        </div>
        <div class="col-12">
            <label class="form-label text-muted small fw-bold mb-0">RINGKASAN</label>
            <p class="text-secondary small mb-0">{{ $analysis->summary ?? 'Tidak ada ringkasan.' }}</p>
        </div>
    </div>
@else
    <p class="text-muted small mb-0">Belum ada data analisis AI. Klik "Generate Reply" untuk membuatnya.</p>
@endif
