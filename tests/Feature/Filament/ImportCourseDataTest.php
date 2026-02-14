<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\ImportCourseData;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Semester;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ImportCourseDataTest extends TestCase
{
    use RefreshDatabase;

    private CourseOffering $offering;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());

        $year = Year::factory()->create();
        $semester = Semester::factory()->create(['year_id' => $year->id]);
        $course = Course::factory()->create(['year_id' => $year->id]);

        $this->offering = CourseOffering::factory()->create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
        ]);
    }

    public function test_page_renders_for_authenticated_admin(): void
    {
        $this->get(ImportCourseData::getUrl())->assertSuccessful();
    }

    public function test_course_offering_selection_works(): void
    {
        Livewire::test(ImportCourseData::class)
            ->set('data.courseOfferingId', $this->offering->id)
            ->assertSet('data.courseOfferingId', $this->offering->id);
    }

    public function test_parse_file_requires_course_offering_and_file(): void
    {
        Livewire::test(ImportCourseData::class)
            ->call('parseFile')
            ->assertNotified();
    }

    public function test_reset_import_returns_to_step_one(): void
    {
        Livewire::test(ImportCourseData::class)
            ->set('currentStep', 4)
            ->call('resetImport')
            ->assertSet('currentStep', 1)
            ->assertSet('data.courseOfferingId', null);
    }

    public function test_parse_file_requires_sheet_selection_for_multi_sheet_workbook(): void
    {
        $filePath = $this->createWorkbook([
            'Sheet A' => [
                ['Student ID', 'First Name', 'Last Name', 'Email', 'Quiz 1 (10)', 'Exam/60'],
                ['SN101', 'Alice', 'Smith', 'alice@example.com', 8, 45],
            ],
            'Sheet B' => [
                ['Student ID', 'First Name', 'Last Name', 'Email', 'Quiz 1 (10)', 'Exam/60'],
                ['SN102', 'Bob', 'Jones', 'bob@example.com', 7, 40],
            ],
        ], 'multi_sheet_import_test.xlsx');

        // With auto-select, the first non-report sheet is auto-selected
        Livewire::test(ImportCourseData::class)
            ->set('data.courseOfferingId', $this->offering->id)
            ->set('data.file', $filePath)
            ->assertSet('data.sheetName', 'Sheet A')
            ->call('parseFile')
            ->assertSet('currentStep', 3);
    }

    public function test_confirm_import_is_blocked_when_preflight_fails(): void
    {
        $filePath = $this->createWorkbook([
            'Grades' => [
                ['Student ID', 'First Name', 'Last Name', 'Email', 'Quiz 1 (10)', 'Exam/60'],
                ['SN201', 'John', 'Doe', 'invalid-email', 8, 45],
            ],
        ], 'preflight_block_import_test.xlsx');

        Livewire::test(ImportCourseData::class)
            ->set('data.courseOfferingId', $this->offering->id)
            ->set('data.file', $filePath)
            ->call('parseFile')
            ->assertSet('currentStep', 3)
            ->call('confirmAndImport')
            ->assertNotified()
            ->assertSet('currentStep', 3);
    }

    /**
     * @param  array<string, array<int, array<int, mixed>>>  $sheets
     */
    private function createWorkbook(array $sheets, string $fileName): string
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0);

        foreach ($sheets as $sheetName => $rows) {
            $worksheet = new Worksheet($spreadsheet, $sheetName);
            $spreadsheet->addSheet($worksheet);
            $worksheet->fromArray($rows, null, 'A1');
        }

        $directory = storage_path('app/private/course-data-imports');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $absolutePath = $directory.'/'.$fileName;
        $writer = new Xlsx($spreadsheet);
        $writer->save($absolutePath);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return 'course-data-imports/'.$fileName;
    }
}
