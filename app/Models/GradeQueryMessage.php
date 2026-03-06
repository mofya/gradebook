<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradeQueryMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'grade_query_id',
        'user_id',
        'body',
        'is_internal_note',
    ];

    protected function casts(): array
    {
        return [
            'is_internal_note' => 'boolean',
        ];
    }

    public function gradeQuery(): BelongsTo
    {
        return $this->belongsTo(GradeQuery::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
