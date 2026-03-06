<?php

namespace Tests\Feature\Exports;

use App\Enums\ExamStatus;
use App\Exports\GradeSheetExport;
use App\Filament\Pages\ClassReport;
use App\Models\Assessment;
use App\Models\AssessmentGroup;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\GradeResult;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class GradeSheetExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private CourseOffering $offering;

    private AssessmentGroup $caGroup;

    private Assessment $quiz;

    private Assessment $assignment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();

        $year = Year::factory()->create();
        $semester = Semester::factory()->create(['year_id' => $year->id]);
        $course = Course::factory()->create(['year_id' => $year->id, 'code' => 'CSC310', 'name' => 'Algorithms']);

        $this->offering = CourseOffering::factory()->create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'lecturer_id' => $this->admin->id,
            'ca_weight' => 50,
            'exam_weight' => 50,
        ]);

        $this->caGroup = AssessmentGroup::factory()->create([
            'course_offering_id' => $this->offering->id,
            'name' => 'Continuous Assessment',
            'type' => 'ca',
        ]);

        $this->quiz = Assessment::factory()->create([
            'assessment_group_id' => $this->caGroup->id,
            'course_id' => $course->id,
            'name' => 'Quiz 1',
            'max_raw_score' => 30,
            'sort_order' => 1,
        ]);

        $this->assignment = Assessment::factory()->create([
            'assessment_group_id' => $this->caGroup->id,
            'course_id' => $course->id,
            'name' => 'Assignment 1',
            'max_raw_score' => 50,
            'sort_order' => 2,
        ]);
    }

    protected function createStudentWithGrades(
        string $studentId,
        string $gender,
        float $quizScore,
        float $assignmentScore,
        float $caTotal,
        float $examScore,
        float $finalTotal,
        string $finalGrade,
        float $gradePoints,
    ): Enrollment {
        $student = Student::factory()->create([
            'student_id_number' => $studentId,
            'gender' => $gender,
            'program' => 'Computer Science',
        ]);

        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $this->offering->id,
            'ca_total' => $caTotal,
            'exam_score' => $examScore,
            'final_total' => $finalTotal,
            'final_grade' => $finalGrade,
            'grade_points' => $gradePoints,
        ]);

        GradeResult::factory()->create([
            'enrollment_id' => $enrollment->id,
            'assessment_id' => $this->quiz->id,
            'raw_score' => $quizScore,
        ]);

        GradeResult::factory()->create([
            'enrollment_id' => $enrollment->id,
            'assessment_id' => $this->assignment->id,
            'raw_score' => $assignmentScore,
        ]);

        return $enrollment;
    }

    protected function createThreeStudents(): void
    {
        // Student 1: Male, passes (B+)
        $this->createStudentWithGrades('SN000000100', 'Male', 25, 40, 65, 75, 70, 'B+', 3.5);

        // Student 2: Male, fails (D)
        $this->createStudentWithGrades('SN000000200', 'Male', 10, 15, 25, 20, 22, 'D', 0.0);

        // Student 3: Female, NE (absent — final_total=0 as per resolveGrade for absent)
        $student3 = Student::factory()->create([
            'student_id_number' => 'SN000000300',
            'gender' => 'Female',
            'program' => 'Computer Science',
        ]);
        Enrollment::factory()->create([
            'student_id' => $student3->id,
            'course_offering_id' => $this->offering->id,
            'ca_total' => 0,
            'exam_score' => 0,
            'final_total' => 0,
            'final_grade' => 'NE',
            'grade_points' => 0.0,
        ]);
    }

    protected function storeAndLoad(): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        $fileName = 'test_export_'.uniqid().'.xlsx';
        Excel::store(new GradeSheetExport($this->offering), $fileName, 'local');
        $fullPath = storage_path('app/private/'.$fileName);

        $reader = IOFactory::createReaderForFile($fullPath);
        $reader->setIncludeCharts(true);
        $spreadsheet = $reader->load($fullPath);

        @unlink($fullPath);

        return $spreadsheet;
    }

    public function test_export_produces_four_sheets(): void
    {
        $this->createThreeStudents();
        $spreadsheet = $this->storeAndLoad();

        $this->assertCount(4, $spreadsheet->getAllSheets());
        $this->assertEquals('Mark Sheet', $spreadsheet->getSheet(0)->getTitle());
        $this->assertEquals('Grade Summary', $spreadsheet->getSheet(1)->getTitle());
        $this->assertEquals('Gender Analysis', $spreadsheet->getSheet(2)->getTitle());
        $this->assertEquals('Charts', $spreadsheet->getSheet(3)->getTitle());
    }

    public function test_mark_sheet_has_dynamic_assessment_columns(): void
    {
        $this->createThreeStudents();
        $spreadsheet = $this->storeAndLoad();

        $markSheet = $spreadsheet->getSheet(0);
        $headingRow = [];
        $lastCol = $markSheet->getHighestColumn();
        $lastColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($lastCol);

        for ($col = 1; $col <= $lastColIndex; $col++) {
            $headingRow[] = $markSheet->getCellByColumnAndRow($col, 1)->getValue();
        }

        $this->assertContains('Quiz 1 /30', $headingRow);
        $this->assertContains('Assignment 1 /50', $headingRow);
    }

    public function test_mark_sheet_normalizes_scores_to_100(): void
    {
        // Quiz max=30, score=25 → (25/30)*100 = 83.33
        $this->createStudentWithGrades('SN000000100', 'Male', 25, 40, 65, 75, 70, 'B+', 3.5);
        $spreadsheet = $this->storeAndLoad();

        $markSheet = $spreadsheet->getSheet(0);

        // Find the Quiz 1 column
        $quizCol = null;
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($markSheet->getHighestColumn());
        for ($col = 1; $col <= $lastCol; $col++) {
            if ($markSheet->getCellByColumnAndRow($col, 1)->getValue() === 'Quiz 1 /30') {
                $quizCol = $col;
                break;
            }
        }

        $this->assertNotNull($quizCol, 'Quiz 1 column not found');
        $value = $markSheet->getCellByColumnAndRow($quizCol, 2)->getValue();
        $this->assertEqualsWithDelta(83.33, $value, 0.01);
    }

    public function test_mark_sheet_check_digit(): void
    {
        $this->createStudentWithGrades('SN000000789', 'Male', 20, 30, 50, 60, 55, 'C+', 2.5);
        $spreadsheet = $this->storeAndLoad();

        $markSheet = $spreadsheet->getSheet(0);

        // Check digit is the last column
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($markSheet->getHighestColumn());
        $checkDigit = $markSheet->getCellByColumnAndRow($lastCol, 2)->getValue();

        $this->assertEquals('[789]', $checkDigit);
    }

    public function test_mark_sheet_sorts_by_student_id(): void
    {
        $this->createStudentWithGrades('SN000000300', 'Male', 20, 30, 50, 60, 55, 'C+', 2.5);
        $this->createStudentWithGrades('SN000000100', 'Female', 25, 40, 65, 75, 70, 'B+', 3.5);
        $this->createStudentWithGrades('SN000000200', 'Male', 15, 25, 40, 40, 40, 'C', 2.0);

        $spreadsheet = $this->storeAndLoad();
        $markSheet = $spreadsheet->getSheet(0);

        // Student ID is column B (2)
        $this->assertEquals('SN000000100', $markSheet->getCellByColumnAndRow(2, 2)->getValue());
        $this->assertEquals('SN000000200', $markSheet->getCellByColumnAndRow(2, 3)->getValue());
        $this->assertEquals('SN000000300', $markSheet->getCellByColumnAndRow(2, 4)->getValue());
    }

    public function test_grade_summary_distribution_counts(): void
    {
        $this->createThreeStudents();
        $spreadsheet = $this->storeAndLoad();

        $summarySheet = $spreadsheet->getSheet(1);

        // Find the GRADE DISTRIBUTION section - Row 9 should have grade labels, Row 10 counts
        // Row 8 = header "GRADE DISTRIBUTION"
        // Row 9 = Grade | NE | D | D+ | ...
        // Row 10 = Count | ...
        $gradeRow = null;
        $maxRow = (int) $summarySheet->getHighestRow();
        for ($row = 1; $row <= $maxRow; $row++) {
            if ($summarySheet->getCellByColumnAndRow(1, $row)->getValue() === 'GRADE DISTRIBUTION') {
                $gradeRow = $row + 1; // labels row
                break;
            }
        }

        $this->assertNotNull($gradeRow, 'GRADE DISTRIBUTION section not found');

        $countRow = $gradeRow + 1;

        // Find "NE" column, "D" column, "B+" column
        $cols = [];
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($summarySheet->getHighestColumn());
        for ($col = 1; $col <= $lastCol; $col++) {
            $val = $summarySheet->getCellByColumnAndRow($col, $gradeRow)->getValue();
            if ($val !== null) {
                $cols[$val] = $col;
            }
        }

        // 1 student with B+, 1 with D, 1 with NE
        $this->assertEquals(1, $summarySheet->getCellByColumnAndRow($cols['NE'], $countRow)->getValue());
        $this->assertEquals(1, $summarySheet->getCellByColumnAndRow($cols['D'], $countRow)->getValue());
        $this->assertEquals(1, $summarySheet->getCellByColumnAndRow($cols['B+'], $countRow)->getValue());
    }

    public function test_grade_summary_pass_rate_excludes_ne(): void
    {
        $this->createThreeStudents();
        $spreadsheet = $this->storeAndLoad();

        $summarySheet = $spreadsheet->getSheet(1);

        // Find PASS / FAIL / NE SUMMARY section
        $maxRow = (int) $summarySheet->getHighestRow();
        $passFailHeaderRow = null;
        for ($row = 1; $row <= $maxRow; $row++) {
            $val = $summarySheet->getCellByColumnAndRow(1, $row)->getValue();
            if ($val !== null && str_contains((string) $val, 'PASS / FAIL / NE')) {
                $passFailHeaderRow = $row;
                break;
            }
        }

        $this->assertNotNull($passFailHeaderRow, 'PASS / FAIL / NE SUMMARY not found');

        // Count row: labels row + 1, counts row + 2, % row + 3
        $countRow = $passFailHeaderRow + 2;
        $pctRow = $passFailHeaderRow + 3;

        // Pass=1 (B+), Fail=1 (D), NE=1
        $this->assertEquals(1, $summarySheet->getCellByColumnAndRow(2, $countRow)->getValue()); // Pass
        $this->assertEquals(1, $summarySheet->getCellByColumnAndRow(3, $countRow)->getValue()); // Fail
        $this->assertEquals(1, $summarySheet->getCellByColumnAndRow(4, $countRow)->getValue()); // NE

        // Pass rate excluding NE: 1 pass / (1 pass + 1 fail) = 50%
        $passRate = $summarySheet->getCellByColumnAndRow(2, $pctRow)->getValue();
        $this->assertEquals('50%', $passRate);
    }

    public function test_gender_analysis_cross_tab_correct(): void
    {
        $this->createThreeStudents();
        $spreadsheet = $this->storeAndLoad();

        $genderSheet = $spreadsheet->getSheet(2);

        // Find cross-tab data - row 2 is the header, then gender rows follow
        // Row 1 = title, Row 2 = grade headers, Row 3+ = gender data
        $maxRow = (int) $genderSheet->getHighestRow();
        $maleRow = null;
        $femaleRow = null;

        for ($row = 1; $row <= $maxRow; $row++) {
            $val = $genderSheet->getCellByColumnAndRow(1, $row)->getValue();
            if ($val === 'Female') {
                $femaleRow = $femaleRow ?? $row;
            } elseif ($val === 'Male') {
                $maleRow = $maleRow ?? $row;
            }
        }

        $this->assertNotNull($maleRow, 'Male row not found');
        $this->assertNotNull($femaleRow, 'Female row not found');

        // Find grade column indices from header row (row 2)
        $cols = [];
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($genderSheet->getHighestColumn());
        for ($col = 1; $col <= $lastCol; $col++) {
            $val = $genderSheet->getCellByColumnAndRow($col, 2)->getValue();
            if ($val !== null) {
                $cols[$val] = $col;
            }
        }

        // Male: 1 B+, 1 D
        $this->assertEquals(1, $genderSheet->getCellByColumnAndRow($cols['B+'], $maleRow)->getValue());
        $this->assertEquals(1, $genderSheet->getCellByColumnAndRow($cols['D'], $maleRow)->getValue());

        // Female: 1 NE
        $this->assertEquals(1, $genderSheet->getCellByColumnAndRow($cols['NE'], $femaleRow)->getValue());
    }

    public function test_gender_analysis_includes_ne(): void
    {
        // Create a student with NE grade
        $student = Student::factory()->create([
            'student_id_number' => 'SN000000400',
            'gender' => 'Male',
        ]);
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $this->offering->id,
            'final_grade' => 'NE',
            'grade_points' => 0.0,
        ]);

        $spreadsheet = $this->storeAndLoad();
        $genderSheet = $spreadsheet->getSheet(2);

        // Find Male row and NE column
        $maxRow = (int) $genderSheet->getHighestRow();
        $maleRow = null;
        for ($row = 1; $row <= $maxRow; $row++) {
            if ($genderSheet->getCellByColumnAndRow(1, $row)->getValue() === 'Male') {
                $maleRow = $row;
                break;
            }
        }

        $cols = [];
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($genderSheet->getHighestColumn());
        for ($col = 1; $col <= $lastCol; $col++) {
            $val = $genderSheet->getCellByColumnAndRow($col, 2)->getValue();
            if ($val !== null) {
                $cols[$val] = $col;
            }
        }

        $this->assertNotNull($maleRow);
        $this->assertEquals(1, $genderSheet->getCellByColumnAndRow($cols['NE'], $maleRow)->getValue());
    }

    public function test_charts_sheet_has_two_charts(): void
    {
        $this->createThreeStudents();
        $spreadsheet = $this->storeAndLoad();

        $chartsSheet = $spreadsheet->getSheet(3);
        $charts = $chartsSheet->getChartCollection();

        $this->assertCount(2, $charts);
    }

    public function test_export_handles_empty_enrollments(): void
    {
        // No students enrolled — should not throw
        $spreadsheet = $this->storeAndLoad();

        $this->assertCount(4, $spreadsheet->getAllSheets());
        $this->assertEquals('Mark Sheet', $spreadsheet->getSheet(0)->getTitle());
    }

    public function test_export_handles_no_ca_assessments(): void
    {
        // Remove the CA group assessments
        $this->quiz->delete();
        $this->assignment->delete();
        $this->caGroup->delete();

        $student = Student::factory()->create(['student_id_number' => 'SN000000100', 'gender' => 'Male']);
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $this->offering->id,
            'ca_total' => 0,
            'exam_score' => 60,
            'final_total' => 30,
            'final_grade' => 'D',
            'grade_points' => 0.0,
        ]);

        $spreadsheet = $this->storeAndLoad();
        $markSheet = $spreadsheet->getSheet(0);

        // Should only have base columns (no assessment-specific columns)
        $headings = [];
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($markSheet->getHighestColumn());
        for ($col = 1; $col <= $lastCol; $col++) {
            $headings[] = $markSheet->getCellByColumnAndRow($col, 1)->getValue();
        }

        // Should have base columns including Category, Exam Grade, Def, Sup — but no assessment columns
        $this->assertContains('CA/100', $headings);
        $this->assertContains('Exam/100', $headings);
        $this->assertContains('Category', $headings);
        $this->assertContains('Exam Grade', $headings);
        $this->assertNotContains('Quiz 1 /30', $headings);
        $this->assertNotContains('Assignment 1 /50', $headings);
    }

    public function test_export_downloadable_from_class_report(): void
    {
        $this->actingAs($this->admin);

        $student = Student::factory()->create();
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $this->offering->id,
            'final_total' => 70,
            'final_grade' => 'B+',
            'grade_points' => 3.5,
        ]);

        $page = new ClassReport;
        $page->course_offering_id = $this->offering->id;

        $response = $page->exportExcel();

        $this->assertNotNull($response);
        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\BinaryFileResponse::class, $response);
    }

    public function test_mark_sheet_header_order_matches_unza(): void
    {
        $this->createThreeStudents();
        $spreadsheet = $this->storeAndLoad();

        $markSheet = $spreadsheet->getSheet(0);
        $headingRow = [];
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($markSheet->getHighestColumn());

        for ($col = 1; $col <= $lastCol; $col++) {
            $headingRow[] = $markSheet->getCellByColumnAndRow($col, 1)->getValue();
        }

        $expected = ['#', 'Student ID', 'Name', 'Gender', 'Programme', 'Category', 'Quiz 1 /30', 'Assignment 1 /50', 'CA/100', 'Exam/100', 'Final Mark/100', 'Grade', 'Exam Grade', 'Def', 'Sup', 'GP', 'Comment', 'Check Digit'];

        $this->assertEquals($expected, $headingRow);
    }

    public function test_mark_sheet_name_is_surname_comma_firstname(): void
    {
        $student = Student::factory()->create([
            'student_id_number' => 'SN000000999',
            'first_name' => 'John',
            'last_name' => 'Smith',
            'gender' => 'Male',
        ]);

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $this->offering->id,
            'final_total' => 70,
            'final_grade' => 'B+',
            'grade_points' => 3.5,
        ]);

        $spreadsheet = $this->storeAndLoad();
        $markSheet = $spreadsheet->getSheet(0);

        // Name is column C (3)
        $name = $markSheet->getCellByColumnAndRow(3, 2)->getValue();
        $this->assertEquals('SMITH, John', $name);
    }

    public function test_mark_sheet_category_column(): void
    {
        $student = Student::factory()->create([
            'student_id_number' => 'SN000001001',
            'study_mode' => 'PT',
            'gender' => 'Male',
        ]);

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $this->offering->id,
            'final_total' => 60,
            'final_grade' => 'C+',
            'grade_points' => 2.5,
        ]);

        $studentDefault = Student::factory()->create([
            'student_id_number' => 'SN000001002',
            'study_mode' => null,
            'gender' => 'Female',
        ]);

        Enrollment::factory()->create([
            'student_id' => $studentDefault->id,
            'course_offering_id' => $this->offering->id,
            'final_total' => 50,
            'final_grade' => 'C',
            'grade_points' => 2.0,
        ]);

        $spreadsheet = $this->storeAndLoad();
        $markSheet = $spreadsheet->getSheet(0);

        // Find Category column
        $catCol = null;
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($markSheet->getHighestColumn());
        for ($col = 1; $col <= $lastCol; $col++) {
            if ($markSheet->getCellByColumnAndRow($col, 1)->getValue() === 'Category') {
                $catCol = $col;
                break;
            }
        }

        $this->assertNotNull($catCol, 'Category column not found');

        // Rows sorted by student ID: SN000001001 first, SN000001002 second
        $this->assertEquals('PT', $markSheet->getCellByColumnAndRow($catCol, 2)->getValue());
        $this->assertEquals('FT', $markSheet->getCellByColumnAndRow($catCol, 3)->getValue());
    }

    public function test_mark_sheet_deferred_and_supplementary_columns(): void
    {
        $studentDef = Student::factory()->create([
            'student_id_number' => 'SN000002001',
            'gender' => 'Male',
        ]);

        Enrollment::factory()->create([
            'student_id' => $studentDef->id,
            'course_offering_id' => $this->offering->id,
            'exam_status' => ExamStatus::Deferred,
            'final_total' => 0,
            'final_grade' => 'DV',
            'grade_points' => 0.0,
        ]);

        $studentSup = Student::factory()->create([
            'student_id_number' => 'SN000002002',
            'gender' => 'Female',
        ]);

        Enrollment::factory()->create([
            'student_id' => $studentSup->id,
            'course_offering_id' => $this->offering->id,
            'exam_status' => ExamStatus::Supplementary,
            'final_total' => 50,
            'final_grade' => 'C',
            'grade_points' => 2.0,
        ]);

        $spreadsheet = $this->storeAndLoad();
        $markSheet = $spreadsheet->getSheet(0);

        // Find Def and Sup column indices
        $defCol = null;
        $supCol = null;
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($markSheet->getHighestColumn());
        for ($col = 1; $col <= $lastCol; $col++) {
            $val = $markSheet->getCellByColumnAndRow($col, 1)->getValue();
            if ($val === 'Def') {
                $defCol = $col;
            }
            if ($val === 'Sup') {
                $supCol = $col;
            }
        }

        $this->assertNotNull($defCol, 'Def column not found');
        $this->assertNotNull($supCol, 'Sup column not found');

        // Row 2 = SN000002001 (deferred), Row 3 = SN000002002 (supplementary)
        $this->assertEquals('DV', $markSheet->getCellByColumnAndRow($defCol, 2)->getValue());
        $this->assertNull($markSheet->getCellByColumnAndRow($supCol, 2)->getValue());

        $this->assertNull($markSheet->getCellByColumnAndRow($defCol, 3)->getValue());
        $this->assertEquals('SP', $markSheet->getCellByColumnAndRow($supCol, 3)->getValue());
    }

    public function test_mark_sheet_exam_grade_column(): void
    {
        $student = Student::factory()->create([
            'student_id_number' => 'SN000003001',
            'gender' => 'Male',
        ]);

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $this->offering->id,
            'exam_score' => 75,
            'final_total' => 70,
            'final_grade' => 'B+',
            'grade_points' => 3.5,
        ]);

        $spreadsheet = $this->storeAndLoad();
        $markSheet = $spreadsheet->getSheet(0);

        // Find Exam Grade column
        $examGradeCol = null;
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($markSheet->getHighestColumn());
        for ($col = 1; $col <= $lastCol; $col++) {
            if ($markSheet->getCellByColumnAndRow($col, 1)->getValue() === 'Exam Grade') {
                $examGradeCol = $col;
                break;
            }
        }

        $this->assertNotNull($examGradeCol, 'Exam Grade column not found');

        // 75 should be B+ according to UNZA grading scale
        $this->assertEquals('B+', $markSheet->getCellByColumnAndRow($examGradeCol, 2)->getValue());
    }
}
