@props(['tiles'])

<div class="row g-4">
    @foreach ($tiles as $tile)
        <div class="col-md-3 col-sm-6">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="avatar avatar-lg flex-shrink-0 me-3">
                        <span class="avatar-initial rounded bg-label-{{ $tile['color'] ?? 'primary' }}">
                            <i class="icon-base bx {{ $tile['icon'] }} icon-lg"></i>
                        </span>
                    </div>
                    <div>
                        <span class="text-body small d-block">{{ $tile['label'] }}</span>
                        <h5 class="mb-0">{{ $tile['value'] }}</h5>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>
