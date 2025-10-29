<?php

namespace App\Services;

use Illuminate\Http\JsonResponse;

class ApiResponseService
{
    /**
     * 成功响应
     *
     * @param mixed $data
     * @param string $message
     * @param int $statusCode
     * @return JsonResponse
     */
    public static function success($data = null, string $message = '操作成功', int $statusCode = 200): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * 错误响应
     *
     * @param string $message
     * @param int $statusCode
     * @param mixed $errors
     * @return JsonResponse
     */
    public static function error(string $message = '操作失败', int $statusCode = 400, $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * 验证失败响应
     *
     * @param mixed $errors
     * @param string $message
     * @return JsonResponse
     */
    public static function validationError($errors, string $message = '参数验证失败'): JsonResponse
    {
        return self::error($message, 422, $errors);
    }

    /**
     * 未认证响应
     *
     * @param string $message
     * @return JsonResponse
     */
    public static function unauthorized(string $message = '用户未认证'): JsonResponse
    {
        return self::error($message, 401);
    }

    /**
     * 权限不足响应
     *
     * @param string $message
     * @return JsonResponse
     */
    public static function forbidden(string $message = '权限不足'): JsonResponse
    {
        return self::error($message, 403);
    }

    /**
     * 资源不存在响应
     *
     * @param string $message
     * @return JsonResponse
     */
    public static function notFound(string $message = '资源不存在'): JsonResponse
    {
        return self::error($message, 404);
    }

    /**
     * 服务器错误响应
     *
     * @param string $message
     * @param mixed $errorDetails
     * @return JsonResponse
     */
    public static function serverError(string $message = '服务器内部错误', $errorDetails = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errorDetails !== null) {
            $response['error_details'] = $errorDetails;
        }

        return response()->json($response, 500);
    }

    /**
     * 分页响应
     *
     * @param mixed $data
     * @param object $paginator
     * @param string $message
     * @return JsonResponse
     */
    public static function paginated($data, object $paginator, string $message = '获取成功'): JsonResponse
    {
        return self::success([
            'data' => $data,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage()
            ]
        ], $message);
    }
}
