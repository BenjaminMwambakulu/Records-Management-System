<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

trait APIResponse
{
    /**
     * @param  array<int|string, mixed>|object|null  $data
     */
    protected function success(mixed $data = null, string $message = 'Success', int $status = JsonResponse::HTTP_OK): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'errors' => null,
        ], $status);
    }

    /**
     * @param  array<int|string, mixed>|null  $errors
     */
    protected function error(string $message = 'An error occurred', int $status = JsonResponse::HTTP_BAD_REQUEST, ?array $errors = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'errors' => $errors,
        ], $status);
    }
}
