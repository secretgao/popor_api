<?php

namespace App\Http\Controllers;

use App\Services\InvoiceService;
use App\Http\Requests\Invoice\CreateInvoiceRequest;
use App\Http\Requests\Invoice\UpdateInvoiceRequest;
use App\Http\Requests\Invoice\UpdateInvoiceStatusRequest;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Invoices",
 *     description="账单管理接口"
 * )
 */
class InvoiceController extends BaseController
{
    /**
     * @OA\Get(
     *     path="/api/invoices",
     *     summary="获取账单列表",
     *     tags={"Invoices"},
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="页码",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="每页数量",
     *         required=false,
     *         @OA\Schema(type="integer", default=10)
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="账单状态",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="student_id",
     *         in="query",
     *         description="学生ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="成功"
     *     )
     * )
     */
    public function index(Request $request, InvoiceService $invoiceService)
    {
        try {
            $user = $this->requireAuthUser($request);
            if ($user instanceof \Illuminate\Http\JsonResponse) {
                return $user;
            }

            $result = $invoiceService->getInvoices($request, $user);

            return $this->success($result, '获取账单列表成功');
        } catch (\Exception $e) {
            return $this->handleServiceException($e);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/invoices",
     *     summary="创建账单",
     *     tags={"Invoices"},
     *     security={{"bearer_token":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="student_id", type="integer", description="学生ID"),
     *             @OA\Property(property="course_id", type="integer", description="课程ID"),
     *             @OA\Property(property="amount", type="number", description="账单金额"),
     *             @OA\Property(property="description", type="string", description="账单描述"),
     *             @OA\Property(property="year_month", type="string", description="年月(YYYYMM)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="成功"
     *     )
     * )
     */
    public function store(CreateInvoiceRequest $request, InvoiceService $invoiceService)
    {
        try {
            $user = $this->requireAuthUser($request);
            if ($user instanceof \Illuminate\Http\JsonResponse) {
                return $user;
            }

            $result = $invoiceService->createInvoice($request, $user);

            return $this->success($result, '账单创建成功');
        } catch (\Exception $e) {
            return $this->handleServiceException($e);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/invoices/{id}",
     *     summary="获取账单详情",
     *     tags={"Invoices"},
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="账单ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="成功"
     *     )
     * )
     */
    public function show(Request $request, $id, InvoiceService $invoiceService)
    {
        try {
            $user = $this->requireAuthUser($request);
            if ($user instanceof \Illuminate\Http\JsonResponse) {
                return $user;
            }

            $result = $invoiceService->getInvoice((int)$id, $user);

            return $this->success($result, '获取账单详情成功');
        } catch (\Exception $e) {
            return $this->handleServiceException($e);
        }
    }    /**
     * @OA\Put(
     *     path="/api/invoices/{id}",
     *     summary="更新账单",
     *     tags={"Invoices"},
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="账单ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="amount", type="number", description="账单金额"),
     *             @OA\Property(property="description", type="string", description="账单描述"),
     *             @OA\Property(property="year_month", type="string", description="年月(YYYYMM)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="成功"
     *     )
     * )
     */
    public function update(UpdateInvoiceRequest $request, $id, InvoiceService $invoiceService)
    {
        try {
            $user = $this->requireAuthUser($request);
            if ($user instanceof \Illuminate\Http\JsonResponse) {
                return $user;
            }

            $result = $invoiceService->updateInvoice($request, (int)$id, $user);

            return $this->success($result, '账单更新成功');
        } catch (\Exception $e) {
            return $this->handleServiceException($e);
        }
    }




    /**
     * @OA\Put(
     *     path="/api/invoices/{id}/status",
     *     summary="更新账单状态",
     *     tags={"Invoices"},
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="账单ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"status"},
     *             @OA\Property(property="status", type="integer", description="新状态值")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="状态更新成功",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="状态更新成功"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="status", type="integer"),
     *                 @OA\Property(property="status_name", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="账单不存在"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="权限不足"
     *     )
     * )
     */
    public function updateStatus(UpdateInvoiceStatusRequest $request, $id, InvoiceService $invoiceService)
    {
        try {
            $user = $this->requireAuthUser($request);
            if ($user instanceof \Illuminate\Http\JsonResponse) {
                return $user;
            }

            $result = $invoiceService->updateInvoiceStatus($request, (int)$id, $user);

            return $this->success($result, '状态更新成功');
        } catch (\Exception $e) {
            return $this->handleServiceException($e);
        }
    }
}


