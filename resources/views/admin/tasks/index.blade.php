@extends('layouts.admin')

@section('title', 'Tasks Management')
@section('header_title', 'Tasks Management')
@section('header_subtitle', 'Global task monitoring')

@section('content')
<div class="panel">
    <div class="panel-header">
        <span class="panel-title">System Tasks</span>
        <div style="display: flex; gap: 0.75rem; align-items: center;">
            <span class="badge todo">{{ $tasks->total() }} Total</span>
            <a href="{{ route('admin.tasks.create') }}" class="badge todo" style="text-decoration:none; background: var(--accent); color: #fff;">+ Create Task</a>
        </div>
    </div>
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Title</th>
                <th>Project</th>
                <th>Status</th>
                <th>Priority</th>
                <th>Due Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($tasks as $task)
            <tr>
                <td>#{{ $task->id }}</td>
                <td><span class="proj-name">{{ $task->title }}</span></td>
                <td>{{ $task->project?->name ?? '—' }}</td>
                <td>
                    <span class="badge {{ strtolower(str_replace(' ', '_', $task->status)) }}">
                        {{ $task->status }}
                    </span>
                </td>
                <td>
                    <span class="badge {{ strtolower($task->priority) }}">
                        {{ $task->priority }}
                    </span>
                </td>
                <td>{{ $task->due_date ? \Carbon\Carbon::parse($task->due_date)->format('M j, Y') : '—' }}</td>
                <td>
                    <div style="display: flex; gap: 0.5rem;">
                        <a href="{{ route('admin.tasks.edit', $task) }}" class="badge todo" style="text-decoration:none;">Edit</a>
                        <form action="{{ route('admin.tasks.destroy', $task) }}" method="POST" onsubmit="return confirm('Delete task?');">
                            @csrf @method('DELETE')
                            <button type="submit" class="badge red" style="border:none; cursor:pointer; background: rgba(239,68,68,0.1); color: #fca5a5;">Delete</button>
                        </form>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div style="padding: 1rem;">
        {{ $tasks->links() }}
    </div>
</div>
@endsection
