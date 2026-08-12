<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponseTrait
{
    protected function successResponse(
        mixed $data = null,
        string $message = 'OK',
        int $status = 200,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'errors' => null,
        ], $status);
    }

    protected function createdResponse(
        mixed $data = null,
        string $message = 'Created successfully.',
    ): JsonResponse {
        return $this->successResponse($data, $message, 201);
    }

    protected function errorResponse(
        string $message,
        mixed $errors = null,
        int $status = 400,
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'errors' => $errors,
        ], $status);
    }

    protected function validationErrorResponse(
        mixed $errors,
        string $message = 'Validation failed.',
    ): JsonResponse {
        return $this->errorResponse($message, $errors, 422);
    }

    protected function notFoundResponse(
        string $message = 'Resource not found.',
    ): JsonResponse {
        return $this->errorResponse($message, null, 404);
    }

    protected function conflictResponse(
        string $message,
        mixed $errors = null,
    ): JsonResponse {
        return $this->errorResponse($message, $errors, 409);
    }
}
