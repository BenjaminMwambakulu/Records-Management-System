<?php

namespace App\Models;

use App\Enums\AssetStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Asset extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'name',
        'serial_number',
        'category',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => AssetStatus::class,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty();
    }

    public function loans(): HasMany
    {
        return $this->hasMany(AssetLoan::class);
    }
}
