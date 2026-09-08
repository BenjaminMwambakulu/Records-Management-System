<?php

use App\Enums\PaymentStatus;
use App\Models\Event;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::create(['name' => 'member', 'guard_name' => 'web']);
    Role::create(['name' => 'admin', 'guard_name' => 'web']);
    Role::create(['name' => 'executive', 'guard_name' => 'web']);
});

test('payment model belongs to user', function () {
    $user = User::factory()->create();
    $payment = Payment::factory()->create(['user_id' => $user->id]);

    expect($payment->user)->toBeInstanceOf(User::class);
    expect($payment->user->id)->toBe($user->id);
});

test('payment model morphs to payable', function () {
    $event = Event::factory()->create();
    $payment = Payment::factory()->create([
        'payable_type' => Event::class,
        'payable_id' => $event->id,
    ]);

    expect($payment->payable)->toBeInstanceOf(Event::class);
    expect($payment->payable->id)->toBe($event->id);
});

test('event model has payments relationship', function () {
    $event = Event::factory()->create();
    $payment = Payment::factory()->create([
        'payable_type' => Event::class,
        'payable_id' => $event->id,
    ]);

    expect($event->payments)->toHaveCount(1);
    expect($event->payments->first()->id)->toBe($payment->id);
});