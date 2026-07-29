@extends('layouts.app')

@section('title', 'Activity Timeline | Reports')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">Activity Timeline</h4>
        <div class="btn-group btn-group-sm">
            @foreach (['pdf' => ['PDF', 'danger'], 'excel' => ['Excel', 'success'], 'csv' => ['CSV', 'secondary']] as $format => $meta)
                <a href="{{ route('reports.export', ['report' => 'timeline', 'format' => $format]) }}"
                   class="btn btn-outline-{{ $meta[1] }}">{{ $meta[0] }}</a>
            @endforeach
        </div>
    </div>

    <div class="card">
        <x-reports.timeline-list :events="$events" />
        <div class="card-body">
            {{ $events->links() }}
        </div>
    </div>
@endsection
