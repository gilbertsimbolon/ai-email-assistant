@props(['title', 'id', 'height' => 280])

<div class="card shadow-sm mb-4 h-100">
    <div class="card-header bg-white">
        <h6 class="mb-0">{{ $title }}</h6>
    </div>
    <div class="card-body">
        <canvas id="{{ $id }}" height="{{ $height }}"></canvas>
    </div>
</div>
