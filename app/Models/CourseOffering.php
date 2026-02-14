<?php

namespace App\Models;

use App\Enums\OfferingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class CourseOffering extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'semester_id',
        'section',
        'grading_scheme_id',
        'lecturer_id',
        'ca_weight',
        'exam_weight',
        'status',
        'created_from_offering_id',
        'is_published',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'ca_weight' => 'decimal:2',
            'exam_weight' => 'decimal:2',
            'status' => OfferingStatus::class,
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function gradingScheme(): BelongsTo
    {
        return $this->belongsTo(GradingScheme::class);
    }

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lecturer_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function assessmentGroups(): HasMany
    {
        return $this->hasMany(AssessmentGroup::class);
    }

    public function assessments(): HasManyThrough
    {
        return $this->hasManyThrough(Assessment::class, AssessmentGroup::class);
    }

    public function sourceOffering(): BelongsTo
    {
        return $this->belongsTo(self::class, 'created_from_offering_id');
    }

    /**
     * Check that CA + exam weights sum to 100.
     */
    public function hasValidWeights(): bool
    {
        return bcadd($this->ca_weight, $this->exam_weight, 2) === '100.00';
    }

    /**
     * Activate the offering: draft → active.
     * Validates grading scheme and assessments exist.
     *
     * @throws \LogicException
     */
    public function activate(): self
    {
        if ($this->status !== OfferingStatus::Draft) {
            throw new \LogicException('Only draft offerings can be activated.');
        }

        if (! $this->hasValidWeights()) {
            throw new \LogicException('CA and exam weights must sum to 100.');
        }

        if ($this->assessmentGroups()->doesntExist()) {
            throw new \LogicException('At least one assessment group is required before activation.');
        }

        $this->update(['status' => OfferingStatus::Active]);

        return $this;
    }

    /**
     * Lock the offering: active → locked. Prevents further grade entry.
     *
     * @throws \LogicException
     */
    public function lock(): self
    {
        if ($this->status !== OfferingStatus::Active) {
            throw new \LogicException('Only active offerings can be locked.');
        }

        $this->update(['status' => OfferingStatus::Locked]);

        return $this;
    }

    /**
     * Publish the offering: locked → published. Visible to students.
     *
     * @throws \LogicException
     */
    public function publish(): self
    {
        if ($this->status !== OfferingStatus::Locked) {
            throw new \LogicException('Only locked offerings can be published.');
        }

        $this->update([
            'status' => OfferingStatus::Published,
            'is_published' => true,
            'published_at' => now(),
        ]);

        return $this;
    }

    /**
     * Duplicate the offering with its assessment groups and assessments.
     * The new offering starts as draft.
     */
    public function duplicate(?Semester $targetSemester = null): self
    {
        $this->load('assessmentGroups.assessments');

        $newOffering = self::create([
            'course_id' => $this->course_id,
            'semester_id' => $targetSemester?->id ?? $this->semester_id,
            'section' => $this->section,
            'grading_scheme_id' => $this->grading_scheme_id,
            'lecturer_id' => $this->lecturer_id,
            'ca_weight' => $this->ca_weight,
            'exam_weight' => $this->exam_weight,
            'status' => OfferingStatus::Draft,
            'created_from_offering_id' => $this->id,
            'is_published' => false,
        ]);

        foreach ($this->assessmentGroups as $group) {
            $newGroup = $newOffering->assessmentGroups()->create([
                'name' => $group->name,
                'type' => $group->type,
                'weight_percentage' => $group->weight_percentage,
                'weight_points' => $group->weight_points,
                'weight_mode' => $group->weight_mode,
                'sort_order' => $group->sort_order,
            ]);

            foreach ($group->assessments as $assessment) {
                $newGroup->assessments()->create([
                    'name' => $assessment->name,
                    'weight' => $assessment->weight,
                    'course_id' => $this->course_id,
                    'max_raw_score' => $assessment->max_raw_score,
                    'normalized_to' => $assessment->normalized_to,
                    'has_subsections' => $assessment->has_subsections,
                    'sort_order' => $assessment->sort_order,
                ]);
            }
        }

        return $newOffering;
    }
}
