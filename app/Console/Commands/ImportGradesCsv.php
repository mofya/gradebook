<?php

namespace App\Console\Commands;

use App\Imports\GradesImport;
use App\Models\CourseOffering;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;

#[Signature('app:import-grades-csv {offering : Course offering ID} {--file= : Path to long-format CSV} {--base64= : Base64-encoded CSV content}')]
#[Description('Import a long-format grades CSV (student_id, assessment_name, raw_score) into an offering.')]
class ImportGradesCsv extends Command
{
    public function handle(): int
    {
        $offering = CourseOffering::find((int) $this->argument('offering'));

        if (! $offering) {
            $this->error("Course offering #{$this->argument('offering')} not found.");

            return self::FAILURE;
        }

        $tempFile = null;
        $filePath = $this->option('file');
        $base64 = $this->option('base64');

        if ($base64) {
            $content = base64_decode($base64, strict: true);
            if ($content === false) {
                $this->error('Invalid base64 input.');

                return self::FAILURE;
            }
            $tempFile = tempnam(sys_get_temp_dir(), 'grades_').'.csv';
            file_put_contents($tempFile, $content);
            $filePath = $tempFile;
        }

        if (! $filePath || ! is_file($filePath)) {
            $this->error('Provide either --file=<path> or --base64=<encoded-csv>.');

            return self::FAILURE;
        }

        try {
            $import = new GradesImport($offering);
            Excel::import($import, $filePath);
        } finally {
            if ($tempFile !== null && is_file($tempFile)) {
                @unlink($tempFile);
            }
        }

        $this->info("Imported: {$import->getImportedCount()}");
        $this->info("Skipped: {$import->getSkippedCount()}");

        foreach ($import->getSkippedDetails() as $detail) {
            $this->line("  - {$detail}");
        }

        return self::SUCCESS;
    }
}
