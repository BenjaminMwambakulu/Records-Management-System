<?php

use App\Enums\PaymentStatus;
use App\Jobs\CreateFinancialRecordFromPayment;
use App\Jobs\PaymentCompletedJob;
use App\Jobs\RegisterEventAttendanceFromPayment;
use App\Models\Event;
use App\Models\FinancialRecord;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('member', 'web');
});

function resilientPayment(): Payment
{
    $user = User::factory()->create();

    return Payment::factory()->create([
        'user_id' => $user->id,
        'status' => PaymentStatus::COMPLETED,
        'payable_type' => Event::class,
        'payable_id' => Event::factory()->create(['title' => 'Hackathon'])->id,
        'amount' => 5000,
        'paid_at' => now(),
    ]);
}

test('payment side-effect jobs configure retries, backoff and timeouts', function () {
    $payment = resilientPayment();

    $financial = new CreateFinancialRecordFromPayment($payment);
    $attendance = new RegisterEventAttendanceFromPayment($payment);
    $mail = new PaymentCompletedJob($payment);

    expect($financial->tries)->toBe(3);
    expect($financial->backoff)->toBe(60);
    expect($financial->timeout)->toBe(30);

    expect($attendance->tries)->toBe(3);
    expect($attendance->timeout)->toBe(30);

    expect($mail->tries)->toBe(3);
    expect($mail->timeout)->toBe(30);

    expect(method_exists($financial, 'failed'))->toBeTrue();
    expect(method_exists($attendance, 'failed'))->toBeTrue();
    expect(method_exists($mail, 'failed'))->toBeTrue();
});

test('a payment never produces duplicate financial records', function () {
    $payment = resilientPayment();

    (new CreateFinancialRecordFromPayment($payment))->handle();
    (new CreateFinancialRecordFromPayment($payment))->handle();

    expect(FinancialRecord::count())->toBe(1);
});