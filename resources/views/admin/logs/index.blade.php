@extends('layouts.admin')

@section('title', 'Activity Logs')
@section('header_title', 'Activity Logs')
@section('header_subtitle', 'Full system audit trail')

@section('extra_css')
<style>
    .log-details {
        font-family: monospace;
        font-size: 0.75rem;
        color: var(--muted);
        background: rgba(0,0,0,0.2);
        padding: 0.5rem;
        border-radius: 4px;
        max-width: 400px;
        overflow-x: auto;
    }
</style>
@endsection

@section('content')
<div class="panel">
    <div class="panel-header">
        <span class="panel-title">Audit History</span>
        <span class="badge todo">{{ $logs->total() }} Total Events</span>
    </div>
    <table class="table">
        <thead>
            <tr>
                <th>User</th>
                <th>Action</th>
                <th>Target</th>
                <th>Old Value</th>
                <th>New Value</th>
                <th>Timestamp</th>
            </tr>
        </thead>
        <tbody>
            @foreach($logs as $log)
            <tr>
                <td><strong>{{ $log->user?->name ?? 'System' }}</strong></td>
                <td>
                    <span class="badge {{ strtolower($log->action) }}">
                        {{ $log->action }}
                    </span>
                </td>
                <td>{{ class_basename($log->loggable_type) }} #{{ $log->loggable_id }}</td>
                <td>
                    @if($log->old_values)
                        <div class="log-details">{{ json_encode($log->old_values) }}</div>
                    @else
                        —
                    @endif
                </td>
                <td>
                    @if($log->new_values)
                        <div class="log-details">{{ json_encode($log->new_values) }}</div>
                    @else
                        —
                    @endif
                </td>
                <td>{{ $log->created_at->diffForHumans() }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div style="padding: 1rem;">
        {{ $logs->links() }}
    </div>
</div>
@endsection
