# UNZA GradeBook — SRS v1.2 Implementation Guide

**Date:** February 2026
**Basis:** Compatibility analysis of four sample workbooks (CSC2202, CSC3301, CSC3712, CSC4505) against SRS v1.1
**Status:** Approved changes ready for implementation

---

## Change Summary

| # | Change | Type | SRS Sections Affected |
|---|--------|------|-----------------------|
| 1 | Assessment Group Aggregation Mode | Data model + Engine | 3.8, 4.2 |
| 2 | Sheet Selection Wizard Step | Functional | FR-060 |
| 3 | Weight Configuration Wizard Step | Functional | FR-060, FR-040 |
| 4 | Study Mode on Enrollment | Data model | 3.7 |
| 5 | Enrollment Comment Field | Data model | 3.7 |
| 6 | UNZA Mark Sheet Export | Functional | FR-086 (new) |
| 7 | Pass/Fail Formula Specification | Functional | FR-084 |
| 8 | Grading Scheme Boundary Correction | Data correction | Appendix 10.1 |
| 9 | Expanded Import Validation | Functional | FR-060 |
| 10 | Column Name Synonyms | Documentation | FR-031 |

---

## Change 1: Assessment Group Aggregation Mode

### Problem
CSC3301 has a "Make Up Test" column and a "Test" column. The lecturer uses `=MAX(MakeUp, Test)` to pick the better score. Only the MAX result feeds into CA. The current SRS only supports weighted average aggregation within assessment groups.

### Database Migration

```php
// Migration: add_aggregation_to_assessment_groups
Schema::table('assessment_groups', function (Blueprint $table) {
    $table->enum('aggregation_mode', ['WEIGHTED_AVERAGE', 'MAX', 'DROP_LOWEST'])
          ->default('WEIGHTED_AVERAGE')
          ->after('weight_mode');
    $table->unsignedInteger('drop_count')
          ->default(0)
          ->after('aggregation_mode');
});
```

**Fields:**
- `aggregation_mode` — ENUM: `WEIGHTED_AVERAGE` (default, current behaviour), `MAX` (best of N), `DROP_LOWEST` (drop N lowest then average the rest)
- `drop_count` — INTEGER, default 0. Only used when mode is `DROP_LOWEST`. Specifies how many lowest scores to drop before averaging.

### Computation Engine Changes (Section 4.2)

The CA computation must branch on `aggregation_mode`:

```php
// In the grade computation service
public function computeGroupContribution(AssessmentGroup $group, Enrollment $enrollment): float
{
    $results = $this->getNonExcusedResults($group, $enrollment);

    if ($results->isEmpty()) {
        return 0;
    }

    $normalizedScores = $results->map(fn ($r) => $r->normalized_score);

    $groupAverage = match ($group->aggregation_mode) {
        'WEIGHTED_AVERAGE' => $this->weightedAverage($results, $group),
        'MAX'              => $normalizedScores->max(),
        'DROP_LOWEST'      => $this->dropLowestAverage($results, $group),
    };

    return match ($group->weight_mode) {
        'points'     => $groupAverage * ($group->weight_points / 100),
        'percentage' => $groupAverage * ($group->weight_percentage / 100),
    };
}

private function dropLowestAverage(Collection $results, AssessmentGroup $group): float
{
    $scores = $results->pluck('normalized_score')->sort()->values();
    $toDrop = min($group->drop_count, $scores->count() - 1); // Always keep at least one
    $kept = $scores->slice($toDrop);
    return $kept->avg();
}
```

### Filament Admin UI

In the AssessmentGroup resource form:
- Add a Select field for `aggregation_mode` with the three options
- Add a Numeric field for `drop_count`, visible only when mode is `DROP_LOWEST`
- Add help text: "MAX: takes the highest score from this group (e.g., test vs make-up test). DROP_LOWEST: drops the N lowest scores before averaging."

### Example Usage
For CSC3301's test/make-up-test scenario:
1. Create assessment group "Tests" with `aggregation_mode = MAX`
2. Add two assessments: "Test" and "Make Up Test"
3. Engine picks the higher normalized score of the two

---

## Change 2: Sheet Selection Wizard Step (FR-060 Step 2)

### Problem
CSC2202 and CSC3712 have 4–6 sheets per workbook (Data, Grade Summary, Gender Analysis, Charts, Entered, Marksheet). Without sheet selection, the system might try to import a report sheet.

### Implementation

This is a new step in the FR-060 import wizard, inserted between file upload and column mapping.

**When to show:** Only for `.xlsx` files with more than one sheet. Skip for `.csv` files and single-sheet `.xlsx` files.

**Sheet listing:** Use PhpSpreadsheet to enumerate sheets:

```php
use PhpOffice\PhpSpreadsheet\IOFactory;

$reader = IOFactory::createReader('Xlsx');
$reader->setReadDataOnly(true);
$spreadsheet = $reader->load($filePath);
$sheetNames = $spreadsheet->getSheetNames();

// If only one sheet, skip this step
if (count($sheetNames) === 1) {
    return redirect()->to($nextStep);
}
```

**Auto-selection heuristic** (applied in priority order):
1. Sheet named "Data" (case-insensitive) → auto-select
2. Sheet name matching the course code (e.g., "CSC3301") → auto-select
3. Sheet with the most columns → auto-select as fallback

**Report sheet flagging:** Flag sheets whose names match any of these patterns (case-insensitive) as "likely report — not data":
- Grade Summary, Gender Analysis, Charts, Metadata, Entered, Marksheet, Mark Sheet, Report, Statistics, Summary

**UI components:**
- Radio button list of sheet names
- Auto-selected sheet is pre-checked with "(Recommended)" label
- Flagged report sheets show a warning icon and "(Report sheet — verify before importing)"
- 5-row preview table for the currently selected sheet, loaded via AJAX/Livewire on selection change

**Livewire component sketch:**

```php
class SheetSelector extends Component
{
    public string $filePath;
    public array $sheets = [];
    public ?string $selectedSheet = null;
    public array $previewRows = [];

    public function mount(string $filePath): void
    {
        $this->filePath = $filePath;
        $this->sheets = $this->analyzeSheets($filePath);
        $this->selectedSheet = $this->autoSelect($this->sheets);
        $this->loadPreview();
    }

    public function updatedSelectedSheet(): void
    {
        $this->loadPreview();
    }

    private function loadPreview(): void
    {
        // Load first 5 data rows + header from selected sheet
        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly([$this->selectedSheet]);
        $spreadsheet = $reader->load($this->filePath);
        $worksheet = $spreadsheet->getActiveSheet();

        $this->previewRows = [];
        foreach ($worksheet->getRowIterator(1, 6) as $row) {
            $rowData = [];
            foreach ($row->getCellIterator() as $cell) {
                $rowData[] = $cell->getValue();
            }
            $this->previewRows[] = $rowData;
        }
    }

    private function analyzeSheets(string $filePath): array
    {
        // Returns array of ['name' => ..., 'columns' => ..., 'isReport' => bool]
    }

    private function autoSelect(array $sheets): string
    {
        // Apply heuristic: Data → course code → widest
    }
}
```

---

## Change 3: Weight Configuration Wizard Step (FR-060 Step 4)

### Problem
Every workbook embeds assessment weights in the CA formula, like `=(F2*0.025)+(G2*0.025)+(H2*0.1)+(I2*0.2)`. Lecturers shouldn't have to re-type weights.

### Implementation

This is a new step in the FR-060 wizard, after column mapping (Step 3) and before data preview (Step 5).

### Column-by-Column Matching Approach

Rather than mapping all columns at once (which makes it hard to identify which column caused an error), the wizard processes one column at a time:

**Step 3 (Column Mapping) revised flow:**

The system presents the lecturer with each detected assessment column sequentially:

1. Show column header from the file (e.g., "Quiz 1")
2. Show the first 5 values as a preview
3. Ask: "What is this column?" with options:
   - Assessment score (raw)
   - Assessment score (normalised)
   - Computed column (skip — e.g., CA(40), Course Total, Grade)
   - Identity column (Student ID, Name, Gender, etc.)
   - Not needed (skip)
4. If the user selects "Assessment score (raw)":
   - Prompt for: assessment name (pre-filled from header), max raw score (auto-detected from data), and which assessment group it belongs to
5. If "Assessment score (normalised)":
   - Prompt to pair it with the corresponding raw score column
6. Validate immediately — any data issue for this column is shown right there

This makes errors immediately traceable to a specific column.

**Alternative (batch mode):** For experienced users or files with many columns, offer a "Map all columns" mode that shows the traditional Filament-style column mapping table. The column-by-column mode is the default for files with ≤15 assessment columns.

### Weight Extraction from CA Formula

After all assessment columns are mapped, the system extracts weights from the CA(40) formula cell:

```php
public function extractWeights(string $filePath, string $sheetName, int $formulaRow = 2): array
{
    $reader = IOFactory::createReader('Xlsx');
    // Do NOT set readDataOnly — we need formulas
    $reader->setLoadSheetsOnly([$sheetName]);
    $spreadsheet = $reader->load($filePath);
    $worksheet = $spreadsheet->getActiveSheet();

    // Find the CA column (header contains "CA" or "Continuous Assessment")
    $caColumn = $this->findCAColumn($worksheet);

    if (!$caColumn) {
        return ['success' => false, 'reason' => 'CA column not found'];
    }

    $cellCoordinate = $caColumn . $formulaRow;
    $cell = $worksheet->getCell($cellCoordinate);

    // Check if cell contains a formula
    if (!str_starts_with((string) $cell->getValue(), '=')) {
        return [
            'success' => false,
            'reason' => 'CA cell contains a value, not a formula. Manual weight entry required.',
        ];
    }

    $formula = $cell->getValue(); // e.g., "=(F2*0.025)+(G2*0.025)+(H2*0.1)+(I2*0.2)"

    // Extract (column_ref, weight) pairs
    preg_match_all('/([A-Z]+)\d+\s*\*\s*(\d+\.?\d*)/', $formula, $matches, PREG_SET_ORDER);

    $weights = [];
    foreach ($matches as $match) {
        $colLetter = $match[1];
        $weight = (float) $match[2];
        $headerName = $worksheet->getCell($colLetter . '1')->getValue();

        $weights[] = [
            'column'     => $colLetter,
            'header'     => $headerName,
            'weight'     => $weight,
            'ca_points'  => $weight * 100, // Convert 0.025 → 2.5 points out of 100
        ];
    }

    $totalWeight = array_sum(array_column($weights, 'weight'));

    return [
        'success'      => true,
        'weights'      => $weights,
        'total_weight'  => $totalWeight,
        'formula'      => $formula,
    ];
}
```

### Weight Configuration UI

Display a table with:

| Assessment | Detected Weight | Points (out of 40) | Override | ✓ |
|------------|-----------------|---------------------|----------|---|
| Quiz 1 | 0.025 | 1.0 | [editable] | ✓ |
| Quiz 2 | 0.025 | 1.0 | [editable] | ✓ |
| Test 1 | 0.10 | 4.0 | [editable] | ✓ |
| Assignment | 0.20 | 8.0 | [editable] | ✓ |
| **Total** | **0.35** | **14.0** | | |

**Validation rules:**
- Running sum should equal the CA weight (e.g., 0.40). Warn (not block) if sum ≠ 0.40 (±0.01 tolerance)
- Each individual weight must be > 0
- If weights cannot be extracted (value-only CA cell), show empty weight table for manual entry

**On confirmation:** The system creates:
1. Assessment groups (or uses existing ones if the lecturer assigned groups during column mapping)
2. Assessment records with the confirmed weights
3. Links each mapped column to its assessment for the data import step

### Normalisation at Weight Configuration

When the lecturer confirms a column as a raw assessment score, the system also shows:
- The detected `max_raw_score` (max value in the column, or inferred from data)
- The `normalized_to` value (default 100)
- A toggle: "Normalise scores?" (default: yes)

This handles normalisation at import time without needing a separate normalisation step.

---

## Change 4: Study Mode on Enrollment

### Problem
UNZA has Regular, Parallel, and Distance Education students. The mark sheet requires a "Category" field. The SRS has no study mode.

### Database Migration

```php
// Migration: add_study_mode_to_enrollments
Schema::table('enrollments', function (Blueprint $table) {
    $table->enum('study_mode', ['REGULAR', 'PARALLEL', 'DISTANCE', 'OTHER'])
          ->default('REGULAR')
          ->after('source');
});
```

**Why on enrollments, not students:** A student could be Regular in one course and Parallel in another (e.g., cross-registered).

### Filament Admin UI
- Add a Select field to the Enrollment form/table
- Default to REGULAR so existing workflows are unaffected
- Include in the mark sheet export (Change 6)
- Allow bulk update via table action (e.g., "Set study mode for all enrollments")

### Import Handling
- If the uploaded file has a "Category" or "Study Mode" column, map it during import
- Auto-map values: "REGULAR FULL-TIME" → REGULAR, "PARALLEL" → PARALLEL, "DISTANCE" → DISTANCE

---

## Change 5: Enrollment Comment Field

### Problem
Lecturers use a "Comment" column for free-text annotations (late registration, readmitted student, etc.). The SRS only has `override_reason` which is specifically for grade overrides.

### Database Migration

```php
// Migration: add_comment_to_enrollments
Schema::table('enrollments', function (Blueprint $table) {
    $table->text('comment')->nullable()->after('enrolled_at');
});
```

**Distinction from `override_reason`:**
- `comment` — Optional, free-text, general purpose. Can be set at any time.
- `ca_override_reason` / `final_override_reason` — Mandatory when the corresponding override is set. Tied to a specific override action. Creates audit log entry.

### Filament Admin UI
- Add a Textarea field to the Enrollment form
- Show as a column in the enrollment table (truncated, expandable)
- Include in the import column mapping as an optional mapped column
- Include in exports and reports

---

## Change 6: UNZA Mark Sheet Export (FR-086 — New)

### Problem
The UNZA mark sheet is a specific institutional template that lecturers print and take to the SIS for data entry. Generic class reports don't match this format.

### Template Specification

**Header section:**
```
UNIVERSITY OF ZAMBIA
SCHOOL OF NATURAL SCIENCES — DEPARTMENT OF COMPUTER SCIENCE
MARK SHEET FOR: [Semester] [Academic Year]
Course: [Course Code] — [Course Name]
Study Mode: [Study Mode]
Total Students: [Count]
Lecturer: [Lecturer Name]
```

**Data columns (in order):**
1. `[n]` — Sequential row number in square brackets (1-indexed)
2. `Student No` — Full student number
3. `Check` — Last 3 digits of student number in parentheses, e.g., `(456)`
4. `Surname` — Last name
5. `Other Names` — First name
6. `Sex` — M/F
7. One column per assessment — Normalised scores
8. `CA(40)` — Continuous assessment total
9. `Exam(60)` — Examination score
10. `Total(100)` — Course total
11. `Grade` — Letter grade
12. `Def` — Deferred exam placeholder (blank or score if deferred exam taken)
13. `Sup` — Supplementary exam placeholder (blank or score)
14. `Comment` — Enrollment comment

**Sorting:** Alphabetical by Surname, then Other Names.

**Footer section:**
```
Grade Distribution: A+: [n]  A: [n]  B+: [n]  B: [n]  C+: [n]  C: [n]  D+: [n]  D: [n]  NE: [n]
Pass: [n] ([%])  Fail: [n] ([%])  NE: [n]  Total: [n]
```

### Implementation

Use PhpSpreadsheet or Filament Export Actions to generate the mark sheet:

```php
class MarkSheetExport
{
    public function __construct(
        private CourseOffering $offering,
    ) {}

    public function generate(): string
    {
        $enrollments = $this->offering->enrollments()
            ->with(['student', 'gradeResults.assessment'])
            ->whereIn('status', ['active'])
            ->get()
            ->sortBy(fn ($e) => $e->student->last_name . $e->student->first_name);

        // Build spreadsheet with PhpSpreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Write header block
        $this->writeHeader($sheet);

        // Write column headers
        $this->writeColumnHeaders($sheet);

        // Write student rows with sequential numbering
        $rowNum = 1;
        foreach ($enrollments as $enrollment) {
            $this->writeStudentRow($sheet, $enrollment, $rowNum++);
        }

        // Write footer with grade distribution
        $this->writeFooter($sheet, $enrollments);

        // Save to temp file
        $writer = new Xlsx($spreadsheet);
        $path = tempnam(sys_get_temp_dir(), 'marksheet_') . '.xlsx';
        $writer->save($path);

        return $path;
    }

    private function checkDigit(string $studentNumber): string
    {
        return '(' . substr($studentNumber, -3) . ')';
    }
}
```

### Filament Integration
- Add an action button on the CourseOffering view page: "Export Mark Sheet"
- This triggers the export and provides a download link
- Optional: "Export Mark Sheet (PDF)" using DomPDF for a print-ready version

---

## Change 7: Pass/Fail Formula (FR-084 Amendment)

### Problem
FR-084 mentions "pass rate" without defining the formula. The denominator handling for NE students is unspecified.

### Specification

**Pass grades:** A+, A, B+, B, C+, C
**Fail grades:** D+, D
**Excluded from denominator:** NE, DE, AB, WH, INC (any special status)

**Formulas:**
```
examined_count = total_enrolled - NE_count - DE_count - AB_count - WH_count - INC_count
pass_count = count of students with grade in {A+, A, B+, B, C+, C}
fail_count = count of students with grade in {D+, D}
pass_rate = (pass_count / examined_count) * 100
fail_rate = (fail_count / examined_count) * 100
```

**Validation:** `pass_count + fail_count` should equal `examined_count`. If not, flag discrepancy.

### Implementation

```php
class CourseStatistics
{
    public function compute(CourseOffering $offering): array
    {
        $enrollments = $offering->enrollments()->where('status', 'active')->get();

        $passGrades = ['A+', 'A', 'B+', 'B', 'C+', 'C'];
        $failGrades = ['D+', 'D'];
        $specialStatuses = ['not_examined', 'deferred', 'absent', 'withheld', 'incomplete'];

        $total = $enrollments->count();
        $special = $enrollments->filter(fn ($e) => in_array($e->exam_status, $specialStatuses))->count();
        $examined = $total - $special;

        $passed = $enrollments->filter(fn ($e) => in_array($e->final_grade, $passGrades))->count();
        $failed = $enrollments->filter(fn ($e) => in_array($e->final_grade, $failGrades))->count();

        return [
            'total_enrolled'  => $total,
            'total_examined'  => $examined,
            'passed'          => $passed,
            'failed'          => $failed,
            'pass_rate'       => $examined > 0 ? round(($passed / $examined) * 100, 1) : null,
            'fail_rate'       => $examined > 0 ? round(($failed / $examined) * 100, 1) : null,
            'ne_count'        => $enrollments->where('exam_status', 'not_examined')->count(),
            'de_count'        => $enrollments->where('exam_status', 'deferred')->count(),
            'ab_count'        => $enrollments->where('exam_status', 'absent')->count(),
        ];
    }
}
```

---

## Change 8: Grading Scheme Boundary Correction (Appendix 10.1)

### Problem
The SRS Appendix 10.1 shows A+ starting at 80%, but all four sample workbooks use A+ starting at 90%. This is the CS Department grading scheme.

### Corrected Boundaries

The default "UNZA CS Department" grading scheme:

| Grade | Min % | Max % | Grade Points | Description |
|-------|-------|-------|-------------|-------------|
| A+ | 90 | 100 | 4.0 | Distinction |
| A | 80 | 89 | 4.0 | Distinction |
| B+ | 70 | 79 | 3.5 | Meritorious |
| B | 60 | 69 | 3.0 | Credit |
| C+ | 50 | 59 | 2.5 | Credit |
| C | 40 | 49 | 2.0 | Pass |
| D+ | 35 | 39 | 1.5 | Marginal Fail |
| D | 0 | 34 | 1.0 | Fail |

**Note:** The previous Appendix 10.1 boundaries (A+ = 80–100) may represent the official UNZA Regulations scheme used for transcripts. If both schemes are in use at the institution, the system should be seeded with both:
1. "UNZA CS Department" (above boundaries) — set as default
2. "UNZA Official Regulations" (original A+ = 80 boundaries) — available for selection

The lecturer selects the applicable scheme per course offering.

### Migration Seeder

```php
// Seeder: update default grading scheme
GradingScheme::where('name', 'UNZA Standard')->update(['name' => 'UNZA CS Department']);

// Update boundaries for CS Department scheme
$scheme = GradingScheme::where('name', 'UNZA CS Department')->first();
$scheme->boundaries()->delete();
$scheme->boundaries()->createMany([
    ['letter' => 'A+', 'min_percentage' => 90, 'max_percentage' => 100, 'grade_point' => 4.0, 'description' => 'Distinction'],
    ['letter' => 'A',  'min_percentage' => 80, 'max_percentage' => 89,  'grade_point' => 4.0, 'description' => 'Distinction'],
    ['letter' => 'B+', 'min_percentage' => 70, 'max_percentage' => 79,  'grade_point' => 3.5, 'description' => 'Meritorious'],
    ['letter' => 'B',  'min_percentage' => 60, 'max_percentage' => 69,  'grade_point' => 3.0, 'description' => 'Credit'],
    ['letter' => 'C+', 'min_percentage' => 50, 'max_percentage' => 59,  'grade_point' => 2.5, 'description' => 'Credit'],
    ['letter' => 'C',  'min_percentage' => 40, 'max_percentage' => 49,  'grade_point' => 2.0, 'description' => 'Pass'],
    ['letter' => 'D+', 'min_percentage' => 35, 'max_percentage' => 39,  'grade_point' => 1.5, 'description' => 'Marginal Fail'],
    ['letter' => 'D',  'min_percentage' => 0,  'max_percentage' => 34,  'grade_point' => 1.0, 'description' => 'Fail'],
]);
```

---

## Change 9: Expanded Import Validation (FR-060 Step 5)

### Problem
FR-060 lists 4 validation types. Real workbooks need 14 checks for reliable import.

### Full Validation Suite

Each check has a severity level: **Error** (blocks import), **Warning** (allows import with confirmation), or **Info** (informational only).

| # | Check | Severity | Description |
|---|-------|----------|-------------|
| V-01 | Required columns present | Error | Student identifier + at least one score column mapped |
| V-02 | Student number format | Error | Must be exactly 10 digits |
| V-03 | Duplicate student numbers | Error | No duplicate student IDs within the file |
| V-04 | Score range validation | Error | Each score must be 0 ≤ score ≤ max_raw_score for the assessment |
| V-05 | Non-numeric score values | Error | Score cells must contain numbers (not text, not formula errors) |
| V-06 | Weight sum validation | Warning | Sum of configured weights should ≈ CA weight (±0.01 tolerance) |
| V-07 | Grade value validation | Warning | If a grade column is mapped, values must be valid grades from the grading scheme |
| V-08 | NE detection | Warning | Blank exam cells → prompt "Confirm these students as Not Examined?" with list of affected student IDs |
| V-09 | CA consistency check | Warning | If CA(40) column exists: `|computed_CA - file_CA| > 0.5` → flag for the affected rows |
| V-10 | Course total consistency | Warning | If Course Total column exists: `|CA + Exam - file_total| > 0.5` → flag |
| V-11 | Embedded report row detection | Warning | Rows where student number cell is non-numeric or contains labels like "Average", "Total", "Summary" |
| V-12 | Non-data row filtering | Warning | Rows with all-blank score columns or with artifact content (e.g., jQuery code) |
| V-13 | Unmatched students | Info | Student IDs in file that don't match any enrollment in the course offering |
| V-14 | Missing score cells | Info | Enrolled students with blank score cells (not the same as NE — these are just missing data) |

### UI Presentation

```
╔═══════════════════════════════════════════════════════════╗
║  Import Validation Results                                ║
╠═══════════════════════════════════════════════════════════╣
║  ✓ 142 students matched                                  ║
║  ✗ 2 errors (must fix before import)                     ║
║  ⚠ 5 warnings (review recommended)                       ║
║  ℹ 3 informational items                                 ║
╠═══════════════════════════════════════════════════════════╣
║                                                           ║
║  ERRORS                                                   ║
║  Row 15: Student ID "20201234" — must be 10 digits       ║
║  Row 89: Quiz 3 score 150 exceeds max (100)              ║
║                                                           ║
║  WARNINGS                                                 ║
║  Weight sum: 0.38 (expected 0.40) — 0.02 short           ║
║  3 students have blank exam cells:                        ║
║    2020345678, 2020456789, 2020567890                     ║
║    [Mark as NE] [Leave blank]                             ║
║                                                           ║
║  Row 304: Non-data content detected (skipped)            ║
║                                                           ║
╚═══════════════════════════════════════════════════════════╝
```

### Implementation

```php
class ImportValidator
{
    private array $errors = [];
    private array $warnings = [];
    private array $info = [];

    public function validate(array $rows, array $columnMapping, CourseOffering $offering): ValidationResult
    {
        foreach ($rows as $index => $row) {
            $this->validateStudentNumber($row, $index, $columnMapping);
            $this->validateScoreRanges($row, $index, $columnMapping);
            $this->detectNonDataRows($row, $index);
        }

        $this->validateNoDuplicateStudents($rows, $columnMapping);
        $this->validateWeightSum($offering);
        $this->detectNEStudents($rows, $columnMapping);
        $this->checkCAConsistency($rows, $columnMapping, $offering);
        $this->checkCourseTotalConsistency($rows, $columnMapping);
        $this->findUnmatchedStudents($rows, $columnMapping, $offering);

        return new ValidationResult(
            errors: $this->errors,
            warnings: $this->warnings,
            info: $this->info,
            canProceed: empty($this->errors),
        );
    }
}
```

---

## Change 10: Column Name Synonyms (FR-031)

### Problem
Workbooks use varying column headers (Computer Number, Surname, Sex) that don't match the SRS field names.

### Synonym Table

| SRS Field | Accepted Synonyms |
|-----------|-------------------|
| `student_id_number` | Student Number, Computer Number, Comp Number, Comp No, Student No, Student ID, Stud No, ID Number |
| `first_name` | First Name, Other Names, Given Name, Forename, Other Name |
| `last_name` | Last Name, Surname, Family Name |
| `gender` | Sex, Gender, M/F |
| `email` | Email, Email Address, E-mail, University Email |
| `program` | Programme, Program, Degree, Course of Study |

### Implementation

```php
class ColumnSynonymResolver
{
    private const SYNONYMS = [
        'student_id_number' => ['student number', 'computer number', 'comp number', 'comp no', 'student no', 'student id', 'stud no', 'id number'],
        'first_name'        => ['first name', 'other names', 'given name', 'forename', 'other name'],
        'last_name'         => ['last name', 'surname', 'family name'],
        'gender'            => ['sex', 'gender', 'm/f'],
        'email'             => ['email', 'email address', 'e-mail', 'university email'],
        'program'           => ['programme', 'program', 'degree', 'course of study'],
    ];

    public function resolve(string $header): ?string
    {
        $normalized = strtolower(trim($header));

        foreach (self::SYNONYMS as $field => $synonyms) {
            if (in_array($normalized, $synonyms, true)) {
                return $field;
            }
        }

        return null; // Unknown column — require manual mapping
    }

    public function autoMapHeaders(array $headers): array
    {
        $mapping = [];
        foreach ($headers as $index => $header) {
            $resolved = $this->resolve($header);
            if ($resolved) {
                $mapping[$index] = $resolved;
            }
        }
        return $mapping;
    }
}
```

This resolver is used in the column mapping step (FR-060 Step 3) to pre-fill the mapping for known columns, reducing manual work.

---

## Revised FR-060: Complete 6-Step Wizard Flow

For reference, here is the complete wizard flow incorporating Changes 2, 3, and 9:

| Step | Name | Description |
|------|------|-------------|
| 1 | File Upload | Accept .xlsx or .csv. Validate file type and size. |
| 2 | Sheet Selection | For multi-sheet XLSX only. Auto-select data sheet with heuristic. Show 5-row preview. Skip for CSV/single-sheet. |
| 3 | Column Mapping | Column-by-column matching (default) or batch mode. Auto-map known columns via synonym resolver. Identify assessment columns, identity columns, and skip columns. |
| 4 | Weight Configuration | Extract weights from CA formula. Pre-fill weight editor. Confirm or override. Configure normalisation per assessment. Create assessment structures on confirmation. Fall back to manual if formula parsing fails. |
| 5 | Data Preview & Validation | Full preview with all mapped columns. Run 14-check validation suite. Show errors/warnings/info. Allow validation report download. NE confirmation prompt. |
| 6 | Conflict Resolution & Import | If existing data: overwrite / merge / append options. Show diff of changed values. Execute with progress indicator. Generate audit log. Show completion summary. |

---

## Implementation Priority

Recommended order of implementation:

1. **Database migrations first:** Changes 1, 4, 5 (aggregation mode, study mode, comment) — schema changes that other features depend on
2. **Grading scheme correction:** Change 8 — seeder update, quick win
3. **Pass/fail formula:** Change 7 — computation logic, needed for reports
4. **Column synonyms:** Change 10 — utility class, used by the import wizard
5. **Import wizard steps:** Changes 2, 3, 9 — the big feature, build incrementally:
   - Sheet selection (Change 2)
   - Column mapping with column-by-column mode (Change 3, first half)
   - Weight configuration (Change 3, second half)
   - Expanded validation (Change 9)
6. **Mark sheet export:** Change 6 — report template, can be built independently

---

*— End of Implementation Guide —*
