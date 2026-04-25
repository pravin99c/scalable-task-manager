<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Task;
use App\Http\Requests\Comment\StoreCommentRequest;
use Illuminate\Http\JsonResponse;

class CommentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Task $task): JsonResponse
    {
        return response()->json($task->comments()->with('user')->latest()->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCommentRequest $request, Task $task): JsonResponse
    {
        $comment = $task->comments()->create([
            'body' => $request->validated('body'),
            'user_id' => auth()->id(),
        ]);

        return response()->json($comment->load('user'), 201);
    }
}
