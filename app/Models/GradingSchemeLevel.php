<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradingSchemeLevel extends Model
{
    use HasFactory;

    protected $fillable = ['grading_scheme_id', 'letter', 'min_mark', 'max_mark', 'grade_points', 'sort_order'];

    protected function casts(): array
    {
        return [
            'min_mark' => 'decimal:2',
            'max_mark' => 'decimal:2',
            'grade_points' => 'decimal:1',
        ];
    }

    public function gradingScheme(): BelongsTo
    {
        return $this->belongsTo(GradingScheme::class);
    }
}
