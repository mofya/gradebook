<?php

namespace App\Models;

use Database\Factories\MissedAssessmentAppealItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MissedAssessmentAppealItem extends Model
{
    /** @use HasFactory<MissedAssessmentAppealItemFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'missed_assessment_appeal_id',
        'assessment_id',
        'status',
        'reviewer_notes',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function appeal(): BelongsTo
    {
        return $this->belongsTo(MissedAssessmentAppeal::class, 'missed_assessment_appeal_id');
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }
}
