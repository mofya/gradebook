<?php

namespace App\Models;

use Database\Factories\MissedAssessmentAppealFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MissedAssessmentAppeal extends Model
{
    /** @use HasFactory<MissedAssessmentAppealFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_UNDER_REVIEW = 'under_review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_PARTIALLY_APPROVED = 'partially_approved';

    protected $fillable = [
        'course_offering_id',
        'student_id',
        'narrative',
        'other_notes',
        'dean_confirmed',
        'evidence_path',
        'status',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'dean_confirmed' => 'boolean',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function courseOffering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MissedAssessmentAppealItem::class);
    }

    /**
     * Derive overall status from the item statuses.
     */
    public function recomputeStatus(): void
    {
        $items = $this->items()->get();
        if ($items->isEmpty()) {
            $this->update(['status' => self::STATUS_PENDING]);

            return;
        }

        $approved = $items->where('status', self::STATUS_APPROVED)->count();
        $rejected = $items->where('status', self::STATUS_REJECTED)->count();
        $total = $items->count();

        $status = match (true) {
            $approved === $total => self::STATUS_APPROVED,
            $rejected === $total => self::STATUS_REJECTED,
            $approved > 0 || $rejected > 0 => self::STATUS_PARTIALLY_APPROVED,
            default => self::STATUS_PENDING,
        };

        $this->update(['status' => $status]);
    }
}
