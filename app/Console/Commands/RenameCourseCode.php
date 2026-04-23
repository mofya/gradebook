<?php

namespace App\Console\Commands;

use App\Models\Course;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:rename-course-code {old : Current course code} {new : New course code}')]
#[Description('Rename a course code. Idempotent: no-op if no course uses the old code.')]
class RenameCourseCode extends Command
{
    public function handle(): int
    {
        $old = (string) $this->argument('old');
        $new = (string) $this->argument('new');

        $course = Course::where('code', $old)->first();

        if (! $course) {
            if (Course::where('code', $new)->exists()) {
                $this->info("No course with code '{$old}' found; a course with '{$new}' already exists. Nothing to do.");

                return self::SUCCESS;
            }

            $this->error("No course found with code '{$old}'.");

            return self::FAILURE;
        }

        if ($course->code === $new) {
            $this->info("Course code already '{$new}'.");

            return self::SUCCESS;
        }

        $course->update(['code' => $new]);
        $this->info("Updated course #{$course->id}: '{$old}' → '{$new}'.");

        return self::SUCCESS;
    }
}
