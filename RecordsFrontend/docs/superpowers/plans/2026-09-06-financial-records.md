# Financial Records Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the Financial Records subsystem end-to-end — a backend `/api/v1/financial-records` + `/api/v1/financial-categories` API (with a managed category table, summary endpoint, and CSV export) and the frontend `/financial-records` page (summary cards, searchable/filterable list, create/edit/delete, category manager, CSV export).

**Architecture:** Backend mirrors the existing `EventService`/`EventController`/`EventResource` and `DocumentCategoryService`/`DocumentCategoryController` patterns; a new `financial_categories` table with `financial_records.category_id` FK replaces the old free-text `category` column. Writes and export are gated by the already-seeded `financials.*` permissions; reads are open to any authenticated user. Frontend mirrors `useEventsDirectory` + `EventsIndex`/`EventFormDialog` + `DocumentCategoryManagerDialog` and `SummaryCard`.

**Tech Stack:** PHP 8.5 / Laravel (Pest tests), spatie/permission, PostgreSQL. React 18 (Vite), react-router-dom, existing `ui/` primitives (`table`, `dialog`, `button`, `input`, `empty`, `skeleton`, `card`), lucide-react, Tailwind, `api`/`apiFetch` client.

**Spec:** `docs/superpowers/specs/2026-09-06-financial-records-design.md`

## Global Constraints

- **Header doc for BOTH repos:** The project is split across two separate git repos and working trees:
  - Backend repo root: `RecordsAPI/` (Laravel + Pest). Run backend commands there.
  - Frontend repo root: `RecordsFrontend/` (React/Vite). Run frontend commands there.
  - These plan/docs markdown files live in `RecordsFrontend/docs/superpowers/`. **Do not** commit them into the backend repo.
- Backend conventions (from `RecordsAPI/CLAUDE.md`): use `php artisan make:` commands; Pest tests (`php artisan make:test --pest`); run `vendor/bin/pint --dirty --format agent` after modifying PHP; PHP-8 constructor property promotion; explicit return types; PHPDoc array shapes; follow existing sibling-file structure; no new base folders or dependency changes without approval.
- Backend gating: reads (`index`, `show`, `summary`, category `index`/`show`) open to any `auth:logto` user; record writes via `permission:financials.create|update|delete,logto`; category writes via `permission:financials.update|delete,logto`; export via `permission:financials.export,logto`.
- The `financials.*` permissions already exist in `RolesAndPermissionsSeeder` (`financials.view|create|update|delete|export`) and are granted to the `executive` role; `superadmin` gets all. **Do not modify the seeder.**
- Frontend conventions (from `RecordsFrontend`): mirror `useEventsDirectory`/`EventsIndex`/`EventFormDialog`/`DocumentCategoryManagerDialog`; inline error boxes (NOT toast — toast infra is not mounted); icon buttons with `aria-label`; `extractErrorMessage` from `err.body.errors`; amounts formatted with `formatMoney` (already in `src/lib/utils.js`); JSON payloads through `api.get/post/put/delete`; CSV export through `apiFetch` with `{ raw: true }`.
- Frontend verification gates: `npm run lint` (no new warnings) and `npm run build` (succeeds) in `RecordsFrontend`; there is no JS test runner — UI behavior is verified via the manual smoke checklist at the end of each frontend task.
- Backend verification gates: `vendor/bin/pest --filter=...` (targeted tests pass) in `RecordsAPI`; then `vendor/bin/pint --dirty --format agent` to fix formatting.
- Never add code comments/docstrings unless a line absolutely needs explanation — the codebases are comment-free.
- Commit after each task with a conventional-style message, in the repo the task touched.

---

# PART A — BACKEND (RecordsAPI)

Run every Part A command from `RecordsAPI/`. Commit Part A tasks to the **RecordsAPI** git repo.

### Task 1: Schema, models, and factories

**Files:**
- Create: `RecordsAPI/database/migrations/2026_09_06_000001_create_financial_categories_table_and_repoint_financial_records.php`
- Create: `RecordsAPI/app/Models/FinancialCategory.php`
- Modify: `RecordsAPI/app/Models/FinancialRecord.php`
- Create: `RecordsAPI/database/factories/FinancialCategoryFactory.php`
- Create: `RecordsAPI/database/factories/FinancialRecordFactory.php`
- Create: `RecordsAPI/tests/Feature/FinancialSchemaTest.php`

**Interfaces:**
- Consumes: existing `App\Enums\TransactionType` (`income`, `expense`), existing `users` table.
- Produces:
  - `App\Models\FinancialCategory` — fillable `name`, `description`; `records()` HasMany; uses `LogsActivity`. `table` = `financial_categories`.
  - `App\Models\FinancialRecord` — fillable `title`, `type`, `amount`, `transaction_date`, `category_id`, `recorded_by`; casts `type` → `TransactionType`, `amount` → `decimal:2`, `transaction_date` → `date`; relations `category()` (BelongsTo FinancialCategory), `recordedBy()` (BelongsTo User).
  - `App\Models\FinancialCategory::factory()` and `App\Models\FinancialRecord::factory()`.
  - `financial_categories` table + `financial_records.category_id` FK (drops old `category` string).

- [ ] **Step 1: Write the failing schema test**

Create `RecordsAPI/tests/Feature/FinancialSchemaTest.php`:

```php
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
```

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/pest --filter=FinancialSchemaTest`
Expected: FAIL — table `financial_categories` does not exist.

- [ ] **Step 3: Create the migration**

Create `RecordsAPI/database/migrations/2026_09_06_000001_create_financial_categories_table_and_repoint_financial_records.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::table('financial_records', function (Blueprint $table) {
            $table->dropColumn('category');
            $table->foreignId('category_id')
                ->nullable()
                ->after('type')
                ->constrained('financial_categories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('financial_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->string('category')->nullable();
        });

        Schema::dropIfExists('financial_categories');
    }
};
```

- [ ] **Step 4: Create `FinancialCategory` model**

Create `RecordsAPI/app/Models/FinancialCategory.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class FinancialCategory extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'name',
        'description',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty();
    }

    public function records(): HasMany
    {
        return $this->hasMany(FinancialRecord::class);
    }
}
```

- [ ] **Step 5: Update `FinancialRecord` model**

Replace the entire contents of `RecordsAPI/app/Models/FinancialRecord.php` with:

```php
<?php

namespace App\Models;

use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class FinancialRecord extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'title',
        'type',
        'amount',
        'transaction_date',
        'category_id',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'amount' => 'decimal:2',
            'transaction_date' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FinancialCategory::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
```

- [ ] **Step 6: Create the factories**

Create `RecordsAPI/database/factories/FinancialCategoryFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\FinancialCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancialCategory>
 */
class FinancialCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'description' => fake()->sentence(),
        ];
    }
}
```

Create `RecordsAPI/database/factories/FinancialRecordFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\TransactionType;
use App\Models\FinancialCategory;
use App\Models\FinancialRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancialRecord>
 */
class FinancialRecordFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'type' => fake()->randomElement(TransactionType::cases()),
            'amount' => fake()->randomFloat(2, 1, 10000),
            'transaction_date' => fake()->date(),
            'category_id' => FinancialCategory::factory(),
            'recorded_by' => User::factory(),
        ];
    }
}
```

- [ ] **Step 7: Run the migration and tests**

Run: `php artisan migrate --no-interaction`
Run: `vendor/bin/pest --filter=FinancialSchemaTest`
Expected: PASS (3 tests).

- [ ] **Step 8: Run Pint and commit**

Run: `vendor/bin/pint --dirty --format agent`

```bash
git add database/migrations/2026_09_06_000001_create_financial_categories_table_and_repoint_financial_records.php app/Models/FinancialCategory.php app/Models/FinancialRecord.php database/factories/FinancialCategoryFactory.php database/factories/FinancialRecordFactory.php tests/Feature/FinancialSchemaTest.php
git commit -m "feat: add financial categories table, FK on financial records, and factories"
```

---

### Task 2: Services, requests, and resources

**Files:**
- Create: `RecordsAPI/app/Services/FinancialCategoryService.php`
- Create: `RecordsAPI/app/Services/FinancialRecordService.php`
- Create: `RecordsAPI/app/Http/Requests/StoreFinancialCategoryRequest.php`
- Create: `RecordsAPI/app/Http/Requests/UpdateFinancialCategoryRequest.php`
- Create: `RecordsAPI/app/Http/Requests/StoreFinancialRecordRequest.php`
- Create: `RecordsAPI/app/Http/Requests/UpdateFinancialRecordRequest.php`
- Create: `RecordsAPI/app/Http/Resources/FinancialCategoryResource.php`
- Create: `RecordsAPI/app/Http/Resources/FinancialRecordResource.php`
- Create: `RecordsAPI/app/Http/Resources/FinancialSummaryResource.php`

**Interfaces:**
- Consumes: models from Task 1; `App\Enums\TransactionType`.
- Produces:
  - `FinancialCategoryService`: `list(): Collection`, `find(int): ?FinancialCategory`, `create(array): FinancialCategory`, `update(FinancialCategory, array): FinancialCategory`, `delete(FinancialCategory): void` (mirrors `DocumentCategoryService`).
  - `FinancialRecordService`: `list(array $filters): LengthAwarePaginator` (filters `search`, `type`, `category_id`, `per_page`); `find(int): ?FinancialRecord`; `create(array): FinancialRecord` (requires `recorded_by`); `update(FinancialRecord, array): FinancialRecord`; `delete(FinancialRecord): void`. Static/plain methods used by controllers.
  - Resources expose shapes from the spec (record: `id,title,type,amount,transaction_date,category{id,name},recorded_by{id,name},created_at,updated_at`).
  - Requests carry validation rules (see steps).

- [ ] **Step 1: Create `FinancialCategoryService`**

Create `RecordsAPI/app/Services/FinancialCategoryService.php` (mirror `DocumentCategoryService`):

```php
<?php

namespace App\Services;

use App\Models\FinancialCategory;
use Illuminate\Database\Eloquent\Collection;

class FinancialCategoryService
{
    public function list(): Collection
    {
        return FinancialCategory::query()
            ->orderBy('name')
            ->get();
    }

    public function find(int $id): ?FinancialCategory
    {
        return FinancialCategory::find($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): FinancialCategory
    {
        return FinancialCategory::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(FinancialCategory $category, array $data): FinancialCategory
    {
        $category->update($data);

        return $category;
    }

    public function delete(FinancialCategory $category): void
    {
        $category->delete();
    }
}
```

- [ ] **Step 2: Create `FinancialRecordService`**

Create `RecordsAPI/app/Services/FinancialRecordService.php`:

```php
<?php

namespace App\Services;

use App\Models\FinancialCategory;
use App\Models\FinancialRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class FinancialRecordService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        return FinancialRecord::query()
            ->with(['category', 'recordedBy'])
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where('title', 'ilike', "%{$search}%");
            })
            ->when($filters['type'] ?? null, function (Builder $query, string $type): void {
                $query->where('type', $type);
            })
            ->when($filters['category_id'] ?? null, function (Builder $query, $categoryId): void {
                $query->where('category_id', $categoryId);
            })
            ->latest('transaction_date')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function find(int $id): ?FinancialRecord
    {
        return FinancialRecord::with(['category', 'recordedBy'])->find($id);
    }

    /**
     * @return array{total_income: string, total_expense: string, balance: string}
     */
    public function summary(): array
    {
        $income = FinancialRecord::query()->where('type', 'income')->sum('amount');
        $expense = FinancialRecord::query()->where('type', 'expense')->sum('amount');

        return [
            'total_income' => number_format((float) $income, 2, '.', ''),
            'total_expense' => number_format((float) $expense, 2, '.', ''),
            'balance' => number_format((float) $income - (float) $expense, 2, '.', ''),
        ];
    }

    /**
     * Same filters as list() but unpaginated, ordered by date. Used by export.
     *
     * @param  array<string, mixed>  $filters
     * @return \Illuminate\Database\Eloquent\Collection<int, FinancialRecord>
     */
    public function filteredForExport(array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        return FinancialRecord::query()
            ->with(['category', 'recordedBy'])
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where('title', 'ilike', "%{$search}%");
            })
            ->when($filters['type'] ?? null, function (Builder $query, string $type): void {
                $query->where('type', $type);
            })
            ->when($filters['category_id'] ?? null, function (Builder $query, $categoryId): void {
                $query->where('category_id', $categoryId);
            })
            ->latest('transaction_date')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): FinancialRecord
    {
        return FinancialRecord::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(FinancialRecord $record, array $data): FinancialRecord
    {
        $record->update($data);

        return $record;
    }

    public function delete(FinancialRecord $record): void
    {
        $record->delete();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, FinancialRecord>  $records
     * @return resource
     */
    public function csvStream($records)
    {
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, ['Title', 'Category', 'Type', 'Amount', 'Transaction Date', 'Recorded By']);

        foreach ($records as $record) {
            fputcsv($handle, [
                $record->title,
                $record->category?->name ?? '',
                $record->type->value,
                $record->amount,
                $record->transaction_date?->toDateString() ?? '',
                $record->recordedBy?->name ?? '',
            ]);
        }

        rewind($handle);

        return $handle;
    }
}
```

- [ ] **Step 3: Create the category request classes**

Create `RecordsAPI/app/Http/Requests/StoreFinancialCategoryRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreFinancialCategoryRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255', 'unique:financial_categories,name'],
            'description' => ['nullable', 'string'],
        ];
    }
}
```

Create `RecordsAPI/app/Http/Requests/UpdateFinancialCategoryRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFinancialCategoryRequest extends FormRequest
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
        $category = $this->route('financial_category');

        return [
            'name' => ['sometimes', 'string', 'max:255', 'unique:financial_categories,name,'.$category],
            'description' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
```

- [ ] **Step 4: Create the record request classes**

Create `RecordsAPI/app/Http/Requests/StoreFinancialRecordRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreFinancialRecordRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:income,expense'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'transaction_date' => ['required', 'date'],
            'category_id' => ['nullable', 'integer', 'exists:financial_categories,id'],
        ];
    }
}
```

Create `RecordsAPI/app/Http/Requests/UpdateFinancialRecordRequest.php` (same rules as store):

```php
<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFinancialRecordRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:income,expense'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'transaction_date' => ['required', 'date'],
            'category_id' => ['nullable', 'integer', 'exists:financial_categories,id'],
        ];
    }
}
```

- [ ] **Step 5: Create the resources**

Create `RecordsAPI/app/Http/Resources/FinancialCategoryResource.php`:

```php
<?php

namespace App\Http\Resources;

use App\Models\FinancialCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FinancialCategory */
class FinancialCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
```

Create `RecordsAPI/app/Http/Resources/FinancialSummaryResource.php`:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \ArrayObject */
class FinancialSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'total_income' => $this['total_income'],
            'total_expense' => $this['total_expense'],
            'balance' => $this['balance'],
        ];
    }
}
```

Create `RecordsAPI/app/Http/Resources/FinancialRecordResource.php`:

```php
<?php

namespace App\Http\Resources;

use App\Models\FinancialRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FinancialRecord */
class FinancialRecordResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type->value,
            'amount' => $this->amount,
            'transaction_date' => $this->transaction_date?->toDateString(),
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category?->id,
                'name' => $this->category?->name,
            ]),
            'recorded_by' => $this->whenLoaded('recordedBy', fn () => [
                'id' => $this->recordedBy?->id,
                'name' => $this->recordedBy?->name,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
```

- [ ] **Step 6: Sanity check the code compiles**

Run: `php -l app/Services/FinancialRecordService.php; php -l app/Services/FinancialCategoryService.php; php -l app/Http/Resources/FinancialRecordResource.php; php -l app/Http/Resources/FinancialCategoryResource.php; php -l app/Http/Resources/FinancialSummaryResource.php`
Expected: no syntax errors. (These classes are only exercised end-to-end in Task 3's tests.)

- [ ] **Step 7: Run Pint and commit**

Run: `vendor/bin/pint --dirty --format agent`

```bash
git add app/Services/FinancialCategoryService.php app/Services/FinancialRecordService.php app/Http/Requests/StoreFinancialCategoryRequest.php app/Http/Requests/UpdateFinancialCategoryRequest.php app/Http/Requests/StoreFinancialRecordRequest.php app/Http/Requests/UpdateFinancialRecordRequest.php app/Http/Resources/FinancialCategoryResource.php app/Http/Resources/FinancialRecordResource.php app/Http/Resources/FinancialSummaryResource.php
git commit -m "feat: add financial services, form requests, and API resources"
```

---

### Task 3: Controllers and routes (with tests)

**Files:**
- Create: `RecordsAPI/app/Http/Controllers/Api/FinancialCategoryController.php`
- Create: `RecordsAPI/app/Http/Controllers/Api/FinanceController.php`
- Modify: `RecordsAPI/routes/api.php`
- Create: `RecordsAPI/tests/Feature/FinancialRecordApiTest.php`
- Create: `RecordsAPI/tests/Feature/FinancialCategoryApiTest.php`
- Create: `RecordsAPI/tests/Feature/FinancialExportTest.php`

**Interfaces:**
- Consumes: Task 2 services/requests/resources; `App\Http\Responses\APIResponse` trait; `auth:logto` Guard against Logto JWT.
- Produces: the REST endpoints from the spec. `FinanceController` methods take `int $id` (matching `EventController`), and record write routes use `{record}` as the param name (matching the controllers). `Route::apiResource('financial-categories', ...)` generates the route param `financial_category` — this is what `UpdateFinancialCategoryRequest` reads via `$this->route('financial_category')`.

- [ ] **Step 1: Write the failing records API tests**

Create `RecordsAPI/tests/Feature/FinancialRecordApiTest.php` (mirror the authentication + gating setup from `DocumentApiTest`; use the `executive` role which carries `financials.*`):

```php
<?php

use App\Models\FinancialCategory;
use App\Models\FinancialRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\JwtTestHelper;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.logto.endpoint' => 'https://logto.test',
        'services.logto.issuer' => 'https://logto.test/oidc',
        'services.logto.api_resource' => 'https://api.test',
    ]);

    Cache::flush();

    $this->keys = JwtTestHelper::keyPair();

    Http::fake([
        'https://logto.test/oidc/jwks' => Http::response([
            'keys' => [JwtTestHelper::jwk($this->keys['public_pem'], $this->keys['kid'])],
        ]),
    ]);
});

function financeToken(array $keys, string $logtoId = 'logto-finance'): string
{
    return JwtTestHelper::sign(JwtTestHelper::claims($logtoId), $keys['private_pem'], $keys['kid']);
}

function createExecutiveRole(): Role
{
    $role = Role::create(['name' => 'executive', 'guard_name' => 'logto']);

    foreach (['financials.create', 'financials.update', 'financials.delete', 'financials.export'] as $perm) {
        $role->givePermissionTo(Permission::findOrCreate($perm, 'logto'));
    }

    return $role;
}

test('financial routes require authentication', function () {
    $this->getJson('/api/v1/financial-records')->assertStatus(401);
    $this->postJson('/api/v1/financial-records', [])->assertStatus(401);
    $this->getJson('/api/v1/financial-categories')->assertStatus(401);
});

test('any authenticated member can list financial records', function () {
    User::factory()->create(['logto_id' => 'logto-viewer']);

    $income = FinancialRecord::factory()->create([
        'type' => 'income',
        'amount' => '1000.00',
        'transaction_date' => '2026-08-01',
    ]);
    $expense = FinancialRecord::factory()->create([
        'type' => 'expense',
        'amount' => '250.00',
        'transaction_date' => '2026-09-01',
    ]);

    $this->withHeader('Authorization', 'Bearer '.financeToken($this->keys, 'logto-viewer'))
        ->getJson('/api/v1/financial-records')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $expense->id)
        ->assertJsonPath('data.1.id', $income->id);
});

test('financial records can be filtered by type and category', function () {
    User::factory()->create(['logto_id' => 'logto-viewer']);

    $fees = FinancialCategory::factory()->create(['name' => 'Membership Fees']);
    FinancialRecord::factory()->create(['type' => 'income', 'category_id' => $fees->id, 'title' => 'Member Dues']);
    FinancialRecord::factory()->create(['type' => 'expense']);

    $this->withHeader('Authorization', 'Bearer '.financeToken($this->keys, 'logto-viewer'))
        ->getJson('/api/v1/financial-records?type=income&category_id='.$fees->id)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Member Dues');
});

test('creating a financial record requires title, type, amount, and date', function () {
    $executive = User::factory()->create(['logto_id' => 'logto-finance']);
    $executive->assignRole(createExecutiveRole());

    $this->withHeader('Authorization', 'Bearer '.financeToken($this->keys))
        ->postJson('/api/v1/financial-records', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['title', 'type', 'amount', 'transaction_date']);
});

test('executive can create a financial record and it records the author', function () {
    $executive = User::factory()->create(['logto_id' => 'logto-finance', 'first_name' => 'Patsy']);
    $executive->assignRole(createExecutiveRole());
    $fees = FinancialCategory::factory()->create(['name' => 'Membership Fees']);

    $this->withHeader('Authorization', 'Bearer '.financeToken($this->keys))
        ->postJson('/api/v1/financial-records', [
            'title' => 'Registration Fees',
            'type' => 'income',
            'amount' => '5000.00',
            'transaction_date' => '2026-09-01',
            'category_id' => $fees->id,
        ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Registration Fees')
        ->assertJsonPath('data.type', 'income')
        ->assertJsonPath('data.category.name', 'Membership Fees')
        ->assertJsonPath('data.recorded_by.id', $executive->id);

    expect(FinancialRecord::where('title', 'Registration Fees')->firstOrFail()->recorded_by)->toBe($executive->id);
});

test('member cannot create financial records', function () {
    Role::create(['name' => 'member', 'guard_name' => 'logto']);
    $member = User::factory()->create(['logto_id' => 'logto-member']);
    $member->assignRole(Role::where('name', 'member')->where('guard_name', 'logto')->first());

    $this->withHeader('Authorization', 'Bearer '.financeToken($this->keys, 'logto-member'))
        ->postJson('/api/v1/financial-records', [
            'title' => 'Nope',
            'type' => 'income',
            'amount' => '10.00',
            'transaction_date' => '2026-09-01',
        ])
        ->assertForbidden();
});

test('executive can update and delete a financial record', function () {
    $executive = User::factory()->create(['logto_id' => 'logto-finance']);
    $executive->assignRole(createExecutiveRole());
    $token = 'Bearer '.financeToken($this->keys);

    $record = FinancialRecord::factory()->create(['title' => 'Old Title']);

    $this->withHeader('Authorization', $token)
        ->putJson("/api/v1/financial-records/{$record->id}", [
            'title' => 'New Title',
            'type' => 'expense',
            'amount' => '99.00',
            'transaction_date' => '2026-09-02',
        ])
        ->assertOk()
        ->assertJsonPath('data.title', 'New Title');

    $this->withHeader('Authorization', $token)
        ->deleteJson("/api/v1/financial-records/{$record->id}")
        ->assertOk();

    expect(FinancialRecord::find($record->id))->toBeNull();
});

test('summary returns income, expense, and balance', function () {
    User::factory()->create(['logto_id' => 'logto-viewer']);

    FinancialRecord::factory()->create(['type' => 'income', 'amount' => '1000.00']);
    FinancialRecord::factory()->create(['type' => 'income', 'amount' => '500.00']);
    FinancialRecord::factory()->create(['type' => 'expense', 'amount' => '300.00']);

    $this->withHeader('Authorization', 'Bearer '.financeToken($this->keys, 'logto-viewer'))
        ->getJson('/api/v1/financial-records/summary')
        ->assertOk()
        ->assertJsonPath('data.total_income', '1500.00')
        ->assertJsonPath('data.total_expense', '300.00')
        ->assertJsonPath('data.balance', '1200.00');
});
```

- [ ] **Step 2: Write the failing category API tests**

Create `RecordsAPI/tests/Feature/FinancialCategoryApiTest.php`:

```php
<?php

use App\Models\FinancialCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\JwtTestHelper;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.logto.endpoint' => 'https://logto.test',
        'services.logto.issuer' => 'https://logto.test/oidc',
        'services.logto.api_resource' => 'https://api.test',
    ]);

    Cache::flush();

    $this->keys = JwtTestHelper::keyPair();

    Http::fake([
        'https://logto.test/oidc/jwks' => Http::response([
            'keys' => [JwtTestHelper::jwk($this->keys['public_pem'], $this->keys['kid'])],
        ]),
    ]);
});

function financeCategoryToken(array $keys, string $logtoId = 'logto-finance'): string
{
    return JwtTestHelper::sign(JwtTestHelper::claims($logtoId), $keys['private_pem'], $keys['kid']);
}

function createCategoryExecutiveRole(): Role
{
    $role = Role::create(['name' => 'executive', 'guard_name' => 'logto']);

    foreach (['financials.update', 'financials.delete'] as $perm) {
        $role->givePermissionTo(Permission::findOrCreate($perm, 'logto'));
    }

    return $role;
}

test('any authenticated member can list financial categories', function () {
    User::factory()->create(['logto_id' => 'logto-viewer']);
    FinancialCategory::factory()->create(['name' => 'B']);
    FinancialCategory::factory()->create(['name' => 'A']);

    $this->withHeader('Authorization', 'Bearer '.financeCategoryToken($this->keys, 'logto-viewer'))
        ->getJson('/api/v1/financial-categories')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'A')
        ->assertJsonPath('data.1.name', 'B');
});

test('executive can create and update a financial category', function () {
    $executive = User::factory()->create(['logto_id' => 'logto-finance']);
    $executive->assignRole(createCategoryExecutiveRole());
    $token = 'Bearer '.financeCategoryToken($this->keys);

    $this->withHeader('Authorization', $token)
        ->postJson('/api/v1/financial-categories', ['name' => 'Equipment'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Equipment');

    $category = FinancialCategory::where('name', 'Equipment')->firstOrFail();

    $this->withHeader('Authorization', $token)
        ->putJson("/api/v1/financial-categories/{$category->id}", ['name' => 'Equipment Upgrades'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Equipment Upgrades');
});

test('executive can delete a financial category and records are nulled', function () {
    $executive = User::factory()->create(['logto_id' => 'logto-finance']);
    $executive->assignRole(createCategoryExecutiveRole());
    $token = 'Bearer '.financeCategoryToken($this->keys);

    $category = FinancialCategory::factory()->create(['name' => 'Temp']);

    $this->withHeader('Authorization', $token)
        ->deleteJson("/api/v1/financial-categories/{$category->id}")
        ->assertOk();

    expect(FinancialCategory::find($category->id))->toBeNull();
});

test('member cannot create financial categories', function () {
    Role::create(['name' => 'member', 'guard_name' => 'logto']);
    $member = User::factory()->create(['logto_id' => 'logto-member']);
    $member->assignRole(Role::where('name', 'member')->where('guard_name', 'logto')->first());

    $this->withHeader('Authorization', 'Bearer '.financeCategoryToken($this->keys, 'logto-member'))
        ->postJson('/api/v1/financial-categories', ['name' => 'Nope'])
        ->assertForbidden();
});
```

- [ ] **Step 3: Write the failing export test**

Create `RecordsAPI/tests/Feature/FinancialExportTest.php`:

```php
<?php

use App\Models\FinancialCategory;
use App\Models\FinancialRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\JwtTestHelper;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.logto.endpoint' => 'https://logto.test',
        'services.logto.issuer' => 'https://logto.test/oidc',
        'services.logto.api_resource' => 'https://api.test',
    ]);

    Cache::flush();

    $this->keys = JwtTestHelper::keyPair();

    Http::fake([
        'https://logto.test/oidc/jwks' => Http::response([
            'keys' => [JwtTestHelper::jwk($this->keys['public_pem'], $this->keys['kid'])],
        ]),
    ]);
});

function financeExportToken(array $keys, string $logtoId = 'logto-finance'): string
{
    return JwtTestHelper::sign(JwtTestHelper::claims($logtoId), $keys['private_pem'], $keys['kid']);
}

function createExportRole(): Role
{
    $role = Role::create(['name' => 'executive', 'guard_name' => 'logto']);
    $role->givePermissionTo(Permission::findOrCreate('financials.export', 'logto'));

    return $role;
}

test('member cannot export financial records', function () {
    Role::create(['name' => 'member', 'guard_name' => 'logto']);
    $member = User::factory()->create(['logto_id' => 'logto-member']);
    $member->assignRole(Role::where('name', 'member')->where('guard_name', 'logto')->first());

    $this->withHeader('Authorization', 'Bearer '.financeExportToken($this->keys, 'logto-member'))
        ->getJson('/api/v1/financial-records/export')
        ->assertForbidden();
});

test('executive can export financial records as CSV', function () {
    $executive = User::factory()->create(['logto_id' => 'logto-finance']);
    $executive->assignRole(createExportRole());
    $fees = FinancialCategory::factory()->create(['name' => 'Membership Fees']);
    FinancialRecord::factory()->create([
        'title' => 'Dues',
        'category_id' => $fees->id,
        'type' => 'income',
        'amount' => '500.00',
        'transaction_date' => '2026-09-01',
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.financeExportToken($this->keys))
        ->get('/api/v1/financial-records/export')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $csv = $response->streamedContent();

    expect($csv)->toContain('Title,Category,Type,Amount,Transaction Date,Recorded By')
        ->and($csv)->toContain('Dues,Membership Fees,income,500.00,2026-09-01');
});
```

- [ ] **Step 4: Run the tests to verify they fail**

Run: `vendor/bin/pest --filter="FinancialRecordApiTest|FinancialCategoryApiTest|FinancialExportTest"`
Expected: FAIL — routes/controllers do not exist (404 / method not found).

- [ ] **Step 5: Create `FinancialCategoryController`**

Create `RecordsAPI/app/Http/Controllers/Api/FinancialCategoryController.php` (mirror `DocumentCategoryController`; route param named `category` so `route('category')` resolves in the update request):

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFinancialCategoryRequest;
use App\Http\Requests\UpdateFinancialCategoryRequest;
use App\Http\Resources\FinancialCategoryResource;
use App\Http\Responses\APIResponse;
use App\Services\FinancialCategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancialCategoryController extends Controller
{
    use APIResponse;

    public function __construct(
        protected FinancialCategoryService $categoryService,
    ) {
        $this->middleware('permission:financials.update|financials.delete,logto')->except('index', 'show');
    }

    public function index(Request $request): JsonResponse
    {
        $categories = $this->categoryService->list();

        return $this->success(
            FinancialCategoryResource::collection($categories),
            'Financial categories retrieved successfully'
        );
    }

    public function show(int $id): JsonResponse
    {
        $category = $this->categoryService->find($id);

        if (! $category) {
            return $this->error('Financial category not found', JsonResponse::HTTP_NOT_FOUND);
        }

        return $this->success(
            new FinancialCategoryResource($category),
            'Financial category retrieved successfully'
        );
    }

    public function store(StoreFinancialCategoryRequest $request): JsonResponse
    {
        $category = $this->categoryService->create($request->validated());

        return $this->success(
            new FinancialCategoryResource($category),
            'Financial category created successfully',
            JsonResponse::HTTP_CREATED,
        );
    }

    public function update(UpdateFinancialCategoryRequest $request, int $id): JsonResponse
    {
        $category = $this->categoryService->find($id);

        if (! $category) {
            return $this->error('Financial category not found', JsonResponse::HTTP_NOT_FOUND);
        }

        $this->categoryService->update($category, $request->validated());

        return $this->success(
            new FinancialCategoryResource($category->refresh()),
            'Financial category updated successfully'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $category = $this->categoryService->find($id);

        if (! $category) {
            return $this->error('Financial category not found', JsonResponse::HTTP_NOT_FOUND);
        }

        $this->categoryService->delete($category);

        return $this->success(null, 'Financial category deleted successfully');
    }
}
```

- [ ] **Step 6: Create `FinanceController`**

Create `RecordsAPI/app/Http/Controllers/Api/FinanceController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFinancialRecordRequest;
use App\Http\Requests\UpdateFinancialRecordRequest;
use App\Http\Resources\FinancialRecordResource;
use App\Http\Resources\FinancialSummaryResource;
use App\Http\Responses\APIResponse;
use App\Services\FinancialRecordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceController extends Controller
{
    use APIResponse;

    public function __construct(
        protected FinancialRecordService $recordService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $records = $this->recordService->list($request->only(['search', 'type', 'category_id', 'per_page']));

        return $this->success(
            FinancialRecordResource::collection($records),
            'Financial records retrieved successfully'
        );
    }

    public function show(int $id): JsonResponse
    {
        $record = $this->recordService->find($id);

        if (! $record) {
            return $this->error('Financial record not found', JsonResponse::HTTP_NOT_FOUND);
        }

        return $this->success(
            new FinancialRecordResource($record),
            'Financial record retrieved successfully'
        );
    }

    public function store(StoreFinancialRecordRequest $request): JsonResponse
    {
        $record = $this->recordService->create([
            ...$request->validated(),
            'recorded_by' => $request->user()->id,
        ]);

        return $this->success(
            new FinancialRecordResource($record->load(['category', 'recordedBy'])),
            'Financial record created successfully',
            JsonResponse::HTTP_CREATED,
        );
    }

    public function update(UpdateFinancialRecordRequest $request, int $id): JsonResponse
    {
        $record = $this->recordService->find($id);

        if (! $record) {
            return $this->error('Financial record not found', JsonResponse::HTTP_NOT_FOUND);
        }

        $this->recordService->update($record, $request->validated());

        return $this->success(
            new FinancialRecordResource($record->refresh()->load(['category', 'recordedBy'])),
            'Financial record updated successfully'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $record = $this->recordService->find($id);

        if (! $record) {
            return $this->error('Financial record not found', JsonResponse::HTTP_NOT_FOUND);
        }

        $this->recordService->delete($record);

        return $this->success(null, 'Financial record deleted successfully');
    }

    public function summary(Request $request): JsonResponse
    {
        return $this->success(
            new FinancialSummaryResource($this->recordService->summary()),
            'Financial summary retrieved successfully'
        );
    }

    public function export(Request $request): StreamedResponse
    {
        $records = $this->recordService->filteredForExport(
            $request->only(['search', 'type', 'category_id'])
        );

        $stream = $this->recordService->csvStream($records);

        return response()->streamDownload(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 'financial-records.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
```

- [ ] **Step 7: Add the routes**

Edit `RecordsAPI/routes/api.php`. Add these imports at the top (after the `DocumentCategoryController` import):

```php
use App\Http\Controllers\Api\FinanceController;
use App\Http\Controllers\Api\FinancialCategoryController;
```

Add the `financials` write/export gates and routes. Replace the section:

```php
    Route::apiResource('documents', DocumentController::class);
    Route::post('documents/{document}/versions', [DocumentController::class, 'storeVersion']);
    Route::get('documents/{document}/versions', [DocumentController::class, 'listVersions']);
    Route::get('documents/{document}/versions/{version}', [DocumentController::class, 'showVersion']);
    Route::apiResource('document-categories', DocumentCategoryController::class);
```

with:

```php
    Route::apiResource('documents', DocumentController::class);
    Route::post('documents/{document}/versions', [DocumentController::class, 'storeVersion']);
    Route::get('documents/{document}/versions', [DocumentController::class, 'listVersions']);
    Route::get('documents/{document}/versions/{version}', [DocumentController::class, 'showVersion']);
    Route::apiResource('document-categories', DocumentCategoryController::class);

    Route::get('financial-records/summary', [FinanceController::class, 'summary']);
    Route::get('financial-records/export', [FinanceController::class, 'export'])
        ->middleware('permission:financials.export,logto');
    Route::post('financial-records', [FinanceController::class, 'store'])
        ->middleware('permission:financials.create,logto');
    Route::put('financial-records/{record}', [FinanceController::class, 'update'])
        ->middleware('permission:financials.update,logto');
    Route::delete('financial-records/{record}', [FinanceController::class, 'destroy'])
        ->middleware('permission:financials.delete,logto');
    Route::get('financial-records/{record}', [FinanceController::class, 'show']);
    Route::get('financial-records', [FinanceController::class, 'index']);
    Route::apiResource('financial-categories', FinancialCategoryController::class);
```

**Ordering note:** The `financial-records/export` and `financial-records/summary` GET routes MUST be registered before the generic `financial-records/{record}` route so the literal segments match first. The block above achieves this.

- [ ] **Step 8: Run the tests to verify they pass**

Run: `vendor/bin/pest --filter="FinancialRecordApiTest|FinancialCategoryApiTest|FinancialExportTest"`
Expected: PASS (17 tests).

- [ ] **Step 9: Run the full suite and Pint**

Run: `vendor/bin/pest --compact`
Expected: all existing + new tests green.

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 10: Commit**

```bash
git add app/Http/Controllers/Api/FinancialCategoryController.php app/Http/Controllers/Api/FinanceController.php routes/api.php tests/Feature/FinancialRecordApiTest.php tests/Feature/FinancialCategoryApiTest.php tests/Feature/FinancialExportTest.php
git commit -m "feat: add financial records and categories API with summary and CSV export"
```

---

# PART B — FRONTEND (RecordsFrontend)

Run every Part B command from `RecordsFrontend/`. Commit Part B tasks to the **RecordsFrontend** git repo.

### Task 4: Route, roles helper, and placeholder page

**Files:**
- Modify: `RecordsFrontend/src/lib/roles.js`
- Modify: `RecordsFrontend/src/App.jsx`
- Create: `RecordsFrontend/src/Pages/FinancialDirectory/FinancialRecordsIndex.jsx` (placeholder)

**Interfaces:**
- Consumes: nothing new. `user` from `useAuth`.
- Produces:
  - `isFinancialManager(user): boolean` in `src/lib/roles.js` (true when `user.roles` includes `super_admin` or `org_admin`).
  - Route `financial-records` → `Pages/FinancialDirectory/FinancialRecordsIndex`.

- [ ] **Step 1: Add `isFinancialManager` to `src/lib/roles.js`**

Append to `RecordsFrontend/src/lib/roles.js`:

```js
export function isFinancialManager(user) {
  return isAdminUser(user);
}
```

- [ ] **Step 2: Create the placeholder page**

Create `RecordsFrontend/src/Pages/FinancialDirectory/FinancialRecordsIndex.jsx`:

```jsx
export default function FinancialRecordsIndex() {
  return <div>Financial Records</div>;
}
```

- [ ] **Step 3: Register the route in `src/App.jsx`**

Add import after the `LogsIndex` import:

```jsx
import FinancialRecordsIndex from './Pages/FinancialDirectory/FinancialRecordsIndex';
```

Add inside the authenticated `<Layout>` route, after the `logs` route:

```jsx
<Route path="financial-records" element={<FinancialRecordsIndex />} />
```

- [ ] **Step 4: Verify lint/build**

Run: `npm run lint`
Expected: no new warnings.

Run: `npm run build`
Expected: build succeeds.

- [ ] **Step 5: Commit**

```bash
git add src/lib/roles.js src/App.jsx src/Pages/FinancialDirectory/FinancialRecordsIndex.jsx
git commit -m "feat: add financial records route and role gating helper"
```

---

### Task 5: `useFinancialDirectory` hook

**Files:**
- Create: `RecordsFrontend/src/hooks/useFinancialDirectory.js`

**Interfaces:**
- Consumes: `api` and `apiFetch` from `@/APIClients/APIClient`.
- Produces (consumed by Tasks 6–8):
  - State: `records` (array), `pagination` (object|null), `summary` (object|null: `{total_income,total_expense,balance}`), `isLoading`, `error`, `search`, `setSearch`, `type` (`""|"income"|"expense"`), `setType`, `categoryId`, `setCategoryId`, `page`, `setPage`, `categories` (array), `categoriesError`, `isExporting`.
  - `refetch()` — GET `/v1/financial-records` with filters.
  - `fetchSummary()` — GET `/v1/financial-records/summary`; sets `summary` from `response.data`.
  - `fetchCategories()` — GET `/v1/financial-categories`.
  - `createRecord(payload)`, `updateRecord(id, payload)`, `deleteRecord(id)` — JSON CRUD + refetch.
  - `exportCsv()` — `apiFetch` with `{ raw: true }`, blob-download; sets/clears `isExporting`; throws on failure.
  - `createCategory({name, description})`, `updateCategory(id, payload)`, `deleteCategory(id)` — JSON CRUD + refetch categories.

- [ ] **Step 1: Write the hook**

Create `RecordsFrontend/src/hooks/useFinancialDirectory.js`:

```js
import { useCallback, useEffect, useRef, useState } from "react";
import { api, apiFetch } from "@/APIClients/APIClient";

const DEFAULT_PER_PAGE = 15;
const SEARCH_DEBOUNCE_MS = 400;

function unwrapList(body) {
  if (Array.isArray(body)) return body;
  return Array.isArray(body?.data) ? body.data : [];
}

export default function useFinancialDirectory() {
  const [records, setRecords] = useState([]);
  const [pagination, setPagination] = useState(null);
  const [summary, setSummary] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState(null);
  const [search, setSearch] = useState("");
  const [debouncedSearch, setDebouncedSearch] = useState("");
  const [type, setType] = useState("");
  const [categoryId, setCategoryId] = useState("");
  const [page, setPage] = useState(1);
  const [categories, setCategories] = useState([]);
  const [categoriesError, setCategoriesError] = useState(null);
  const [isExporting, setIsExporting] = useState(false);
  const cancelledRef = useRef(false);

  useEffect(() => {
    const timer = setTimeout(() => {
      setDebouncedSearch(search);
      setPage(1);
    }, SEARCH_DEBOUNCE_MS);
    return () => clearTimeout(timer);
  }, [search]);

  useEffect(() => {
    setPage(1);
  }, [type, categoryId]);

  const refetch = useCallback(() => {
    cancelledRef.current = false;
    setError(null);
    setIsLoading(true);

    const params = new URLSearchParams({
      page: String(page),
      per_page: String(DEFAULT_PER_PAGE),
    });
    if (debouncedSearch.trim()) params.set("search", debouncedSearch.trim());
    if (type) params.set("type", type);
    if (categoryId) params.set("category_id", categoryId);

    api
      .get(`/v1/financial-records?${params.toString()}`)
      .then((response) => {
        if (cancelledRef.current) return;
        const body = response.data;
        const list = unwrapList(body);
        setRecords(list);
        setPagination(
          Array.isArray(body)
            ? {
                current_page: 1,
                last_page: 1,
                total: list.length,
                per_page: list.length,
                from: list.length ? 1 : 0,
                to: list.length,
              }
            : body
        );
      })
      .catch((err) => {
        if (!cancelledRef.current) setError(err);
      })
      .finally(() => {
        if (!cancelledRef.current) setIsLoading(false);
      });
  }, [debouncedSearch, type, categoryId, page]);

  useEffect(() => {
    refetch();
    return () => {
      cancelledRef.current = true;
    };
  }, [refetch]);

  const fetchSummary = useCallback(() => {
    api
      .get("/v1/financial-records/summary")
      .then((response) => {
        if (!cancelledRef.current) setSummary(response?.data ?? null);
      })
      .catch(() => {
        if (!cancelledRef.current) setSummary(null);
      });
  }, []);

  const fetchCategories = useCallback(() => {
    setCategoriesError(null);
    api
      .get("/v1/financial-categories")
      .then((response) => {
        setCategories(unwrapList(response.data));
      })
      .catch((err) => setCategoriesError(err));
  }, []);

  useEffect(() => {
    fetchSummary();
    fetchCategories();
  }, [fetchSummary, fetchCategories]);

  const createRecord = useCallback(
    async (data) => {
      const response = await api.post("/v1/financial-records", data);
      await refetch();
      await fetchSummary();
      return response;
    },
    [refetch, fetchSummary]
  );

  const updateRecord = useCallback(
    async (id, data) => {
      const response = await api.put(`/v1/financial-records/${id}`, data);
      await refetch();
      await fetchSummary();
      return response;
    },
    [refetch, fetchSummary]
  );

  const deleteRecord = useCallback(
    async (id) => {
      const response = await api.delete(`/v1/financial-records/${id}`);
      await refetch();
      await fetchSummary();
      return response;
    },
    [refetch, fetchSummary]
  );

  const exportCsv = useCallback(async () => {
    setIsExporting(true);
    try {
      const params = new URLSearchParams();
      if (debouncedSearch.trim()) params.set("search", debouncedSearch.trim());
      if (type) params.set("type", type);
      if (categoryId) params.set("category_id", categoryId);
      const qs = params.toString();
      const response = await apiFetch(
        `/v1/financial-records/export${qs ? `?${qs}` : ""}`,
        { raw: true }
      );
      const blob = await response.blob();
      const url = URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = url;
      link.download = "financial-records.csv";
      document.body.appendChild(link);
      link.click();
      link.remove();
      URL.revokeObjectURL(url);
    } finally {
      setIsExporting(false);
    }
  }, [debouncedSearch, type, categoryId]);

  const createCategory = useCallback(async (data) => {
    const response = await api.post("/v1/financial-categories", data);
    await fetchCategories();
    return response;
  }, [fetchCategories]);

  const updateCategory = useCallback(async (id, data) => {
    const response = await api.put(`/v1/financial-categories/${id}`, data);
    await fetchCategories();
    return response;
  }, [fetchCategories]);

  const deleteCategory = useCallback(async (id) => {
    const response = await api.delete(`/v1/financial-categories/${id}`);
    await fetchCategories();
    return response;
  }, [fetchCategories]);

  return {
    records,
    pagination,
    summary,
    isLoading,
    error,
    search,
    setSearch,
    type,
    setType,
    categoryId,
    setCategoryId,
    page,
    setPage,
    categories,
    categoriesError,
    isExporting,
    refetch,
    fetchSummary,
    exportCsv,
    createRecord,
    updateRecord,
    deleteRecord,
    createCategory,
    updateCategory,
    deleteCategory,
  };
}
```

- [ ] **Step 2: Verify lint/build**

Run: `npm run lint` — no new warnings.
Run: `npm run build` — succeeds.

- [ ] **Step 3: Commit**

```bash
git add src/hooks/useFinancialDirectory.js
git commit -m "feat: add useFinancialDirectory data hook"
```

---

### Task 6: Financial records list view with summary cards

**Files:**
- Modify: `RecordsFrontend/src/Pages/FinancialDirectory/FinancialRecordsIndex.jsx` (replace placeholder entirely)

**Interfaces:**
- Consumes: `useFinancialDirectory` (Task 5), `isFinancialManager` (`src/lib/roles.js`), `useAuth` (`@/Context/AuthContext`), `SummaryCard` (`@/components/ui/SummaryCard`), `formatMoney` (`@/lib/utils`), `ui/` primitives and current `extractErrorMessage` for delete (handled in Task 7; Task 6 renders view/edit/delete buttons but wires only the read path and placeholder handlers).
- Produces: `FinancialRecordsIndex` default export — header, summary cards, toolbar, table, pagination. Holds state: `formOpen`, `editingRecord`, `deleteTarget` (wired in Task 7); match the `EventsIndex` shape.

- [ ] **Step 1: Implement the list page**

Replace `RecordsFrontend/src/Pages/FinancialDirectory/FinancialRecordsIndex.jsx`:

```jsx
import { useState } from "react";
import {
  ChevronLeft,
  ChevronRight,
  Download,
  Eye,
  Pencil,
  Plus,
  Search,
  Trash2,
  Wallet,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Card } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import SummaryCard from "@/components/ui/SummaryCard";
import { useAuth } from "@/Context/AuthContext";
import { isFinancialManager } from "@/lib/roles";
import { formatMoney } from "@/lib/utils";
import useFinancialDirectory from "@/hooks/useFinancialDirectory";

function formatDate(value) {
  if (!value) return "—";
  return new Date(value).toLocaleDateString();
}

function typeStyles(type) {
  return type === "income"
    ? "bg-emerald-50 text-emerald-700"
    : "bg-rose-50 text-rose-700";
}

function TableSkeleton() {
  return (
    <TableBody>
      {Array.from({ length: 6 }).map((_, index) => (
        <TableRow key={index}>
          <TableCell><Skeleton className="h-3 w-44" /></TableCell>
          <TableCell><Skeleton className="h-3 w-24" /></TableCell>
          <TableCell><Skeleton className="h-5 w-16" /></TableCell>
          <TableCell><Skeleton className="h-3 w-20" /></TableCell>
          <TableCell><Skeleton className="h-3 w-20" /></TableCell>
          <TableCell><Skeleton className="h-5 w-16" /></TableCell>
        </TableRow>
      ))}
    </TableBody>
  );
}

export default function FinancialRecordsIndex() {
  const { user } = useAuth();
  const canManage = isFinancialManager(user);

  const {
    records,
    pagination,
    summary,
    isLoading,
    error,
    search,
    setSearch,
    type,
    setType,
    categoryId,
    setCategoryId,
    page,
    setPage,
    categories,
    isExporting,
    refetch,
    exportCsv,
  } = useFinancialDirectory();

  const [formOpen, setFormOpen] = useState(false);
  const [editingRecord, setEditingRecord] = useState(null);
  const [deleteTarget, setDeleteTarget] = useState(null);

  const openCreate = () => {
    setEditingRecord(null);
    setFormOpen(true);
  };

  const openEdit = (record) => {
    setEditingRecord(record);
    setFormOpen(true);
  };

  const total = pagination?.total ?? 0;
  const lastPage = pagination?.last_page ?? 1;
  const from = pagination?.from ?? 0;
  const to = pagination?.to ?? 0;

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold text-csit-text">Financial Records</h1>
          <p className="mt-1 text-sm text-csit-text-muted">
            Income and expenses for the society
          </p>
        </div>
        {canManage ? (
          <div className="flex flex-wrap items-center gap-2">
            <Button
              type="button"
              variant="outline"
              onClick={exportCsv}
              disabled={isExporting}
            >
              <Download />
              {isExporting ? "Exporting…" : "Export CSV"}
            </Button>
            <Button onClick={openCreate}>
              <Plus />
              Add record
            </Button>
          </div>
        ) : null}
      </div>

      <div className="grid gap-4 sm:grid-cols-3">
        <SummaryCard
          title="Total Income"
          value={formatMoney(summary?.total_income)}
          description="All recorded income"
          variant="success"
          icon={<Wallet />}
        />
        <SummaryCard
          title="Total Expenses"
          value={formatMoney(summary?.total_expense)}
          description="All recorded expenses"
          variant="danger"
          icon={<Wallet />}
        />
        <SummaryCard
          title="Net Balance"
          value={formatMoney(summary?.balance)}
          description="Income minus expenses"
          variant="default"
          icon={<Wallet />}
        />
      </div>

      <Card className="border-csit-border bg-white">
        <div className="flex flex-col gap-3 border-b border-csit-border p-4 sm:flex-row sm:items-center">
          <div className="relative flex-1">
            <Search
              size={16}
              className="pointer-events-none absolute top-1/2 left-2.5 -translate-y-1/2 text-csit-text-muted"
            />
            <Input
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder="Search by title…"
              className="pl-8"
            />
          </div>
          <select
            value={type}
            onChange={(event) => setType(event.target.value)}
            className="h-8 rounded-lg border border-input bg-transparent px-2.5 text-sm outline-none transition-colors focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
            aria-label="Filter by type"
          >
            <option value="">All types</option>
            <option value="income">Income</option>
            <option value="expense">Expense</option>
          </select>
          <select
            value={categoryId}
            onChange={(event) => setCategoryId(event.target.value)}
            className="h-8 rounded-lg border border-input bg-transparent px-2.5 text-sm outline-none transition-colors focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
            aria-label="Filter by category"
          >
            <option value="">All categories</option>
            {categories.map((category) => (
              <option key={category.id} value={category.id}>
                {category.name}
              </option>
            ))}
          </select>
        </div>

        {error ? (
          <div className="rounded-2xl border border-rose-200 bg-rose-50/50 p-6 text-sm text-rose-600">
            <div>Failed to load financial records. Please try again.</div>
            <Button
              type="button"
              variant="outline"
              onClick={refetch}
              className="mt-3 border-rose-300 text-rose-600 hover:bg-rose-100"
            >
              Retry
            </Button>
          </div>
        ) : records.length === 0 && !isLoading ? (
          <Empty className="border-0 py-16">
            <EmptyHeader>
              <EmptyMedia variant="icon">
                <Wallet />
              </EmptyMedia>
              <EmptyTitle>No records found</EmptyTitle>
              <EmptyDescription>
                {search || type || categoryId
                  ? "Try adjusting your search or filters."
                  : canManage
                    ? "Get started by adding your first record."
                    : "No financial records have been recorded yet."}
              </EmptyDescription>
            </EmptyHeader>
          </Empty>
        ) : (
          <Table>
            <TableHeader>
              <TableRow className="hover:bg-transparent">
                <TableHead className="px-4">Title</TableHead>
                <TableHead>Category</TableHead>
                <TableHead>Type</TableHead>
                <TableHead className="text-right">Amount</TableHead>
                <TableHead>Date</TableHead>
                <TableHead className="px-4 text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            {isLoading ? (
              <TableSkeleton />
            ) : (
              <TableBody>
                {records.map((record) => (
                  <TableRow key={record.id}>
                    <TableCell className="px-4">
                      <span className="font-medium text-csit-text">{record.title}</span>
                    </TableCell>
                    <TableCell className="text-csit-text-muted">
                      {record.category?.name ?? "—"}
                    </TableCell>
                    <TableCell>
                      <span className={`rounded-md px-1.5 py-0.5 text-xs font-medium ${typeStyles(record.type)}`}>
                        {record.type}
                      </span>
                    </TableCell>
                    <TableCell className="text-right">
                      <span className={`font-medium ${record.type === "income" ? "text-emerald-600" : "text-rose-600"}`}>
                        {record.type === "income" ? "+" : "−"}{formatMoney(record.amount)}
                      </span>
                    </TableCell>
                    <TableCell className="text-csit-text-muted">
                      {formatDate(record.transaction_date)}
                    </TableCell>
                    <TableCell className="px-4">
                      <div className="flex items-center justify-end gap-1">
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon"
                          onClick={() => openEdit(record)}
                          aria-label={`View ${record.title}`}
                        >
                          <Eye className="text-csit-text-muted" />
                        </Button>
                        {canManage ? (
                          <>
                            <Button
                              type="button"
                              variant="ghost"
                              size="icon"
                              onClick={() => openEdit(record)}
                              aria-label={`Edit ${record.title}`}
                            >
                              <Pencil className="text-csit-text-muted" />
                            </Button>
                            <Button
                              type="button"
                              variant="ghost"
                              size="icon"
                              onClick={() => setDeleteTarget(record)}
                              aria-label={`Delete ${record.title}`}
                              className="hover:bg-rose-50 hover:text-rose-600"
                            >
                              <Trash2 className="text-csit-text-muted" />
                            </Button>
                          </>
                        ) : null}
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            )}
          </Table>
        )}

        {!error && (
          <div className="flex items-center justify-between gap-4 border-t border-csit-border px-4 py-3">
            <p className="text-xs text-csit-text-muted">
              {total === 0 ? "No results" : `Showing ${from}–${to} of ${total} records`}
            </p>
            <div className="flex items-center gap-2">
              <Button
                type="button"
                variant="outline"
                size="sm"
                disabled={page <= 1 || isLoading}
                onClick={() => setPage((current) => Math.max(1, current - 1))}
              >
                <ChevronLeft />
                Previous
              </Button>
              <span className="text-xs font-medium text-csit-text-muted">
                Page {Math.min(page, lastPage)} of {lastPage}
              </span>
              <Button
                type="button"
                variant="outline"
                size="sm"
                disabled={page >= lastPage || isLoading}
                onClick={() => setPage((current) => Math.min(lastPage, current + 1))}
              >
                Next
                <ChevronRight />
              </Button>
            </div>
          </div>
        )}
      </Card>

      {/* Form + delete dialogs wired in Task 7 */}
      <span className="hidden">{formOpen ? "open" : "closed"}-{editingRecord ? editingRecord.id : "none"}-{deleteTarget ? deleteTarget.id : "none"}</span>
    </div>
  );
}
```

**Note on the read path:** The `Eye` button calls `openEdit` in this task to keep the file functional without a dedicated detail view; in Task 7 the `Eye` action will be changed to open the edit form directly too (there is no separate read-only detail page — the edit dialog serves as the view/edit surface, consistent with how members/events inline forms work). The hidden span is a temporary holder for the `formOpen`/`editingRecord`/`deleteTarget` state so lint does not flag unused vars before Task 7 wires the dialogs. Task 7 removes it.

- [ ] **Step 2: Verify lint/build**

Run: `npm run lint` — no new warnings.
Run: `npm run build` — succeeds.

- [ ] **Step 3: Manual smoke (read path)**

Start dev servers. Log in, navigate to `/financial-records`. Expect: crumbs show `Home / Financial Records`; summary cards render with £/MWK-formatted totals (0.00 initially); empty state; search/type/category filters present; pagination footer shows "No results". Admin sees Export + Add record buttons; non-admin does not.

- [ ] **Step 4: Commit**

```bash
git add src/Pages/FinancialDirectory/FinancialRecordsIndex.jsx
git commit -m "feat: add financial records list view with summary cards"
```

---

### Task 7: Create/edit record dialog and delete confirmation

**Files:**
- Create: `RecordsFrontend/src/Pages/FinancialDirectory/FinancialRecordFormDialog.jsx`
- Modify: `RecordsFrontend/src/Pages/FinancialDirectory/FinancialRecordsIndex.jsx`

**Interfaces:**
- Consumes: `useFinancialDirectory.createRecord`, `.updateRecord`, `.deleteRecord`, `.categories` (Task 5); `extractErrorMessage` (import from `@/lib/errors`); Dialog primitives.
- Produces:
  - `FinancialRecordFormDialog` default export. Props: `open`, `onOpenChange`, `record` (object|null = create), `categories` (array), `onSubmit` (async fn), `isSubmitting` (bool), `error` (string|null). Fields: `title` (required), `type` (select income/expense, default income), `category_id` (select), `amount` (number, > 0, required), `transaction_date` (date input, required).
  - Note: `src/lib/errors.js` already exists (created for the document feature). If it does not exist in your checkout, match the `DocumentFormDialog`/`MembersIndex` inline error helper; otherwise import `extractErrorMessage` from it.

- [ ] **Step 1: Create `FinancialRecordFormDialog.jsx`**

Create `RecordsFrontend/src/Pages/FinancialDirectory/FinancialRecordFormDialog.jsx`:

```jsx
import { useEffect, useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";

const TYPE_OPTIONS = [
  { value: "income", label: "Income" },
  { value: "expense", label: "Expense" },
];

export default function FinancialRecordFormDialog({
  open,
  onOpenChange,
  record,
  categories,
  onSubmit,
  isSubmitting,
  error,
}) {
  const isEdit = Boolean(record);

  const [form, setForm] = useState({
    title: "",
    type: "income",
    category_id: "",
    amount: "",
    transaction_date: "",
  });

  useEffect(() => {
    if (!open) return;
    setForm({
      title: record?.title ?? "",
      type: record?.type ?? "income",
      category_id: record?.category?.id != null ? String(record.category.id) : "",
      amount: record?.amount ?? "",
      transaction_date: record?.transaction_date ?? "",
    });
  }, [open, record]);

  const updateField = (field, value) => {
    setForm((current) => ({ ...current, [field]: value }));
  };

  const handleSubmit = (event) => {
    event.preventDefault();
    onSubmit({
      title: form.title.trim(),
      type: form.type,
      category_id: form.category_id ? Number(form.category_id) : null,
      amount: form.amount,
      transaction_date: form.transaction_date,
    });
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{isEdit ? "Edit record" : "Add financial record"}</DialogTitle>
          <DialogDescription>
            {isEdit ? "Update the record's details below." : "Record an income or expense."}
          </DialogDescription>
        </DialogHeader>

        <form id="financial-record-form" onSubmit={handleSubmit} className="flex flex-col gap-3">
          <label className="flex flex-col gap-1.5 text-xs font-medium text-csit-text-muted">
            Title
            <Input
              required
              value={form.title}
              onChange={(event) => updateField("title", event.target.value)}
              placeholder="e.g. Membership fees"
            />
          </label>

          <div className="grid grid-cols-2 gap-3">
            <label className="flex flex-col gap-1.5 text-xs font-medium text-csit-text-muted">
              Type
              <select
                value={form.type}
                onChange={(event) => updateField("type", event.target.value)}
                className="h-9 rounded-lg border border-input bg-transparent px-2.5 text-sm outline-none transition-colors focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
              >
                {TYPE_OPTIONS.map((option) => (
                  <option key={option.value} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </select>
            </label>
            <label className="flex flex-col gap-1.5 text-xs font-medium text-csit-text-muted">
              Amount (MWK)
              <Input
                required
                type="number"
                min="0.01"
                step="0.01"
                value={form.amount}
                onChange={(event) => updateField("amount", event.target.value)}
                placeholder="0.00"
              />
            </label>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <label className="flex flex-col gap-1.5 text-xs font-medium text-csit-text-muted">
              Category
              <select
                value={form.category_id}
                onChange={(event) => updateField("category_id", event.target.value)}
                className="h-9 rounded-lg border border-input bg-transparent px-2.5 text-sm outline-none transition-colors focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
              >
                <option value="">Uncategorized</option>
                {categories.map((category) => (
                  <option key={category.id} value={category.id}>
                    {category.name}
                  </option>
                ))}
              </select>
            </label>
            <label className="flex flex-col gap-1.5 text-xs font-medium text-csit-text-muted">
              Transaction date
              <Input
                required
                type="date"
                value={form.transaction_date}
                onChange={(event) => updateField("transaction_date", event.target.value)}
              />
            </label>
          </div>
        </form>

        {error && (
          <div className="rounded-lg border border-rose-200 bg-rose-50/50 px-3 py-2 text-xs text-rose-600">
            {error}
          </div>
        )}

        <DialogFooter>
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button type="submit" form="financial-record-form" disabled={isSubmitting}>
            {isSubmitting ? (isEdit ? "Saving…" : "Adding…") : isEdit ? "Save changes" : "Add record"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
```

- [ ] **Step 2: Wire the form + delete dialogs into `FinancialRecordsIndex.jsx`**

Add imports:

```jsx
import FinancialRecordFormDialog from "./FinancialRecordFormDialog";
import { extractErrorMessage } from "@/lib/errors";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
```

Add to the hook destructure: `createRecord, updateRecord, deleteRecord`.

Add state after the existing `deleteTarget` state:

```jsx
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState(null);
  const [isDeleting, setIsDeleting] = useState(false);
  const [deleteError, setDeleteError] = useState(null);
```

Add handlers after `openEdit`:

```jsx
  const handleSubmit = async (data) => {
    setIsSubmitting(true);
    setSubmitError(null);
    try {
      if (editingRecord) {
        await updateRecord(editingRecord.id, data);
      } else {
        await createRecord(data);
      }
      setFormOpen(false);
    } catch (err) {
      setSubmitError(extractErrorMessage(err));
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleDelete = async () => {
    setIsDeleting(true);
    setDeleteError(null);
    try {
      await deleteRecord(deleteTarget.id);
      setDeleteTarget(null);
    } catch (err) {
      setDeleteError(extractErrorMessage(err));
    } finally {
      setIsDeleting(false);
    }
  };
```

Remove the hidden `<span>` holder at the bottom of the returned JSX and replace the final `</div>` region (after the `<Card>`) with the two dialogs:

```jsx
      <FinancialRecordFormDialog
        open={formOpen}
        onOpenChange={setFormOpen}
        record={editingRecord}
        categories={categories}
        onSubmit={handleSubmit}
        isSubmitting={isSubmitting}
        error={submitError}
      />

      <Dialog
        open={Boolean(deleteTarget)}
        onOpenChange={(open) => {
          if (!open) setDeleteTarget(null);
        }}
      >
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>Delete record</DialogTitle>
            <DialogDescription>
              Are you sure you want to delete{" "}
              <span className="font-medium text-foreground">
                {deleteTarget?.title}
              </span>
              ? This action cannot be undone.
            </DialogDescription>
          </DialogHeader>

          {deleteError && (
            <div className="rounded-lg border border-rose-200 bg-rose-50/50 px-3 py-2 text-xs text-rose-600">
              {deleteError}
            </div>
          )}

          <DialogFooter>
            <Button
              type="button"
              variant="outline"
              onClick={() => setDeleteTarget(null)}
              disabled={isDeleting}
            >
              Cancel
            </Button>
            <Button
              type="button"
              variant="destructive"
              onClick={handleDelete}
              disabled={isDeleting}
            >
              {isDeleting ? "Deleting…" : "Delete record"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
```

- [ ] **Step 3: Verify lint/build**

Run: `npm run lint` — no new warnings.
Run: `npm run build` — succeeds.

- [ ] **Step 4: Manual smoke**

Admin: add an income and an expense record → rows appear and summary cards update. Edit a record's title/amount → row + summary refresh. Delete a record → row disappears and summary updates. Non-admin: no Add/Export buttons, Edit/Delete hidden.

- [ ] **Step 5: Commit**

```bash
git add src/Pages/FinancialDirectory/FinancialRecordFormDialog.jsx src/Pages/FinancialDirectory/FinancialRecordsIndex.jsx
git commit -m "feat: add financial record create/edit dialog and delete confirm"
```

---

### Task 8: Financial category manager dialog

**Files:**
- Create: `RecordsFrontend/src/Pages/FinancialDirectory/FinancialCategoryManagerDialog.jsx`
- Modify: `RecordsFrontend/src/Pages/FinancialDirectory/FinancialRecordsIndex.jsx`

**Interfaces:**
- Consumes: `useFinancialDirectory.categories`, `.createCategory`, `.updateCategory`, `.deleteCategory` (Task 5); `extractErrorMessage` (`@/lib/errors`).
- Produces: `FinancialCategoryManagerDialog` default export. Props: `open`, `onOpenChange`, `categories`, `onCreate`, `onUpdate`, `onDelete`. Internally manages form (`name`, `description`), edit target, delete-confirm target, submitting/error state.

- [ ] **Step 1: Create `FinancialCategoryManagerDialog.jsx`** (mirror `DocumentCategoryManagerDialog`)

Create `RecordsFrontend/src/Pages/FinancialDirectory/FinancialCategoryManagerDialog.jsx`:

```jsx
import { useEffect, useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Pencil, Plus, Trash2 } from "lucide-react";
import { extractErrorMessage } from "@/lib/errors";

const emptyForm = { name: "", description: "" };

export default function FinancialCategoryManagerDialog({
  open,
  onOpenChange,
  categories,
  onCreate,
  onUpdate,
  onDelete,
}) {
  const [form, setForm] = useState(emptyForm);
  const [editing, setEditing] = useState(null);
  const [confirming, setConfirming] = useState(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => {
    if (!open) return;
    setForm(emptyForm);
    setEditing(null);
    setConfirming(null);
    setError(null);
  }, [open]);

  const startEdit = (category) => {
    setEditing(category);
    setConfirming(null);
    setForm({ name: category.name ?? "", description: category.description ?? "" });
  };

  const submitForm = async (event) => {
    event.preventDefault();
    const payload = {
      name: form.name.trim(),
      description: form.description.trim(),
    };
    setIsSubmitting(true);
    setError(null);
    try {
      if (editing) {
        await onUpdate(editing.id, payload);
        setEditing(null);
        setForm(emptyForm);
      } else {
        await onCreate(payload);
        setForm(emptyForm);
      }
    } catch (err) {
      setError(extractErrorMessage(err));
    } finally {
      setIsSubmitting(false);
    }
  };

  const confirmDelete = async () => {
    setIsSubmitting(true);
    setError(null);
    try {
      await onDelete(confirming.id);
      setConfirming(null);
    } catch (err) {
      setError(extractErrorMessage(err));
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>Financial categories</DialogTitle>
          <DialogDescription>
            {confirming
              ? `Delete "${confirming.name}"? Records using it will become uncategorized.`
              : "Organize records into categories."}
          </DialogDescription>
        </DialogHeader>

        {error && (
          <div className="rounded-lg border border-rose-200 bg-rose-50/50 px-3 py-2 text-xs text-rose-600">
            {error}
          </div>
        )}

        {confirming ? (
          <DialogFooter>
            <Button
              type="button"
              variant="outline"
              onClick={() => setConfirming(null)}
              disabled={isSubmitting}
            >
              Cancel
            </Button>
            <Button
              type="button"
              variant="destructive"
              onClick={confirmDelete}
              disabled={isSubmitting}
            >
              {isSubmitting ? "Deleting…" : "Delete category"}
            </Button>
          </DialogFooter>
        ) : (
          <>
            <form onSubmit={submitForm} className="flex flex-col gap-3 border-b border-csit-border pb-4">
              <label className="flex flex-col gap-1.5 text-xs font-medium text-csit-text-muted">
                Name
                <Input
                  required
                  value={form.name}
                  onChange={(event) => setForm((current) => ({ ...current, name: event.target.value }))}
                  placeholder="e.g. Membership Fees"
                />
              </label>
              <label className="flex flex-col gap-1.5 text-xs font-medium text-csit-text-muted">
                Description
                <Input
                  value={form.description}
                  onChange={(event) => setForm((current) => ({ ...current, description: event.target.value }))}
                  placeholder="Optional"
                />
              </label>
              <div className="flex justify-end">
                <Button type="submit" size="sm" disabled={isSubmitting}>
                  <Plus />
                  {isSubmitting ? "Saving…" : editing ? "Save changes" : "Add category"}
                </Button>
              </div>
            </form>

            <ul className="flex flex-col gap-2 pt-2">
              {categories.length === 0 ? (
                <li className="py-4 text-center text-sm text-csit-text-muted">
                  No categories yet. Add one above.
                </li>
              ) : (
                categories.map((category) => (
                  <li
                    key={category.id}
                    className="flex items-center justify-between gap-3 rounded-lg border border-csit-border px-3 py-2 text-sm"
                  >
                    <div className="min-w-0">
                      <p className="font-medium text-csit-text">{category.name}</p>
                      {category.description ? (
                        <p className="truncate text-xs text-csit-text-muted">{category.description}</p>
                      ) : null}
                    </div>
                    <div className="flex shrink-0 items-center gap-1">
                      <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        onClick={() => startEdit(category)}
                        aria-label={`Edit ${category.name}`}
                      >
                        <Pencil className="text-csit-text-muted" />
                      </Button>
                      <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        onClick={() => setConfirming(category)}
                        aria-label={`Delete ${category.name}`}
                        className="hover:bg-rose-50 hover:text-rose-600"
                      >
                        <Trash2 className="text-csit-text-muted" />
                      </Button>
                    </div>
                  </li>
                ))
              )}
            </ul>
          </>
        )}
      </DialogContent>
    </Dialog>
  );
}
```

- [ ] **Step 2: Wire into `FinancialRecordsIndex.jsx`**

Add import:

```jsx
import FinancialCategoryManagerDialog from "./FinancialCategoryManagerDialog";
```

Add to the hook destructure: `createCategory, updateCategory, deleteCategory`.

Add state: `const [categoryManagerOpen, setCategoryManagerOpen] = useState(false);`

Add a "Manage categories" button next to "Export CSV" in the header actions (inside the `canManage` fragment):

```jsx
            <Button
              type="button"
              variant="outline"
              onClick={() => setCategoryManagerOpen(true)}
            >
              Manage categories
            </Button>
```

Render the dialog after the delete `<Dialog>` (before the final `</div>`):

```jsx
      <FinancialCategoryManagerDialog
        open={categoryManagerOpen}
        onOpenChange={setCategoryManagerOpen}
        categories={categories}
        onCreate={createCategory}
        onUpdate={updateCategory}
        onDelete={deleteCategory}
      />
```

- [ ] **Step 3: Verify lint/build**

Run: `npm run lint` — no new warnings.
Run: `npm run build` — succeeds.

- [ ] **Step 4: Manual smoke**

Admin: open Manage categories, add a category → appears in the list and in the record form/filter dropdown. Rename and delete a category (confirm dialog) → list updates. Non-admin: "Manage categories" button is hidden.

- [ ] **Step 5: Commit**

```bash
git add src/Pages/FinancialDirectory/FinancialCategoryManagerDialog.jsx src/Pages/FinancialDirectory/FinancialRecordsIndex.jsx
git commit -m "feat: add financial category manager dialog"
```

---

# END-TO-END VERIFICATION

Run both repos' gates together and the manual smoke:

- RecordsAPI: `vendor/bin/pest --compact` (all green), `vendor/bin/pint --dirty --format agent`.
- RecordsFrontend: `npm run lint`, `npm run build`.
- Manual: login as admin → `/financial-records`: summary cards show real totals; add income/expense; filter by type/category; search; export CSV downloads with the filtered rows and correct header; categories manageable. Login as member → read-only (no Add/Export/Manage/edit/delete), summary + list visible.
