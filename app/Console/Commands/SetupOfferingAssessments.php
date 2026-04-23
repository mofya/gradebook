<?php

namespace App\Console\Commands;

use App\Models\Assessment;
use App\Models\AssessmentGroup;
use App\Models\CourseOffering;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('app:setup-offering-assessments {offering : Offering ID} {--spec-file= : Path to JSON spec} {--spec-base64= : Base64-encoded JSON spec} {--dry-run}')]
#[Description('Create/update assessment groups and assessments on an offering from a JSON spec (idempotent).')]
class SetupOfferingAssessments extends Command
{
    public function handle(): int
    {
        $offering = CourseOffering::find((int) $this->argument('offering'));
        if (! $offering) {
            $this->error("Offering #{$this->argument('offering')} not found.");

            return self::FAILURE;
        }

        $spec = $this->loadSpec();
        if ($spec === null) {
            return self::FAILURE;
        }

        $groups = $spec['groups'] ?? [];
        if (! is_array($groups) || $groups === []) {
            $this->error('Spec must contain a non-empty "groups" array.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $this->info("Target offering: #{$offering->id} ({$offering->course->code}).");

        if ($dryRun) {
            $this->warn('Dry run — no writes will be performed.');
        }

        DB::transaction(function () use ($offering, $groups, $dryRun): void {
            foreach ($groups as $groupSpec) {
                $this->applyGroup($offering, $groupSpec, $dryRun);
            }
        });

        $this->info($dryRun ? 'Dry run complete.' : 'Done.');

        return self::SUCCESS;
    }

    /**
     * @return array{groups: array<int, mixed>}|null
     */
    private function loadSpec(): ?array
    {
        $file = $this->option('spec-file');
        $b64 = $this->option('spec-base64');

        if ($b64) {
            $json = base64_decode($b64, strict: true);
            if ($json === false) {
                $this->error('Invalid base64 input.');

                return null;
            }
        } elseif ($file && is_file($file)) {
            $json = file_get_contents($file);
        } else {
            $this->error('Provide either --spec-file=<path> or --spec-base64=<encoded-json>.');

            return null;
        }

        $spec = json_decode($json, true);
        if (! is_array($spec)) {
            $this->error('Spec is not valid JSON.');

            return null;
        }

        return $spec;
    }

    /**
     * @param  array<string, mixed>  $groupSpec
     */
    private function applyGroup(CourseOffering $offering, array $groupSpec, bool $dryRun): void
    {
        $name = (string) ($groupSpec['name'] ?? '');
        if ($name === '') {
            $this->warn('Skipping group with no name.');

            return;
        }

        $groupAttributes = [
            'type' => $groupSpec['type'] ?? 'ca',
            'weight_percentage' => $groupSpec['weight_percentage'] ?? 0,
            'weight_mode' => $groupSpec['weight_mode'] ?? 'percentage',
            'sort_order' => $groupSpec['sort_order'] ?? 1,
        ];

        $group = AssessmentGroup::where('course_offering_id', $offering->id)
            ->where('name', $name)
            ->first();

        if ($group) {
            $this->line("  Group exists: {$name} (#{$group->id}) — updating");
            if (! $dryRun) {
                $group->update($groupAttributes);
            }
        } else {
            $this->line("  Group create: {$name}");
            if (! $dryRun) {
                $group = AssessmentGroup::create(array_merge($groupAttributes, [
                    'course_offering_id' => $offering->id,
                    'name' => $name,
                ]));
            }
        }

        $assessments = $groupSpec['assessments'] ?? [];
        if (! is_array($assessments)) {
            return;
        }

        foreach ($assessments as $i => $aSpec) {
            $this->applyAssessment($offering, $group, $aSpec, $i, $dryRun);
        }
    }

    /**
     * @param  array<string, mixed>  $aSpec
     */
    private function applyAssessment(CourseOffering $offering, ?AssessmentGroup $group, array $aSpec, int $index, bool $dryRun): void
    {
        $name = (string) ($aSpec['name'] ?? '');
        if ($name === '') {
            return;
        }

        $attributes = [
            'course_id' => $offering->course_id,
            'name' => $name,
            'weight' => $aSpec['weight'] ?? 1,
            'max_raw_score' => $aSpec['max_raw_score'] ?? 100,
            'normalized_to' => $aSpec['normalized_to'] ?? 100,
            'has_subsections' => $aSpec['has_subsections'] ?? false,
            'sort_order' => $aSpec['sort_order'] ?? ($index + 1),
        ];

        if ($group === null) {
            $this->line("  Assessment create: {$name} [dry-run: group not yet created]");

            return;
        }

        $existing = Assessment::where('assessment_group_id', $group->id)
            ->where('name', $name)
            ->first();

        if ($existing) {
            $this->line("  Assessment exists: {$name} — leaving unchanged");

            return;
        }

        $renameFrom = $aSpec['rename_from'] ?? null;
        if ($renameFrom) {
            // Look anywhere in the offering — lets the spec move an assessment
            // across groups (e.g. out of a misfiled parent group) while
            // preserving its ID and grade_results.
            $toRename = Assessment::whereHas(
                'assessmentGroup',
                fn ($q) => $q->where('course_offering_id', $offering->id),
            )
                ->where('name', $renameFrom)
                ->first();

            if ($toRename) {
                $moveAcrossGroups = $toRename->assessment_group_id !== $group->id;
                $verb = $moveAcrossGroups ? 'move+rename' : 'rename';
                $this->line("  Assessment {$verb}: '{$renameFrom}' → '{$name}'");
                if (! $dryRun) {
                    $toRename->update([
                        'name' => $name,
                        'assessment_group_id' => $group->id,
                    ]);
                }

                return;
            }
        }

        $this->line("  Assessment create: {$name} (max={$attributes['max_raw_score']})");
        if (! $dryRun) {
            Assessment::create(array_merge($attributes, [
                'assessment_group_id' => $group->id,
            ]));
        }
    }
}
