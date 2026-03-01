<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class PaymentController extends Controller
{
    #[OA\Get(
        path: "/api/admin/payments",
        summary: "Get all payments",
        tags: ["Admin Payments"],
        security: [["sanctum" => []]],
        responses: [new OA\Response(response: 200, description: "Payments list")]
    )]
    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status');
        
        $query = Payment::with(['client', 'booking']);

        if ($status) {
            $query->where('status', $status);
        }

        $payments = $query->latest()->paginate(20);

        return response()->json([
            'status' => 'success',
            'payments' => $payments,
        ]);
    }
}
