@extends('layouts.admin')

@section('title', 'Projects Management')
@section('header_title', 'Projects Management')
@section('header_subtitle', 'Overview of all ongoing projects')

@section('content')
<div class="panel">
    <div class="panel-header">
        <span class="panel-title">Active Projects</span>
        <div style="display: flex; gap: 0.75rem; align-items: center;">
            <span class="badge todo">{{ $projects->total() }} Total</span>
            <a href="{{ route('admin.projects.create') }}" class="badge todo" style="text-decoration:none; background: var(--accent); color: #fff;">+ New Project</a>
        </div>
    </div>
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Creator</th>
                <th>Created At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($projects as $project)
            <tr>
                <td>#{{ $project->id }}</td>
                <td><span class="proj-name">{{ $project->name }}</span></td>
                <td>{{ $project->creator?->name ?? 'System' }}</td>
                <td>{{ $project->created_at->format('M j, Y') }}</td>
                <td>
                    <div style="display: flex; gap: 0.5rem;">
                        <a href="{{ route('admin.projects.edit', $project) }}" class="badge todo" style="text-decoration:none;">Edit</a>
                        <form action="{{ route('admin.projects.destroy', $project) }}" method="POST" onsubmit="return confirm('Delete project?');">
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
        {{ $projects->links() }}
    </div>
</div>
@endsection
