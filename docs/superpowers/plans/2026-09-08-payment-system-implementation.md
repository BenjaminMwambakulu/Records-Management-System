# Payment System Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement a payment system with PayChangu integration for tracking payments across events, memberships, and other payable items.

**Architecture:** Polymorphic payments table linked to payable models, PayChangu API service for online payments, automatic financial record creation, and email notifications.

**Tech Stack:** Laravel, PHP 8.x, PayChangu API, MySQL/SQLite

**Spec:** `docs/superpowers/specs/2026-09-08-payment-system-design.md`

## Global Constraints

- Follow existing code patterns (Services, FormRequests, API Resources)
- Use `logto` guard for authentication
- Use `APIResponse` trait for controller responses
- Payments table uses polymorphic relations
- PayChangu API requires `Authorization: Bearer {secret_key}` header
- Test files use Pest PHP with `RefreshDatabase`

---

## File Structure

| File | Purpose |
|------|---------|
| `database/migrations/xxxx_create_payments_table.php` | Payments table migration |
| `app/Models/Payment.php` | Payment model with polymorphic relations |
| `app/Enums/PaymentStatus.php` | Payment status enum |
| `app/Services/PayChanguService.php` | PayChangu API integration |
| `app/Services/PaymentService.php` | Payment business logic |
| `app/Http/Controllers/Api/PaymentController.php` | Payment API endpoints |
| `app/Http/Requests/StorePaymentRequest.php` | Initiate payment validation |
| `app/Http/Resources/PaymentResource.php` | Payment API resource |
| `app/Jobs/PaymentCompletedJob.php` | Post-payment actions |
| `app/Mail/PaymentCompletedMail.php` | Payment confirmation email |
| `routes/api.php` | Add payment routes |
| `config/services.php` | Add PayChangu config |
| `tests/Feature/PaymentApiTest.php` | Payment API tests |
| `tests/Unit/PayChanguServiceTest.php` | PayChangu service tests |

---

### Task 1: Create Payment Status Enum

**Files:**
- Create: `app/Enums/PaymentStatus.php`

**Interfaces:**
- Produces: `PaymentStatus` enum with cases: PENDING, PROCESSING, COMPLETED, FAILED, REFUNDED

- [ ] **Step 1: Create the enum**

```php
<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case REFUNDED = 'refunded';
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Enums/PaymentStatus.php
git commit -m "feat: add PaymentStatus enum"
```

---

### Task 2: Create Payments Table Migration

**Files:**
- Create: `database/migrations/2026_09_08_000000_create_payments_table.php`

**Interfaces:**
- Produces: `payments` table with polymorphic indexes

- [ ] **Step 1: Create the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (blockquote $table) {
            $table->id();
            $table->string('payable_type');
            $table->unsignedBigInteger('payable_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('MWK');
            $table->string('status')->default('pending');
            $table->string('payment_method')->nullable();
            $table->string('payment_carrier')->default('paychangu');
            $table->string('tx_ref')->unique();
            $table->string('provider_reference')->nullable();
            $table->json('provider_response')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['payable_type', 'payable_id']);
            $table->index('user_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
```

- [ ] **Step 2: Run migration**

Run: `php artisan migrate`
Expected: Migration runs successfully

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_09_08_000000_create_payments_table.php
git commit -m "feat: create payments table migration"
```

---

### Task 3: Create Payment Model

**Files:**
- Create: `app/Models/Payment.php`
- Modify: `app/Models/Event.php` (add payments relationship)

**Interfaces:**
- Consumes: `PaymentStatus` enum
- Produces: `Payment` model with `payable()`, `user()` relationships

- [ ] **Step 1: Create Payment model**

```php
<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'payable_type',
        'payable_id',
        'user_id',
        'amount',
        'currency',
        'status',
        'payment_method',
        'payment_carrier',
        'tx_ref',
        'provider_reference',
        'provider_response',
        'metadata',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'provider_response' => 'array',
            'metadata' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPending(): bool
    {
        return $this->status === PaymentStatus::PENDING;
    }

    public function isCompleted(): bool
    {
        return $this->status === PaymentStatus::COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === PaymentStatus::FAILED;
    }
}
```

- [ ] **Step 2: Add payments relationship to Event model**

Add to `app/Models/Event.php`:

```php
use Illuminate\Database\Eloquent\Relations\MorphMany;

// Add inside Event class
public function payments(): MorphMany
{
    return $this->morphMany(Payment::class, 'payable');
}
```

- [ ] **Step 3: Commit**

```bash
git add app/Models/Payment.php app/Models/Event.php
git commit -m "feat: add Payment model with polymorphic relations"
```

---

### Task 4: Create PayChangu Service

**Files:**
- Create: `app/Services/PayChanguService.php`

**Interfaces:**
- Produces: `initiate(array $data): array` and `verify(string $txRef): array`

- [ ] **Step 1: Create the service**

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PayChanguService
{
    private string $apiKey;
    private string $baseUrl;
    private string $callbackUrl;
    private string $returnUrl;

    public function __construct()
    {
        $this->apiKey = config('services.paychangu.secret_key');
        $this->baseUrl = config('services.paychangu.base_url', 'https://api.paychangu.com');
        $this->callbackUrl = config('services.paychangu.callback_url');
        $this->returnUrl = config('services.paychangu.return_url');
    }

    /**
     * Initiate a payment transaction
     *
     * @param array{amount: float, currency?: string, first_name?: string, last_name?: string, email?: string, meta?: string, customization?: array{title?: string, description?: string}} $data
     * @return array{checkout_url: string, tx_ref: string}
     */
    public function initiate(array $data): array
    {
        $txRef = $data['tx_ref'] ?? 'TX-'.$this->generateTxRef();

        $payload = [
            'amount' => number_format($data['amount'], 2, '.', ''),
            'currency' => $data['currency'] ?? 'MWK',
            'tx_ref' => $txRef,
            'callback_url' => $this->callbackUrl,
            'return_url' => $this->returnUrl,
        ];

        if (isset($data['first_name'])) {
            $payload['first_name'] = $data['first_name'];
        }

        if (isset($data['last_name'])) {
            $payload['last_name'] = $data['last_name'];
        }

        if (isset($data['email'])) {
            $payload['email'] = $data['email'];
        }

        if (isset($data['meta'])) {
            $payload['meta'] = $data['meta'];
        }

        if (isset($data['customization'])) {
            $payload['customization'] = $data['customization'];
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->apiKey,
            'Content-Type' => 'application/json',
        ])->post("{$this->baseUrl}/payment", $payload);

        $result = $response->json();

        if ($response->failed() || ($result['status'] ?? '') !== 'success') {
            throw new \RuntimeException($result['message'] ?? 'Payment initiation failed');
        }

        return [
            'checkout_url' => $result['data']['checkout_url'],
            'tx_ref' => $txRef,
        ];
    }

    /**
     * Verify transaction status
     *
     * @return array{status: string, tx_ref: string, amount: int, currency: string, authorization?: array, customer?: array, reference?: string}
     */
    public function verify(string $txRef): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->apiKey,
        ])->get("{$this->baseUrl}/verify-payment/{$txRef}");

        $result = $response->json();

        if ($response->failed() || ($result['status'] ?? '') !== 'success') {
            throw new \RuntimeException($result['message'] ?? 'Payment verification failed');
        }

        return $result['data'];
    }

    private function generateTxRef(): string
    {
        return strtoupper(Str::random(8)).'-'.time();
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Services/PayChanguService.php
git commit -m "feat: add PayChangu API service"
```

---

### Task 5: Add PayChangu Configuration

**Files:**
- Modify: `config/services.php`

**Interfaces:**
- Consumes: PayChangu service config values

- [ ] **Step 1: Add config to services.php**

Add inside the `return` array in `config/services.php`:

```php
'paychangu' => [
    'base_url' => env('PAYCHANGU_BASE_URL', 'https://api.paychangu.com'),
    'secret_key' => env('PAYCHANGU_SECRET_KEY'),
    'callback_url' => env('PAYCHANGU_CALLBACK_URL'),
    'return_url' => env('PAYCHANGU_RETURN_URL'),
],
```

- [ ] **Step 2: Add env variables to .env.example**

Add to `.env.example`:

```
PAYCHANGU_BASE_URL=https://api.paychangu.com
PAYCHANGU_SECRET_KEY=
PAYCHANGU_CALLBACK_URL=https://yoursite.com/api/v1/payments/callback
PAYCHANGU_RETURN_URL=https://yoursite.com/payment/status
```

- [ ] **Step 3: Commit**

```bash
git add config/services.php .env.example
git commit -m "feat: add PayChangu configuration"
```

---

### Task 6: Create Payment Service

**Files:**
- Create: `app/Services/PaymentService.php`

**Interfaces:**
- Consumes: `PayChanguService`, `Payment` model, `PaymentStatus` enum
- Produces: `initiate()`, `handleCallback()`, `list()`, `find()`, `myPayments()`

- [ ] **Step 1: Create the service**

```php
<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Event;
use App\Models\FinancialRecord;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class PaymentService
{
    public function __construct(
        protected PayChanguService $paychangu,
    ) {}

    /**
     * Initiate a payment for a payable item
     */
    public function initiate(int $userId, string $payableType, int $payableId, float $amount, array $metadata = []): array
    {
        $payable = $this->getPayable($payableType, $payableId);

        if (! $payable) {
            throw new \RuntimeException('Payable item not found');
        }

        $user = \App\Models\User::findOrFail($userId);

        $payment = Payment::create([
            'payable_type' => $payableType,
            'payable_id' => $payableId,
            'user_id' => $userId,
            'amount' => $amount,
            'currency' => 'MWK',
            'status' => PaymentStatus::PENDING,
            'metadata' => array_merge($metadata, [
                'payable_title' => $this->getPayableTitle($payable),
            ]),
        ]);

        $checkoutData = $this->paychangu->initiate([
            'amount' => $amount,
            'first_name' => $user->name ?? $user->email,
            'email' => $user->email,
            'meta' => json_encode(['payment_id' => $payment->id]),
            'customization' => [
                'title' => 'Payment for '.$this->getPayableTitle($payable),
                'description' => 'Payment via PayChangu',
            ],
        ]);

        $payment->update([
            'tx_ref' => $checkoutData['tx_ref'],
            'status' => PaymentStatus::PROCESSING,
        ]);

        return [
            'payment' => $payment,
            'checkout_url' => $checkoutData['checkout_url'],
        ];
    }

    /**
     * Handle PayChangu callback
     */
    public function handleCallback(string $txRef): Payment
    {
        $payment = Payment::where('tx_ref', $txRef)->firstOrFail();

        try {
            $verification = $this->paychangu->verify($txRef);

            $payment->update([
                'status' => $verification['status'] === 'success'
                    ? PaymentStatus::COMPLETED
                    : PaymentStatus::FAILED,
                'provider_reference' => $verification['reference'] ?? null,
                'provider_response' => $verification,
                'payment_method' => $verification['authorization']['channel'] ?? null,
                'paid_at' => $verification['status'] === 'success' ? now() : null,
            ]);

            if ($payment->isCompleted()) {
                $this->createFinancialRecord($payment);
                \App\Jobs\PaymentCompletedJob::dispatch($payment);
            }
        } catch (\Exception $e) {
            $payment->update([
                'status' => PaymentStatus::FAILED,
                'provider_response' => ['error' => $e->getMessage()],
            ]);
        }

        return $payment->refresh();
    }

    /**
     * List all payments (admin)
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        return Payment::query()
            ->with(['payable', 'user'])
            ->when($filters['status'] ?? null, function (Builder $query, string $status) {
                $query->where('status', $status);
            })
            ->when($filters['user_id'] ?? null, function (Builder $query, int $userId) {
                $query->where('user_id', $userId);
            })
            ->latest()
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * List user's payments
     */
    public function myPayments(int $userId, array $filters = []): LengthAwarePaginator
    {
        return Payment::query()
            ->with(['payable'])
            ->where('user_id', $userId)
            ->when($filters['status'] ?? null, function (Builder $query, string $status) {
                $query->where('status', $status);
            })
            ->latest()
            ->paginate($filters['per_page'] ?? 15);
    }

    public function find(int $id): ?Payment
    {
        return Payment::with(['payable', 'user'])->find($id);
    }

    private function getPayable(string $type, int $id)
    {
        return $type::find($id);
    }

    private function getPayableTitle($payable): string
    {
        return match (true) {
            $payable instanceof Event => $payable->title,
            default => class_basename($payable).' #'.$payable->id,
        };
    }

    private function createFinancialRecord(Payment $payment): void
    {
        $payable = $payment->payable;

        $categoryName = match (true) {
            $payable instanceof Event => 'Event Fees',
            default => 'Payments',
        };

        $category = \App\Models\FinancialCategory::where('name', $categoryName)->first();

        FinancialRecord::create([
            'title' => 'Payment for '.$this->getPayableTitle($payable),
            'type' => 'income',
            'amount' => $payment->amount,
            'category_id' => $category?->id,
            'transaction_date' => $payment->paid_at?->toDateString() ?? now()->toDateString(),
            'recorded_by' => $payment->user_id,
        ]);
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Services/PaymentService.php
git commit -m "feat: add PaymentService with business logic"
```

---

### Task 7: Create StorePaymentRequest

**Files:**
- Create: `app/Http/Requests/StorePaymentRequest.php`

**Interfaces:**
- Produces: Validated data for payment initiation

- [ ] **Step 1: Create the form request**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'payable_type' => ['required', 'string', 'in:App\Models\Event'],
            'payable_id' => ['required', 'integer', 'exists:events,id'],
            'amount' => ['required', 'numeric', 'min:1'],
        ];
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Http/Requests/StorePaymentRequest.php
git commit -m "feat: add StorePaymentRequest validation"
```

---

### Task 8: Create PaymentResource

**Files:**
- Create: `app/Http/Resources/PaymentResource.php`

**Interfaces:**
- Consumes: `Payment` model
- Produces: API resource for payment data

- [ ] **Step 1: Create the resource**

```php
<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Payment */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payable_type' => class_basename($this->payable_type),
            'payable_id' => $this->payable_id,
            'user_id' => $this->user_id,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status->value,
            'payment_method' => $this->payment_method,
            'payment_carrier' => $this->payment_carrier,
            'tx_ref' => $this->tx_ref,
            'provider_reference' => $this->provider_reference,
            'metadata' => $this->metadata,
            'paid_at' => $this->paid_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Http/Resources/PaymentResource.php
git commit -m "feat: add PaymentResource for API responses"
```

---

### Task 9: Create PaymentCompletedJob

**Files:**
- Create: `app/Jobs/PaymentCompletedJob.php`

**Interfaces:**
- Consumes: `Payment` model
- Produces: Sends email, logs activity

- [ ] **Step 1: Create the job**

```php
<?php

namespace App\Jobs;

use App\Mail\PaymentCompletedMail;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class PaymentCompletedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Payment $payment,
    ) {}

    public function handle(): void
    {
        Mail::to($this->payment->user->email)
            ->send(new PaymentCompletedMail($this->payment));

        activity('payment')
            ->performedOn($this->payment)
            ->causedBy($this->payment->user)
            ->withProperties([
                'amount' => $this->payment->amount,
                'currency' => $this->payment->currency,
            ])
            ->log('Payment completed');
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Jobs/PaymentCompletedJob.php
git commit -m "feat: add PaymentCompletedJob for post-payment actions"
```

---

### Task 10: Create PaymentCompletedMail

**Files:**
- Create: `app/Mail/PaymentCompletedMail.php`
- Create: `resources/views/emails/payment-completed.blade.php`

**Interfaces:**
- Consumes: `Payment` model
- Produces: Payment confirmation email

- [ ] **Step 1: Create the mailable**

```php
<?php

namespace App\Mail;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentCompletedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Payment $payment,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Payment Confirmation - '.$this->payment->metadata['payable_title'] ?? 'Payment',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.payment-completed',
            with: [
                'payment' => $this->payment,
                'payableTitle' => $this->payment->metadata['payable_title'] ?? 'Item',
            ],
        );
    }
}
```

- [ ] **Step 2: Create the email template**

```blade
<x-mail::message>
# Payment Confirmed

Your payment has been successfully processed.

**Amount:** {{ number_format($payment->amount, 2) }} {{ $payment->currency }}
**For:** {{ $payableTitle }}
**Reference:** {{ $payment->tx_ref }}
**Date:** {{ $payment->paid_at->format('M d, Y H:i') }}

<x-mail::button :url="url('/payments/'.$payment->id)">
View Receipt
</x-mail::button>

Thank you for your payment!<br>
{{ config('app.name') }}
</x-mail::message>
```

- [ ] **Step 3: Commit**

```bash
git add app/Mail/PaymentCompletedMail.php resources/views/emails/payment-completed.blade.php
git commit -m "feat: add PaymentCompletedMail with template"
```

---

### Task 11: Create PaymentController

**Files:**
- Create: `app/Http/Controllers/Api/PaymentController.php`

**Interfaces:**
- Consumes: `PaymentService`, `StorePaymentRequest`, `PaymentResource`
- Produces: `store()`, `show()`, `callback()`, `index()`, `myPayments()`

- [ ] **Step 1: Create the controller**

```php
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
```

- [ ] **Step 2: Commit**

```bash
git add app/Http/Controllers/Api/PaymentController.php
git commit -m "feat: add PaymentController with API endpoints"
```

---

### Task 12: Add Payment Routes

**Files:**
- Modify: `routes/api.php`

**Interfaces:**
- Consumes: `PaymentController`

- [ ] **Step 1: Add routes to api.php**

Add inside the authenticated routes group (after line 46):

```php
// Payments
Route::post('payments', [PaymentController::class, 'store'])
    ->middleware(['throttle:api-write']);
Route::get('payments/{payment}', [PaymentController::class, 'show'])
    ->middleware(['throttle:api-read']);
Route::get('my-payments', [PaymentController::class, 'myPayments'])
    ->middleware(['throttle:api-read']);
```

Add the callback route outside the auth group (after line 20):

```php
Route::post('v1/payments/callback', [PaymentController::class, 'callback'])
    ->middleware('throttle:api-sensitive');
```

Add the import at the top:

```php
use App\Http\Controllers\Api\PaymentController;
```

- [ ] **Step 2: Add admin routes inside deny_member middleware**

Add inside the deny_member group:

```php
// Payments — admin routes
Route::get('payments', [PaymentController::class, 'index'])
    ->middleware(['throttle:api-read', 'permission:payments.view,logto']);
```

- [ ] **Step 3: Commit**

```bash
git add routes/api.php
git commit -m "feat: add payment routes"
```

---

### Task 13: Create Payment API Tests

**Files:**
- Create: `tests/Feature/PaymentApiTest.php`

**Interfaces:**
- Consumes: `PaymentController`, `Payment` model, `PayChanguService`

- [ ] **Step 1: Create the test file**

```php
<?php

use App\Enums\PaymentStatus;
use App\Models\Event;
use App\Models\User;
use App\Services\PayChanguService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Support\JwtTestHelper;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.logto.endpoint' => 'https://logto.test',
        'services.logto.issuer' => 'https://logto.test/oidc',
        'services.logto.api_resource' => 'https://api.test',
        'services.paychangu.secret_key' => 'test-secret-key',
        'services.paychangu.callback_url' => 'https://test.com/api/v1/payments/callback',
        'services.paychangu.return_url' => 'https://test.com/payment/status',
    ]);

    Cache::flush();

    $this->keys = JwtTestHelper::keyPair();

    Http::fake([
        'https://logto.test/oidc/jwks' => Http::response([
            'keys' => [JwtTestHelper::jwk($this->keys['public_pem'], $this->keys['kid'])],
        ]),
    ]);
});

function paymentToken(array $claims, array $keys): string
{
    return JwtTestHelper::sign($claims, $keys['private_pem'], $keys['kid']);
}

test('payment routes require authentication', function () {
    $this->postJson('/api/v1/payments', [])->assertStatus(401);
    $this->getJson('/api/v1/my-payments')->assertStatus(401);
});

test('authenticated user can initiate payment', function () {
    $user = User::factory()->create(['logto_id' => 'logto-payment-user']);
    $event = Event::factory()->create(['entry_fee' => 5000]);

    Http::fake([
        'https://api.paychangu.com/payment' => Http::response([
            'status' => 'success',
            'data' => [
                'checkout_url' => 'https://checkout.paychangu.com/test123',
                'data' => ['tx_ref' => 'TX-TEST123'],
            ],
        ]),
    ]);

    $this->withHeader('Authorization', 'Bearer '.paymentToken(JwtTestHelper::claims('logto-payment-user'), $this->keys))
        ->postJson('/api/v1/payments', [
            'payable_type' => 'App\Models\Event',
            'payable_id' => $event->id,
            'amount' => 5000,
        ])
        ->assertCreated()
        ->assertJsonPath('data.checkout_url', 'https://checkout.paychangu.com/test123')
        ->assertJsonPath('data.payment.status', 'processing');

    $this->assertDatabaseHas('payments', [
        'user_id' => $user->id,
        'payable_type' => 'App\Models\Event',
        'payable_id' => $event->id,
        'amount' => 5000,
        'status' => 'processing',
    ]);
});

test('initiate payment requires valid payable', function () {
    $user = User::factory()->create(['logto_id' => 'logto-payment-user']);

    $this->withHeader('Authorization', 'Bearer '.paymentToken(JwtTestHelper::claims('logto-payment-user'), $this->keys))
        ->postJson('/api/v1/payments', [
            'payable_type' => 'App\Models\Event',
            'payable_id' => 9999,
            'amount' => 5000,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['payable_id']);
});

test('initiate payment requires amount', function () {
    $user = User::factory()->create(['logto_id' => 'logto-payment-user']);
    $event = Event::factory()->create();

    $this->withHeader('Authorization', 'Bearer '.paymentToken(JwtTestHelper::claims('logto-payment-user'), $this->keys))
        ->postJson('/api/v1/payments', [
            'payable_type' => 'App\Models\Event',
            'payable_id' => $event->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['amount']);
});

test('user can view their payment', function () {
    $user = User::factory()->create(['logto_id' => 'logto-payment-user']);
    $event = Event::factory()->create();

    $payment = \App\Models\Payment::factory()->create([
        'user_id' => $user->id,
        'payable_type' => 'App\Models\Event',
        'payable_id' => $event->id,
    ]);

    $this->withHeader('Authorization', 'Bearer '.paymentToken(JwtTestHelper::claims('logto-payment-user'), $this->keys))
        ->getJson("/api/v1/payments/{$payment->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $payment->id);
});

test('user can list their payments', function () {
    $user = User::factory()->create(['logto_id' => 'logto-payment-user']);

    \App\Models\Payment::factory()->count(3)->create([
        'user_id' => $user->id,
    ]);

    $this->withHeader('Authorization', 'Bearer '.paymentToken(JwtTestHelper::claims('logto-payment-user'), $this->keys))
        ->getJson('/api/v1/my-payments')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});
```

- [ ] **Step 2: Run tests**

Run: `php artisan test tests/Feature/PaymentApiTest.php`
Expected: All tests pass

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/PaymentApiTest.php
git commit -m "feat: add PaymentApiTest for payment endpoints"
```

---

### Task 14: Create PayChanguService Unit Tests

**Files:**
- Create: `tests/Unit/PayChanguServiceTest.php`

**Interfaces:**
- Consumes: `PayChanguService`

- [ ] **Step 1: Create the test file**

```php
<?php

use App\Services\PayChanguService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PayChanguServiceTest extends TestCase
{
    protected PayChanguService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.paychangu.secret_key' => 'test-secret-key',
            'services.paychangu.base_url' => 'https://api.paychangu.com',
            'services.paychangu.callback_url' => 'https://test.com/callback',
            'services.paychangu.return_url' => 'https://test.com/return',
        ]);

        $this->service = new PayChanguService();
    }

    public function test_initiate_returns_checkout_url(): void
    {
        Http::fake([
            'https://api.paychangu.com/payment' => Http::response([
                'status' => 'success',
                'data' => [
                    'checkout_url' => 'https://checkout.paychangu.com/test123',
                    'data' => ['tx_ref' => 'TX-TEST123'],
                ],
            ]),
        ]);

        $result = $this->service->initiate([
            'amount' => 5000,
            'first_name' => 'John',
            'email' => 'john@example.com',
        ]);

        $this->assertArrayHasKey('checkout_url', $result);
        $this->assertArrayHasKey('tx_ref', $result);
        $this->assertEquals('https://checkout.paychangu.com/test123', $result['checkout_url']);
    }

    public function test_initiate_throws_on_failure(): void
    {
        Http::fake([
            'https://api.paychangu.com/payment' => Http::response([
                'status' => 'failed',
                'message' => 'Invalid amount',
            ], 400),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid amount');

        $this->service->initiate(['amount' => 0]);
    }

    public function test_verify_returns_payment_data(): void
    {
        Http::fake([
            'https://api.paychangu.com/verify-payment/TX-TEST123' => Http::response([
                'status' => 'success',
                'data' => [
                    'status' => 'success',
                    'tx_ref' => 'TX-TEST123',
                    'amount' => 5000,
                    'currency' => 'MWK',
                    'authorization' => ['channel' => 'Card'],
                ],
            ]),
        ]);

        $result = $this->service->verify('TX-TEST123');

        $this->assertEquals('success', $result['status']);
        $this->assertEquals('TX-TEST123', $result['tx_ref']);
        $this->assertEquals('Card', $result['authorization']['channel']);
    }

    public function test_verify_throws_on_failure(): void
    {
        Http::fake([
            'https://api.paychangu.com/verify-payment/TX-FAILED' => Http::response([
                'status' => 'failed',
                'message' => 'Transaction not found',
            ], 404),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Transaction not found');

        $this->service->verify('TX-FAILED');
    }
}
```

- [ ] **Step 2: Run tests**

Run: `php artisan test tests/Unit/PayChanguServiceTest.php`
Expected: All tests pass

- [ ] **Step 3: Commit**

```bash
git add tests/Unit/PayChanguServiceTest.php
git commit -m "feat: add PayChanguService unit tests"
```

---

### Task 15: Run Full Test Suite

**Files:**
- None (verification task)

- [ ] **Step 1: Run all tests**

Run: `php artisan test`
Expected: All tests pass

- [ ] **Step 2: Run linting**

Run: `./vendor/bin/pint --test`
Expected: No style violations

- [ ] **Step 3: Final commit if any fixes needed**

```bash
git add -A
git commit -m "chore: fix code style issues"
```

---

## Summary

| Task | Description | Files |
|------|-------------|-------|
| 1 | PaymentStatus enum | `app/Enums/PaymentStatus.php` |
| 2 | Payments migration | `database/migrations/...` |
| 3 | Payment model | `app/Models/Payment.php`, `app/Models/Event.php` |
| 4 | PayChangu service | `app/Services/PayChanguService.php` |
| 5 | Configuration | `config/services.php`, `.env.example` |
| 6 | Payment service | `app/Services/PaymentService.php` |
| 7 | StorePaymentRequest | `app/Http/Requests/StorePaymentRequest.php` |
| 8 | PaymentResource | `app/Http/Resources/PaymentResource.php` |
| 9 | PaymentCompletedJob | `app/Jobs/PaymentCompletedJob.php` |
| 10 | PaymentCompletedMail | `app/Mail/PaymentCompletedMail.php`, email template |
| 11 | PaymentController | `app/Http/Controllers/Api/PaymentController.php` |
| 12 | Routes | `routes/api.php` |
| 13 | API tests | `tests/Feature/PaymentApiTest.php` |
| 14 | Service tests | `tests/Unit/PayChanguServiceTest.php` |
| 15 | Final verification | Run full test suite |
