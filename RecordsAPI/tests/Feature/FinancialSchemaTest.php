<?php

use App\Enums\TransactionType;
use App\Models\FinancialCategory;
use App\Models\FinancialRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('financial categories table exists with expected columns', function () {
    $columns = Schema::getColumnListing('financial_categories');

    expect($columns)->toContain('id', 'name', 'description', 'created_at', 'updated_at');
});

test('financial records table has category_id and drops old category column', function () {
    $columns = Schema::getColumnListing('financial_records');

    expect($columns)->toContain('category_id', 'title', 'type', 'amount', 'transaction_date', 'recorded_by')
        ->and($columns)->not->toContain('category');
});

test('a financial record belongs to a category', function () {
    $category = FinancialCategory::factory()->create(['name' => 'Membership Fees']);
    $record = FinancialRecord::factory()->create(['category_id' => $category->id]);

    expect($record->category->id)->toBe($category->id)
        ->and($record->type)->toBeInstanceOf(TransactionType::class);
});
