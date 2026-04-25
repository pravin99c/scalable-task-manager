@extends('layouts.admin')

@section('title', 'Roles Management')
@section('header_title', 'Roles Management')
@section('header_subtitle', 'Define and manage user roles and their associated permissions')

@section('content')
<div class="panel">
    <div class="panel-header">
        <span class="panel-title">System Roles</span>
        <a href="{{ route('admin.roles.create') }}" class="badge todo" style="text-decoration:none; background: var(--accent); color: #fff;">+ New Role</a>
    </div>
    <table class="table">
        <thead>
            <tr>
                <th>Role Name</th>
                <th>Slug</th>
                <th>Permissions</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($roles as $role)
            <tr>
                <td><span class="proj-name">{{ $role->name }}</span></td>
                <td><code>{{ $role->slug }}</code></td>
                <td>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.25rem;">
                        @foreach($role->permissions as $perm)
                            <span style="font-size: 0.7rem; background: rgba(255,255,255,0.05); padding: 0.2rem 0.5rem; border-radius: 4px; color: var(--muted);">
                                {{ $perm->name }}
                            </span>
                        @endforeach
                    </div>
                </td>
                <td>
                    <div style="display: flex; gap: 0.5rem;">
                        <a href="{{ route('admin.roles.edit', $role) }}" class="badge todo" style="text-decoration:none;">Edit</a>
                        <form action="{{ route('admin.roles.destroy', $role) }}" method="POST" onsubmit="return confirm('Delete role?');">
                            @csrf @method('DELETE')
                            <button type="submit" class="badge red" style="border:none; cursor:pointer; background: rgba(239,68,68,0.1); color: #fca5a5;">Delete</button>
                        </form>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
