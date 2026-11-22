@extends('layouts.admin')

@php
    $isEdit = $role->exists;
    $title = $isEdit ? 'Edit Role' : 'Create New Role';
@endphp

@section('title', $title)
@section('header_title', $title)
@section('header_subtitle', $isEdit ? "Modifying role: {$role->name}" : "Define a new set of system permissions")

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
<div class="panel" style="max-width: 800px;">
    <div class="panel-header">
        <span class="panel-title">Role Configuration</span>
    </div>
    <form action="{{ $isEdit ? route('admin.roles.update', $role) : route('admin.roles.store') }}" method="POST" style="padding: 2rem;">
        @csrf
        @if($isEdit) @method('PUT') @endif

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <div class="form-group">
                <label class="form-label">Role Name</label>
                <input type="text" name="name" class="form-control" value="{{ old('name', $role->name) }}" required placeholder="e.g. Project Manager">
            </div>

            <div class="form-group">
                <label class="form-label">Role Slug (URL identifier)</label>
                <input type="text" name="slug" class="form-control" value="{{ old('slug', $role->slug) }}" required placeholder="e.g. project-manager">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Description</label>
            <input type="text" name="description" class="form-control" value="{{ old('description', $role->description) }}" placeholder="What can this role do?">
        </div>

        <div class="form-group">
            <label class="form-label">Assign Permissions</label>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem; background: rgba(0,0,0,0.1); padding: 1.5rem; border-radius: 12px; border: 1px solid var(--border);">
                @foreach($permissions as $perm)
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <input type="checkbox" name="permissions[]" value="{{ $perm->id }}" id="perm_{{ $perm->id }}" 
                               {{ $role->permissions->contains($perm->id) ? 'checked' : '' }}
                               style="width: 18px; height: 18px; accent-color: var(--accent);">
                        <label for="perm_{{ $perm->id }}" style="font-size: 0.85rem; color: #fff; cursor: pointer;">
                            {{ $perm->name }}
                        </label>
                    </div>
                @endforeach
            </div>
        </div>

        <div style="margin-top: 2rem;">
            <button type="submit" class="btn-submit">{{ $isEdit ? 'UPDATE ROLE' : 'CREATE ROLE' }}</button>
            <a href="{{ route('admin.roles.index') }}" style="color: var(--muted); font-size: 0.85rem; margin-left: 1rem; text-decoration: none;">Cancel</a>
        </div>
    </form>
</div>
@endsection
