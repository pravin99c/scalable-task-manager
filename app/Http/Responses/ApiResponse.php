<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

final class ApiResponse
{
    public static function item(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => null,
            'links' => null,
        ], $status);
    }

    public static function message(string $message, int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => null,
            'message' => $message,
            'meta' => null,
            'links' => null,
        ], $status);
    }

    public static function page(LengthAwarePaginator $page, callable $transform): JsonResponse
    {
        $page->appends(request()->query());

        return response()->json([
            'data' => array_map($transform, $page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
                'from' => $page->firstItem(),
                'to' => $page->lastItem(),
            ],
            'links' => [
                'first' => $page->url(1),
                'last' => $page->url($page->lastPage()),
                'prev' => $page->previousPageUrl(),
                'next' => $page->nextPageUrl(),
            ],
        ]);
    }

    public static function error(string $message, int $status, array $errors = []): JsonResponse
    {
        return response()->json([
            'data' => null,
            'message' => $message,
            'errors' => $errors === [] ? (object) [] : $errors,
            'meta' => null,
            'links' => null,
        ], $status);
    }
}
