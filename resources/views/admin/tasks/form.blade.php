@extends('layouts.admin')

@php
    $isEdit = $task->exists;
    $title = $isEdit ? 'Edit Task' : 'Create New Task';
@endphp

@section('title', $title)
@section('header_title', $title)
@section('header_subtitle', $isEdit ? "Updating task: {$task->title}" : "Assign a new task to a project")

@section('extra_css')
<style>
    .form-group { margin-bottom: 1.5rem; }
    .form-label { display: block; font-size: 0.85rem; font-weight: 500; color: var(--muted); margin-bottom: 0.5rem; }
    .form-control {
        width: 100%;
        padding: 0.75rem 1rem;
        background: rgba(0,0,0,0.2);
        border: 1px solid var(--border);
        border-radius: 10px;
        color: #fff;
        font-size: 0.9rem;
    }
    .btn-submit {
        background: var(--accent);
        color: #fff;
        border: none;
        padding: 0.75rem 2rem;
        border-radius: 10px;
        font-weight: 600;
        cursor: pointer;
    }
</style>
@endsection

@section('content')
<div class="panel" style="max-width: 600px;">
    <div class="panel-header">
        <span class="panel-title">Task Details</span>
    </div>
    <form action="{{ $isEdit ? route('admin.tasks.update', $task) : route('admin.tasks.store') }}" method="POST" style="padding: 2rem;">
        @csrf
        @if($isEdit) @method('PUT') @endif

        <div class="form-group">
            <label class="form-label">Task Title</label>
            <input type="text" name="title" class="form-control" value="{{ old('title', $task->title) }}" required>
        </div>

        <div class="form-group">
            <label class="form-label">Project</label>
            <select name="project_id" class="form-control" {{ $isEdit ? 'disabled' : 'required' }}>
                @foreach($projects as $project)
                    <option value="{{ $project->id }}" {{ old('project_id', $task->project_id) == $project->id ? 'selected' : '' }}>
                        {{ $project->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <option value="TODO" {{ old('status', $task->status) === 'TODO' ? 'selected' : '' }}>TODO</option>
                    <option value="IN_PROGRESS" {{ old('status', $task->status) === 'IN_PROGRESS' ? 'selected' : '' }}>IN PROGRESS</option>
                    <option value="DONE" {{ old('status', $task->status) === 'DONE' ? 'selected' : '' }}>DONE</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Priority</label>
                <select name="priority" class="form-control">
                    <option value="LOW" {{ old('priority', $task->priority) === 'LOW' ? 'selected' : '' }}>LOW</option>
                    <option value="MEDIUM" {{ old('priority', $task->priority) === 'MEDIUM' ? 'selected' : '' }}>MEDIUM</option>
                    <option value="HIGH" {{ old('priority', $task->priority) === 'HIGH' ? 'selected' : '' }}>HIGH</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Due Date</label>
            <input type="date" name="due_date" class="form-control" value="{{ old('due_date', $task->due_date ? \Carbon\Carbon::parse($task->due_date)->format('Y-m-d') : '') }}">
        </div>

        <div style="margin-top: 2rem;">
            <button type="submit" class="btn-submit">{{ $isEdit ? 'UPDATE TASK' : 'CREATE TASK' }}</button>
            <a href="{{ route('admin.tasks.index') }}" style="color: var(--muted); font-size: 0.85rem; margin-left: 1rem; text-decoration: none;">Cancel</a>
        </div>
    </form>
</div>
@endsection
