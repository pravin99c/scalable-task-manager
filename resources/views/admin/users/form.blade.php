@extends('layouts.admin')

@php
    $isEdit = $user->exists;
    $title = $isEdit ? 'Edit User' : 'Create New User';
@endphp

@section('title', $title)
@section('header_title', $title)
@section('header_subtitle', $isEdit ? "Modifying user: {$user->email}" : "Add a new member to the system")

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
        transition: border-color 0.2s;
    }
    .form-control:focus { outline: none; border-color: var(--accent); }
    .btn-submit {
        background: var(--accent);
        color: #fff;
        border: none;
        padding: 0.75rem 2rem;
        border-radius: 10px;
        font-weight: 600;
        cursor: pointer;
        transition: opacity 0.2s;
    }
    .btn-submit:hover { opacity: 0.9; }
</style>
@endsection

@section('content')
<div class="panel" style="max-width: 600px;">
    <div class="panel-header">
        <span class="panel-title">User Information</span>
    </div>
    <form action="{{ $isEdit ? route('admin.users.update', $user) : route('admin.users.store') }}" method="POST" style="padding: 2rem;">
        @csrf
        @if($isEdit) @method('PUT') @endif

        <div class="form-group">
            <label class="form-label">Full Name</label>
            <input type="text" name="name" class="form-control" value="{{ old('name', $user->name) }}" required placeholder="John Doe">
        </div>

        <div class="form-group">
            <label class="form-label">Email Address</label>
            <input type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required placeholder="john@example.com">
        </div>

        <div class="form-group">
            <label class="form-label">Assign Roles</label>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 1rem; background: rgba(0,0,0,0.1); padding: 1.5rem; border-radius: 12px; border: 1px solid var(--border);">
                @foreach($roles as $role)
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <input type="checkbox" name="roles[]" value="{{ $role->id }}" id="role_{{ $role->id }}" 
                               {{ $user->roles->contains($role->id) ? 'checked' : '' }}
                               style="width: 18px; height: 18px; accent-color: var(--accent);">
                        <label for="role_{{ $role->id }}" style="font-size: 0.85rem; color: #fff; cursor: pointer;">
                            {{ $role->name }}
                        </label>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Password {{ $isEdit ? '(Leave blank to keep current)' : '' }}</label>
            <input type="password" name="password" class="form-control" {{ $isEdit ? '' : 'required' }}>
        </div>

        <div style="margin-top: 2rem;">
            <button type="submit" class="btn-submit">{{ $isEdit ? 'UPDATE USER' : 'CREATE USER' }}</button>
            <a href="{{ route('admin.users.index') }}" style="color: var(--muted); font-size: 0.85rem; margin-left: 1rem; text-decoration: none;">Cancel</a>
        </div>
    </form>
</div>
@endsection
