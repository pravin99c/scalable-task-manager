@extends('layouts.admin')

@section('title', 'Users Management')
@section('header_title', 'Users Management')
@section('header_subtitle', 'Manage all users in the system')

@section('content')
<div class="panel">
    <div class="panel-header">
        <span class="panel-title">System Users</span>
        <div style="display: flex; gap: 0.75rem; align-items: center;">
            <span class="badge todo">{{ $users->total() }} Total</span>
            <a href="{{ route('admin.users.create') }}" class="badge todo" style="text-decoration:none; background: var(--accent); color: #fff;">+ Add User</a>
        </div>
    </div>
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Joined</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($users as $user)
            <tr>
                <td>#{{ $user->id }}</td>
                <td><span class="proj-name">{{ $user->name }}</span></td>
                <td>{{ $user->email }}</td>
                <td>
                    @foreach($user->roles as $role)
                        @php
                            $roleClass = 'medium';
                            if($role->slug === 'superadmin') $roleClass = 'high';
                            elseif($role->slug === 'manager') $roleClass = 'todo';
                        @endphp
                        <span class="badge {{ $roleClass }}">{{ strtoupper($role->name) }}</span>
                    @endforeach
                    @if($user->roles->isEmpty())
                        <span class="badge medium">NO ROLE</span>
                    @endif
                </td>
                <td>{{ $user->created_at->format('M j, Y') }}</td>
                <td>
                    <div style="display: flex; gap: 0.5rem;">
                        <a href="{{ route('admin.users.edit', $user) }}" class="badge todo" style="text-decoration:none;">Edit</a>
                        <form action="{{ route('admin.users.destroy', $user) }}" method="POST" onsubmit="return confirm('Delete user?');">
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
        {{ $users->links() }}
    </div>
</div>
@endsection
