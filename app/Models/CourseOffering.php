<?php

namespace App\Models;

use App\Enums\OfferingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;

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
        'verification_token',
        'verification_expires_at',
        'public_grade_token',
        'public_grade_token_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'ca_weight' => 'decimal:2',
            'exam_weight' => 'decimal:2',
            'status' => OfferingStatus::class,
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'verification_expires_at' => 'datetime',
            'public_grade_token_expires_at' => 'datetime',
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

    public function missedAssessmentAppeals(): HasMany
    {
        return $this->hasMany(MissedAssessmentAppeal::class);
    }

    public function sourceOffering(): BelongsTo
    {
        return $this->belongsTo(self::class, 'created_from_offering_id');
    }

    /**
     * Generate a verification token for the public student verification form.
     */
    public function generateVerificationToken(int $days = 3): self
    {
        $this->update([
            'verification_token' => Str::random(64),
            'verification_expires_at' => now()->addDays($days),
        ]);

        return $this;
    }

    /**
     * Revoke the verification token.
     */
    public function revokeVerificationToken(): self
    {
        $this->update([
            'verification_token' => null,
            'verification_expires_at' => null,
        ]);

        return $this;
    }

    /**
     * Extend the verification token expiry without changing the token itself.
     */
    public function extendVerificationToken(int $days): self
    {
        if (! $this->verification_token) {
            throw new \LogicException('No verification token exists to extend.');
        }

        $this->update([
            'verification_expires_at' => now()->addDays($days),
        ]);

        return $this;
    }

    /**
     * Check if this offering has a valid (non-expired) verification token.
     */
    public function hasValidVerificationToken(): bool
    {
        return $this->verification_token !== null
            && $this->verification_expires_at !== null
            && $this->verification_expires_at->isFuture();
    }

    /**
     * Generate a public grade token for the class grade sheet.
     */
    public function generatePublicGradeToken(int $days = 7): self
    {
        $this->update([
            'public_grade_token' => Str::random(64),
            'public_grade_token_expires_at' => now()->addDays($days),
        ]);

        return $this;
    }

    /**
     * Revoke the public grade token.
     */
    public function revokePublicGradeToken(): self
    {
        $this->update([
            'public_grade_token' => null,
            'public_grade_token_expires_at' => null,
        ]);

        return $this;
    }

    /**
     * Extend the public grade token expiry without changing the token itself.
     */
    public function extendPublicGradeToken(int $days): self
    {
        if (! $this->public_grade_token) {
            throw new \LogicException('No public grade token exists to extend.');
        }

        $this->update([
            'public_grade_token_expires_at' => now()->addDays($days),
        ]);

        return $this;
    }

    /**
     * Check if this offering has a valid (non-expired) public grade token.
     */
    public function hasValidPublicGradeToken(): bool
    {
        return $this->public_grade_token !== null
            && $this->public_grade_token_expires_at !== null
            && $this->public_grade_token_expires_at->isFuture();
    }

    /**
     * Check if the given user is the assigned lecturer for this offering.
     */
    public function isLecturerAssigned(User $user): bool
    {
        return $this->lecturer_id === $user->id;
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
