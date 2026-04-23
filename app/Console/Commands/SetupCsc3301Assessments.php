<?php

namespace App\Console\Commands;

use App\Models\Assessment;
use App\Models\AssessmentGroup;
use App\Models\CourseOffering;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('app:setup-csc3301-assessments {--course-code=CSC3301} {--dry-run}')]
#[Description('Create/restructure assessment groups and assessments for a CSC3301 offering (idempotent).')]
class SetupCsc3301Assessments extends Command
{
    private int $courseId;

    public function handle(): int
    {
        $courseCode = (string) $this->option('course-code');
        $dryRun = (bool) $this->option('dry-run');

        $offering = CourseOffering::whereHas('course', fn ($q) => $q->where('code', $courseCode))
            ->orderByDesc('id')
            ->first();

        if (! $offering) {
            $this->error("No offering found for course code '{$courseCode}'.");

            return self::FAILURE;
        }

        $this->courseId = (int) $offering->course_id;

        $this->info("Target offering: #{$offering->id} ({$courseCode}).");

        if ($dryRun) {
            $this->warn('Dry run — no writes will be performed.');
        }

        DB::transaction(function () use ($offering, $dryRun): void {
            $labs = $this->upsertGroup($offering->id, 'Labs', 1, $dryRun);
            $projects = $this->upsertGroup($offering->id, 'Projects', 2, $dryRun);
            $quizzes = $this->upsertGroup($offering->id, 'Quizzes', 3, $dryRun);
            $midterm = $this->upsertGroup($offering->id, 'Midterm Test', 4, $dryRun);

            $this->moveProjectsOutOfLabs($labs, $projects, $dryRun);

            $this->addMissingAssessments($labs->id, [
                'Lab 3: Functional Programming',
                'Lab 4: OOP Design',
                'Lab 5: Logic Programming',
                'Lab 6: Concurrency',
            ], maxScore: 100, hasSubsections: true, startSortOrder: 10, dryRun: $dryRun);

            $this->addMissingAssessments($projects->id, [
                'Project 3: Design Patterns',
                'Project 4: Expert System',
            ], maxScore: 100, hasSubsections: true, startSortOrder: 10, dryRun: $dryRun);

            $this->addMissingAssessments($quizzes->id, [
                'Quiz 1 — Intro to PLs + Names, Binding & Scope',
                'Quiz 2 — Control Flow & Memory + Type Systems',
                'Quiz 3 — Functional Programming & Higher-Order Functions',
                'Quiz 4 — Object-Oriented Programming & Logic Programming',
            ], maxScore: 30, hasSubsections: false, startSortOrder: 1, dryRun: $dryRun);

            $this->addMissingAssessments($midterm->id, [
                'Test — Programming Language Paradigms',
            ], maxScore: 100, hasSubsections: false, startSortOrder: 1, dryRun: $dryRun);
        });

        $this->info($dryRun ? 'Dry run complete.' : 'Done.');

        return self::SUCCESS;
    }

    private function upsertGroup(int $offeringId, string $name, int $sortOrder, bool $dryRun): AssessmentGroup
    {
        $attributes = [
            'type' => 'ca',
            'weight_percentage' => 10,
            'weight_mode' => 'percentage',
            'sort_order' => $sortOrder,
        ];

        $existing = AssessmentGroup::where('course_offering_id', $offeringId)
            ->where('name', $name)
            ->first();

        if ($existing) {
            $this->line("  Group exists: {$name} (#{$existing->id}) — setting weight=10%");
            if (! $dryRun) {
                $existing->update($attributes);
            }

            return $existing;
        }

        $this->line("  Group create: {$name}");
        if ($dryRun) {
            return new AssessmentGroup(array_merge($attributes, [
                'course_offering_id' => $offeringId,
                'name' => $name,
            ]));
        }

        return AssessmentGroup::create(array_merge($attributes, [
            'course_offering_id' => $offeringId,
            'name' => $name,
        ]));
    }

    private function moveProjectsOutOfLabs(AssessmentGroup $labs, AssessmentGroup $projects, bool $dryRun): void
    {
        if ($labs->exists && $projects->exists && $labs->id === $projects->id) {
            return;
        }

        $toMove = Assessment::where('assessment_group_id', $labs->id)
            ->whereRaw('LOWER(name) LIKE ?', ['project %'])
            ->get();

        foreach ($toMove as $a) {
            $this->line("  Move assessment #{$a->id} '{$a->name}' from Labs → Projects");
            if (! $dryRun) {
                $a->update(['assessment_group_id' => $projects->id]);
            }
        }
    }

    /**
     * @param  array<int, string>  $names
     */
    private function addMissingAssessments(?int $groupId, array $names, int $maxScore, bool $hasSubsections, int $startSortOrder, bool $dryRun): void
    {
        if ($groupId === null) {
            foreach ($names as $name) {
                $this->line("  Assessment create: {$name} (max={$maxScore}) [dry-run: group not yet created]");
            }

            return;
        }

        foreach ($names as $i => $name) {
            $exists = Assessment::where('assessment_group_id', $groupId)
                ->where('name', $name)
                ->exists();

            if ($exists) {
                $this->line("  Assessment exists: {$name}");

                continue;
            }

            $this->line("  Assessment create: {$name} (max={$maxScore})");
            if (! $dryRun) {
                Assessment::create([
                    'assessment_group_id' => $groupId,
                    'course_id' => $this->courseId,
                    'name' => $name,
                    'weight' => 1,
                    'max_raw_score' => $maxScore,
                    'normalized_to' => 100,
                    'has_subsections' => $hasSubsections,
                    'sort_order' => $startSortOrder + $i,
                ]);
            }
        }
    }
}
