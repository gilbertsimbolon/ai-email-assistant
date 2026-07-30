@props([
    'title',
    'id',
    'height' => '320px',
])

<div class="card shadow-sm mb-4 h-100 ">
    <div class="card-header bg-white">
        <h6 class="mb-0">{{ $title }}</h6>
    </div>

    <div class="card-body">
        <div class="position-relative w-100" style="height: {{ $height }};">
            <canvas id="{{ $id }}"></canvas>
        </div>
    </div>
</div>
