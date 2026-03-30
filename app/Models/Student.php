<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'personal_email',
        'password',
        'registered_at',
        'student_id_number',
        'gender',
        'program',
        'year_of_study',
        'study_mode',
        'github_username',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'registered_at' => 'datetime',
        ];
    }

    public function isRegistered(): bool
    {
        return $this->registered_at !== null;
    }

    /**
     * Return the personal email if set, otherwise the institutional email.
     */
    public function preferredEmail(): string
    {
        return $this->personal_email ?? $this->email;
    }

    /**
     * Find a student by any of their email addresses (institutional or personal).
     */
    public static function findByEmail(string $email): ?self
    {
        $normalized = mb_strtolower($email);

        return static::query()
            ->whereRaw('LOWER(email) = ?', [$normalized])
            ->orWhereRaw('LOWER(personal_email) = ?', [$normalized])
            ->first();
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class)
            ->withPivot('academic_year', 'semester')
            ->withTimestamps();
    }
}
