<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsernameDispute extends Model
{
    use HasFactory;

    protected $fillable = [
        'claimant_student_id',
        'current_holder_student_id',
        'github_username',
        'course_offering_id',
        'status',
        'resolved_by',
        'resolved_at',
        'resolution_notes',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    public function claimant(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'claimant_student_id');
    }

    public function currentHolder(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'current_holder_student_id');
    }

    public function courseOffering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class);
    }

    public function resolvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
