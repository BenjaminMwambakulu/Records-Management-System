<?php

namespace App\Models;

use App\Enums\AcademicTrack;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, LogsActivity, Notifiable;

    protected static function booted(): void
    {
        static::created(function (User $user) {
            if ($user->roles->isEmpty()) {
                $user->assignRole(Role::findOrCreate('member', 'logto'));
            }
        });
    }

    protected $fillable = [
        'logto_id',
        'student_id',
        'first_name',
        'last_name',
        'email',
        'academic_track',
        'enrolled_year',
        'study_year',
        'skills',
    ];

    protected $hidden = [
        'email',
    ];

    protected function casts(): array
    {
        return [
            'academic_track' => AcademicTrack::class,
            'enrolled_year' => 'integer',
            'study_year' => 'integer',
            'skills' => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty();
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function assetLoans(): HasMany
    {
        return $this->hasMany(AssetLoan::class, 'borrower_id');
    }

    public function financialRecords(): HasMany
    {
        return $this->hasMany(FinancialRecord::class, 'recorded_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'created_by');
    }

    public function documentVersions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class, 'uploaded_by');
    }
}
