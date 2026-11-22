@extends('layouts.admin')

@section('title', 'Comments Management')
@section('header_title', 'Comments Management')
@section('header_subtitle', 'Latest discussions across tasks')

@section('content')
<div class="panel">
    <div class="panel-header">
        <span class="panel-title">Task Comments</span>
        <span class="badge todo">{{ $comments->total() }} Total</span>
    </div>
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Comment</th>
                <th>Task</th>
                <th>User</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            @foreach($comments as $comment)
            <tr>
                <td>#{{ $comment->id }}</td>
                <td style="max-width: 300px;">
                    <span style="display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        {{ $comment->body }}
                    </span>
                </td>
                <td>{{ $comment->task?->title ?? '—' }}</td>
                <td>{{ $comment->user?->name ?? '—' }}</td>
                <td>{{ $comment->created_at->format('M j, Y H:i') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div style="padding: 1rem;">
        {{ $comments->links() }}
    </div>
</div>
@endsection
