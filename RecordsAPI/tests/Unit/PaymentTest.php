<?php

use App\Enums\PaymentStatus;
use App\Models\Payment;

test('payment model has correct fillable attributes', function () {
    $payment = new Payment();

    expect($payment->getFillable())->toBe([
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
    ]);
});

test('payment model casts status to PaymentStatus enum', function () {
    $payment = new Payment(['status' => 'pending']);

    expect($payment->status)->toBeInstanceOf(PaymentStatus::class);
    expect($payment->status)->toBe(PaymentStatus::PENDING);
});

test('payment model casts amount to decimal', function () {
    $payment = new Payment(['amount' => 100.50]);

    expect($payment->amount)->toBe('100.50');
});

test('payment model casts provider_response to array', function () {
    $payment = new Payment(['provider_response' => ['key' => 'value']]);

    expect($payment->provider_response)->toBe(['key' => 'value']);
});

test('payment model casts metadata to array', function () {
    $payment = new Payment(['metadata' => ['key' => 'value']]);

    expect($payment->metadata)->toBe(['key' => 'value']);
});

test('payment model has paid_at in casts', function () {
    $payment = new Payment();

    $casts = $payment->getCasts();

    expect($casts['paid_at'])->toBe('datetime');
});

test('payment model isPending returns true when status is PENDING', function () {
    $payment = new Payment(['status' => PaymentStatus::PENDING]);

    expect($payment->isPending())->toBeTrue();
});

test('payment model isPending returns false when status is not PENDING', function () {
    $payment = new Payment(['status' => PaymentStatus::COMPLETED]);

    expect($payment->isPending())->toBeFalse();
});

test('payment model isCompleted returns true when status is COMPLETED', function () {
    $payment = new Payment(['status' => PaymentStatus::COMPLETED]);

    expect($payment->isCompleted())->toBeTrue();
});

test('payment model isCompleted returns false when status is not COMPLETED', function () {
    $payment = new Payment(['status' => PaymentStatus::PENDING]);

    expect($payment->isCompleted())->toBeFalse();
});

test('payment model isFailed returns true when status is FAILED', function () {
    $payment = new Payment(['status' => PaymentStatus::FAILED]);

    expect($payment->isFailed())->toBeTrue();
});

test('payment model isFailed returns false when status is not FAILED', function () {
    $payment = new Payment(['status' => PaymentStatus::PENDING]);

    expect($payment->isFailed())->toBeFalse();
});
