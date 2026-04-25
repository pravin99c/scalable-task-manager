@extends('layouts.admin')

@php
    $isEdit = $project->exists;
    $title = $isEdit ? 'Edit Project' : 'Create New Project';
@endphp

@section('title', $title)
@section('header_title', $title)
@section('header_subtitle', $isEdit ? "Modifying: {$project->name}" : "Start a new organizational project")

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
        <span class="panel-title">Project Details</span>
    </div>
    <form action="{{ $isEdit ? route('admin.projects.update', $project) : route('admin.projects.store') }}" method="POST" style="padding: 2rem;">
        @csrf
        @if($isEdit) @method('PUT') @endif

        <div class="form-group">
            <label class="form-label">Project Name</label>
            <input type="text" name="name" class="form-control" value="{{ old('name', $project->name) }}" required placeholder="e.g. Website Redesign">
        </div>

        <div class="form-group">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="4" placeholder="Brief overview of the project goals...">{{ old('description', $project->description) }}</textarea>
        </div>

        <div style="margin-top: 2rem;">
            <button type="submit" class="btn-submit">{{ $isEdit ? 'UPDATE PROJECT' : 'CREATE PROJECT' }}</button>
            <a href="{{ route('admin.projects.index') }}" style="color: var(--muted); font-size: 0.85rem; margin-left: 1rem; text-decoration: none;">Cancel</a>
        </div>
    </form>
</div>
@endsection
