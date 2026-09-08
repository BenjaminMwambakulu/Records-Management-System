<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberImport extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'original_name',
        'file_path',
        'status',
        'total_rows',
        'processed',
        'created_count',
        'duplicate_count',
        'failed_count',
        'error_rows',
        'error_message',
    ];

    protected $casts = [
        'total_rows' => 'integer',
        'processed' => 'integer',
        'created_count' => 'integer',
        'duplicate_count' => 'integer',
        'failed_count' => 'integer',
        'error_rows' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
