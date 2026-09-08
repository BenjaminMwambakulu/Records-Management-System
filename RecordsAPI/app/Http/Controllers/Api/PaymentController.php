<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Http\Responses\APIResponse;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class PaymentController extends Controller
{
    use APIResponse;

    public function __construct(
        protected PaymentService $paymentService,
    ) {}

    public function store(StorePaymentRequest $request): JsonResponse
    {
        $user = auth('logto')->user();

        try {
            $result = $this->paymentService->initiate(
                $user->id,
                $request->validated('payable_type'),
                $request->validated('payable_id'),
                $request->validated('amount'),
            );

            return $this->success(
                [
                    'payment' => new PaymentResource($result['payment']),
                    'checkout_url' => $result['checkout_url'],
                ],
                'Payment initiated successfully',
                JsonResponse::HTTP_CREATED,
            );
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    public function show(int $id): JsonResponse
    {
        $payment = $this->paymentService->find($id);

        if (! $payment) {
            return $this->error('Payment not found', JsonResponse::HTTP_NOT_FOUND);
        }

        return $this->success(
            new PaymentResource($payment),
            'Payment retrieved successfully'
        );
    }

    public function callback(Request $request): RedirectResponse
    {
        $txRef = $request->query('tx_ref');

        if (! $txRef) {
            return redirect(config('services.paychangu.return_url').'?status=failed');
        }

        $payment = $this->paymentService->handleCallback($txRef);

        $status = $payment->isCompleted() ? 'success' : 'failed';

        return redirect(config('services.paychangu.return_url')."?status={$status}&tx_ref={$txRef}");
    }

    public function index(Request $request): JsonResponse
    {
        $payments = $this->paymentService->list(
            $request->only(['status', 'user_id', 'per_page'])
        );

        return $this->success(
            PaymentResource::collection($payments),
            'Payments retrieved successfully'
        );
    }

    public function myPayments(Request $request): JsonResponse
    {
        $user = auth('logto')->user();

        $payments = $this->paymentService->myPayments(
            $user->id,
            $request->only(['status', 'per_page'])
        );

        return $this->success(
            PaymentResource::collection($payments),
            'Your payments retrieved successfully'
        );
    }
}
