# Payment System Design

## Overview

A comprehensive payment system for tracking payments across events, memberships, and other payable items. Integrates with PayChangu for online payments and automatically creates financial records.

## Goals

- Support wide scope: event fees, membership fees, donations, etc.
- Online payment via PayChangu API
- Track payment status, method, and carrier
- Automatic financial record creation
- Email notifications on successful payments

## Database Schema

### payments table

```sql
CREATE TABLE payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payable_type VARCHAR(255) NOT NULL,
    payable_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'MWK',
    status ENUM('pending', 'processing', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    payment_method VARCHAR(50) NULL,
    payment_carrier VARCHAR(50) DEFAULT 'paychangu',
    tx_ref VARCHAR(255) NOT NULL UNIQUE,
    provider_reference VARCHAR(255) NULL,
    provider_response JSON NULL,
    metadata JSON NULL,
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_payable (payable_type, payable_id),
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_tx_ref (tx_ref)
);
```

### Fields Description

| Field | Description |
|-------|-------------|
| `payable_type` | Model class this payment is for (Event, Membership, etc.) |
| `payable_id` | ID of the related model |
| `user_id` | User making the payment |
| `amount` | Payment amount |
| `currency` | ISO currency code (MWK, USD) |
| `status` | Current payment status |
| `payment_method` | How user paid: card, mobile_money, bank_transfer |
| `payment_carrier` | Payment provider: paychangu |
| `tx_ref` | Unique transaction reference (generated) |
| `provider_reference` | PayChangu's reference ID |
| `provider_response` | Full API response from PayChangu |
| `metadata` | Extra data (event name, description, etc.) |
| `paid_at` | When payment was completed |

## Payment Flow

### 1. Initiate Payment

```
User → POST /payments
    {
        "payable_type": "event",
        "payable_id": 123,
        "amount": 5000
    }

PaymentService:
    1. Validate payable exists and has entry_fee
    2. Create Payment record (status: pending)
    3. Call PayChanguService::initiate()
    4. Update payment with tx_ref
    5. Return checkout_url to frontend
```

### 2. Payment Processing

```
User → PayChangu Checkout (external)
    - User enters card/mobile money details
    - PayChangu processes payment
    - Redirects to callback_url with tx_ref
```

### 3. Callback/Verification

```
PayChangu → POST /payments/callback?tx_ref=xxx

PaymentService::handleCallback():
    1. Find payment by tx_ref
    2. Call PayChanguService::verify(tx_ref)
    3. Update payment status based on response
    4. If completed:
        - Create FinancialRecord (type: INCOME)
        - Dispatch PaymentCompletedJob
    5. Return redirect URL to frontend
```

### 4. Post-Payment Actions

```
PaymentCompletedJob:
    1. Send PaymentCompletedMail to user
    2. Log activity
    3. Update related model if needed (e.g., mark attendance as paid)
```

## Components

### Models

#### Payment Model

```php
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

    protected $casts = [
        'amount' => 'decimal:2',
        'provider_response' => 'array',
        'metadata' => 'array',
        'paid_at' => 'datetime',
    ];

    // Polymorphic relation
    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

#### Event Model Updates

```php
// Add to Event model
public function payments(): MorphMany
{
    return $this->morphMany(Payment::class, 'payable');
}
```

### Services

#### PayChanguService

```php
class PayChanguService
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.paychangu.secret_key');
        $this->baseUrl = config('services.paychangu.base_url', 'https://api.paychangu.com');
    }

    public function initiate(array $data): array
    {
        // POST /payment
        // Returns: checkout_url, tx_ref
    }

    public function verify(string $txRef): array
    {
        // GET /verify-payment/{tx_ref}
        // Returns: status, amount, authorization details
    }
}
```

#### PaymentService

```php
class PaymentService
{
    public function initiate(int $userId, string $payableType, int $payableId, float $amount): Payment
    {
        // 1. Validate payable
        // 2. Create payment record
        // 3. Call PayChangu
        // 4. Return payment with checkout_url
    }

    public function handleCallback(string $txRef): Payment
    {
        // 1. Find payment
        // 2. Verify with PayChangu
        // 3. Update status
        // 4. Create financial record if completed
        // 5. Dispatch jobs
    }

    private function createFinancialRecord(Payment $payment): void
    {
        // Create FinancialRecord:
        // - title: "Payment for {payable title}"
        // - type: INCOME
        // - amount: payment.amount
        // - category_id: Look up 'Event Fees' or 'Membership Fees' category based on payable_type
        // - recorded_by: payment.user_id
    }
}
```

### Controllers

#### PaymentController

```php
class PaymentController extends Controller
{
    public function store(StorePaymentRequest $request): JsonResponse
    {
        // Initiate payment
    }

    public function show(Payment $payment): JsonResponse
    {
        // Get payment status
    }

    public function callback(Request $request): RedirectResponse
    {
        // Handle PayChangu callback
    }

    public function index(Request $request): JsonResponse
    {
        // List payments (admin)
    }
}
```

### Jobs

#### PaymentCompletedJob

```php
class PaymentCompletedJob implements ShouldQueue
{
    public function __construct(private Payment $payment) {}

    public function handle(): void
    {
        // 1. Send email
        // 2. Log activity
        // 3. Update related model if needed
    }
}
```

### Mail

#### PaymentCompletedMail

```php
class PaymentCompletedMail extends Mailable
{
    public function __construct(private Payment $payment) {}

    public function build(): Mailable
    {
        return $this->subject('Payment Confirmation')
            ->view('emails.payment-completed');
    }
}
```

## Routes

```php
// api.php - Authenticated routes
Route::prefix('v1')->middleware(['auth:logto'])->group(function () {
    // User routes
    Route::post('payments', [PaymentController::class, 'store']);
    Route::get('payments/{payment}', [PaymentController::class, 'show']);
    Route::get('my-payments', [PaymentController::class, 'myPayments']);

    // Callback (no auth - external webhook)
    Route::post('payments/callback', [PaymentController::class, 'callback'])
        ->middleware('throttle:api-sensitive');

    // Admin routes
    Route::middleware('deny_member')->group(function () {
        Route::get('payments', [PaymentController::class, 'index'])
            ->middleware('permission:payments.view,logto');
    });
});
```

## Configuration

```php
// config/services.php
'paychangu' => [
    'base_url' => env('PAYCHANGU_BASE_URL', 'https://api.paychangu.com'),
    'secret_key' => env('PAYCHANGU_SECRET_KEY'),
    'callback_url' => env('PAYCHANGU_CALLBACK_URL'), // e.g., https://yoursite.com/api/v1/payments/callback
    'return_url' => env('PAYCHANGU_RETURN_URL'), // e.g., https://yoursite.com/payment/status
],
```

## Error Handling

1. **API Failures**: Log error, update payment status to failed, return user-friendly message
2. **Duplicate Payments**: Check for existing pending payment with same payable, return existing
3. **Callback Verification**: If verification fails, keep status as processing, log for manual review
4. **Financial Record Failure**: Retry via queue, alert admin if persistent

## Testing

1. Unit tests for PayChanguService (mock HTTP)
2. Unit tests for PaymentService (mock PayChanguService)
3. Feature tests for endpoints
4. Test callback handling with various statuses
5. Test financial record creation

## Security

1. Validate callback origin (optional: verify IP whitelist)
2. Use unique tx_ref to prevent replay attacks
3. Rate limit payment initiation
4. Log all payment attempts for audit trail
5. Never store card details - PayChangu handles PCI compliance
