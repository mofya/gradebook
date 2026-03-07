<?php

namespace App\Imports;

use App\Models\Student;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class StudentsImport implements ToModel, WithHeadingRow
{
    protected int $imported = 0;

    protected int $skipped = 0;

    /** @var array<int, Student> */
    protected array $importedStudents = [];

    /** @var array<string, string> */
    protected const REQUIRED_COLUMNS = [
        'email' => 'email',
        'first_name' => 'first_name',
        'last_name' => 'last_name',
    ];

    /** @var array<string, string> */
    protected const OPTIONAL_COLUMNS = [
        'student_id' => 'student_id or student_id_number',
        'gender' => 'gender',
        'program' => 'program or programme',
        'year_of_study' => 'year_of_study',
    ];

    /**
     * Validate that the file headers contain the required columns.
     *
     * @param  array<int, string>  $headers
     * @return array{valid: bool, missing: array<int, string>}
     */
    public static function validateHeaders(array $headers): array
    {
        $headers = array_map(fn ($h) => strtolower(str_replace(' ', '_', trim((string) $h))), $headers);

        $missing = [];

        if (! in_array('email', $headers)) {
            $missing[] = 'email';
        }

        if (! in_array('first_name', $headers)) {
            $missing[] = 'first_name';
        }

        if (! in_array('last_name', $headers)) {
            $missing[] = 'last_name';
        }

        return [
            'valid' => empty($missing),
            'missing' => $missing,
        ];
    }

    /** @var array<string, bool> */
    protected array $seenGithubUsernames = [];

    public function model(array $row): ?Student
    {
        $studentIdNumber = $row['student_id'] ?? $row['student_id_number'] ?? null;
        $firstName = $row['first_name'] ?? null;
        $lastName = $row['last_name'] ?? null;
        $email = $row['email'] ?? null;
        $gender = $row['gender'] ?? null;
        $program = $row['program'] ?? $row['programme'] ?? null;
        $yearOfStudy = $row['year_of_study'] ?? null;
        $githubUsername = $row['github_username'] ?? null;

        if (! $firstName || ! $lastName || ! $email) {
            $this->skipped++;

            return null;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->skipped++;

            return null;
        }

        if ($yearOfStudy !== null && ! is_numeric($yearOfStudy)) {
            $yearOfStudy = null;
        }

        // Deduplicate github usernames — skip if empty or already seen/taken
        $resolvedGithub = null;
        if ($githubUsername && trim($githubUsername) !== '') {
            $trimmed = trim($githubUsername);
            $lower = strtolower($trimmed);

            if (isset($this->seenGithubUsernames[$lower])) {
                $resolvedGithub = null;
            } elseif (Student::where('github_username', $trimmed)->where('email', '!=', $email)->exists()) {
                $resolvedGithub = null;
            } else {
                $resolvedGithub = $trimmed;
                $this->seenGithubUsernames[$lower] = true;
            }
        }

        $attributes = array_filter([
            'student_id_number' => $studentIdNumber,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'gender' => $gender,
            'program' => $program,
            'year_of_study' => $yearOfStudy ? (int) $yearOfStudy : null,
            'github_username' => $resolvedGithub,
        ], fn ($value) => $value !== null);

        $this->imported++;

        $student = Student::updateOrCreate(
            ['email' => $email],
            $attributes,
        );

        $this->importedStudents[] = $student;

        return $student;
    }

    public function getImportedCount(): int
    {
        return $this->imported;
    }

    public function getSkippedCount(): int
    {
        return $this->skipped;
    }

    /**
     * @return array<int, Student>
     */
    public function getImportedStudents(): array
    {
        return $this->importedStudents;
    }
}
