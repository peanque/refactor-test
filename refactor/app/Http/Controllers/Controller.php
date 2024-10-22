<?php

namespace DTApi\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesResources;

class Controller extends BaseController
{
    use AuthorizesRequests, AuthorizesResources, DispatchesJobs, ValidatesRequests;

    protected function respondSuccessful($data, string $message = 'Success', int $statusCode = Response::HTTP_OK): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'message' => $message,
            'success' => true
        ], $statusCode);
    }

    protected function respondWithError(string $message, string $errorMessage = 'Bad Request', int $statusCode = Response::HTTP_BAD_REQUEST)
    {
        return response()->json([
            'message' => $message,
            'error' => $errorMessage,
            'success' => false
        ], $statusCode);
    }
}
