<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Http\Requests\Comment\StoreCommentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class CommentController extends Controller
{
    public function index(Task $task): JsonResponse
    {
        Gate::authorize('view', $task);

        return response()->json($task->comments()->with('user')->latest()->get());
    }

    public function store(StoreCommentRequest $request, Task $task): JsonResponse
    {
        Gate::authorize('update', $task);
        $comment = $task->comments()->create([
            'body' => $request->validated('body'),
            'user_id' => $request->user()->getKey(),
        ]);

        return response()->json($comment->load('user'), 201);
    }
}
