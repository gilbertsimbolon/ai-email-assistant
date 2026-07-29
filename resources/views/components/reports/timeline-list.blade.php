@props(['events'])

<div class="list-group list-group-flush">
    @forelse ($events as $event)
        <div class="list-group-item d-flex align-items-start gap-3">
            <span class="avatar avatar-sm flex-shrink-0">
                <span class="avatar-initial rounded-circle bg-label-primary">
                    <i class="icon-base bx {{ $event['icon'] }}"></i>
                </span>
            </span>
            <div class="flex-grow-1">
                <div class="d-flex justify-content-between">
                    <h6 class="mb-0">{{ $event['title'] }}</h6>
                    <small class="text-muted">{{ $event['time']->format('d M Y H:i') }}</small>
                </div>
                <small class="text-muted">{{ $event['description'] }}</small>
            </div>
        </div>
    @empty
        <div class="p-4 text-center text-muted">Belum ada aktivitas.</div>
    @endforelse
</div>
