# SRS Traceability Matrix

Maps each requirement from `UNZA_GradeBook_SRS_v1.1.docx` to its implementing file(s) and test(s).

## Authentication

| Requirement | Implementation | Tests |
|---|---|---|
| OTP-based student login | `app/Services/OtpAuthService.php` | `tests/Feature/Services/OtpAuthServiceTest.php` |
| OTP login UI flow | `app/Filament/Student/Pages/Auth/OtpLogin.php` | `tests/Feature/Filament/Student/OtpLoginTest.php` |
| OTP notification (queued) | `app/Notifications/OtpLoginNotification.php` | `OtpAuthServiceTest::test_otp_notification_is_queued` |
| Per-identifier rate limiting | `OtpAuthService::throttleCheck()` | `OtpAuthServiceTest::test_throttle_check_*` |
| Resend cooldown (60s) | `OtpAuthService::canResend()` | `OtpAuthServiceTest::test_can_resend_enforces_sixty_second_cooldown` |
| OTP expiry & max attempts | `OtpAuthService::verifyOtp()` | `OtpAuthServiceTest::test_verify_otp_fails_when_expired`, `test_verify_otp_fails_after_max_attempts` |
| Student user auto-creation | `OtpAuthService::ensureUserExists()` | `OtpAuthServiceTest::test_ensure_user_exists_*` |

## Grade Calculation

| Requirement | Implementation | Tests |
|---|---|---|
| UNZA grading scale (A+ to D) | `app/Services/GradingService.php` (GRADING_SCALE) | `tests/Feature/Services/GradingServiceTest.php` |
| Letter grade from mark | `GradingService::getLetterGrade()` | `GradingServiceTest::test_letter_grade_*` |
| Grade points from mark | `GradingService::getGradePoints()` | `GradingServiceTest::test_grade_points_*` |
| CA total computation | `GradingService::computeCaTotal()` | `GradingServiceTest::test_compute_ca_total_*` |
| Final mark computation | `GradingService::computeFinalMark()` | `GradingServiceTest::test_compute_final_mark_*` |
| Grade resolution (NE, SP, DV, EX) | `GradingService::resolveGrade()` | `GradingServiceTest::test_resolve_grade_*` |
| Exam status enum | `app/Enums/ExamStatus.php` | — |

## Excel Export

| Requirement | Implementation | Tests |
|---|---|---|
| Multi-sheet export | `app/Exports/GradeSheetExport.php` | `tests/Feature/Exports/GradeSheetExportTest.php` |
| Mark Sheet (student marks) | `app/Exports/Sheets/MarkSheet.php` | `GradeSheetExportTest::test_mark_sheet_*` |
| UNZA header order (Category, Exam Grade, Def, Sup) | `MarkSheet::headings()` | `GradeSheetExportTest::test_mark_sheet_header_order_matches_unza` |
| SURNAME, Firstname format | `MarkSheet::buildRows()` | `GradeSheetExportTest::test_mark_sheet_name_is_surname_comma_firstname` |
| Category column (FT/PT/Distance) | `MarkSheet::buildRows()` | `GradeSheetExportTest::test_mark_sheet_category_column` |
| Exam Grade column | `MarkSheet::buildRows()` | `GradeSheetExportTest::test_mark_sheet_exam_grade_column` |
| Deferred / Supplementary columns | `MarkSheet::buildRows()` | `GradeSheetExportTest::test_mark_sheet_deferred_and_supplementary_columns` |
| Check digit | `MarkSheet::checkDigit()` | `GradeSheetExportTest::test_mark_sheet_check_digit` |
| Grade Summary sheet | `app/Exports/Sheets/GradeSummarySheet.php` | `GradeSheetExportTest::test_grade_summary_*` |
| Pass rate excludes NE | `GradeSummarySheet` | `GradeSheetExportTest::test_grade_summary_pass_rate_excludes_ne` |
| Charts sheet | `app/Exports/Sheets/ChartsSheet.php` | `GradeSheetExportTest::test_charts_sheet_has_two_charts` |

## Data Import

| Requirement | Implementation | Tests |
|---|---|---|
| Excel/CSV import | `app/Services/CourseDataImportService.php` | `tests/Feature/Services/CourseDataImportServiceTest.php` |
| Auto-detect column roles | `CourseDataImportService::parseHeaders()` | `CourseDataImportServiceTest::test_parses_standard_unza_headers` |
| Assessment header parsing | `CourseDataImportService::parseAssessmentHeader()` | `CourseDataImportServiceTest::test_detects_assessment_columns_with_max_scores` |
| Exam denominator parsing | `CourseDataImportService::parseExamDenominator()` | `CourseDataImportServiceTest::test_detects_exam_column_with_denominator` |
| Column mapping validation | `CourseDataImportService::validateColumnMappings()` | `CourseDataImportServiceTest::test_validate_column_mappings_requires_student_id` |
| Non-numeric score handling | `CourseDataImportService::import()` | `CourseDataImportServiceTest::test_non_numeric_score_adds_error_and_skips` |
| Short student ID warning | `CourseDataImportService::import()` | `CourseDataImportServiceTest::test_short_student_id_adds_warning` |
| Import wizard UI | `app/Filament/Pages/ImportCourseData.php` | — |
| Pre-import validation | `ImportCourseData::parseFile()` | — |

## Gender Analysis

| Requirement | Implementation | Tests |
|---|---|---|
| Gender × Grade cross-tab | `app/Exports/Sheets/GenderAnalysisSheet.php` | `GradeSheetExportTest::test_gender_analysis_cross_tab_correct` |
| NE included in analysis | `GenderAnalysisSheet` | `GradeSheetExportTest::test_gender_analysis_includes_ne` |

## Infrastructure

| Requirement | Implementation | Tests |
|---|---|---|
| Mail configuration verification | `app/Console/Commands/VerifyMailConfig.php` | — |
| Production mail docs | `.env.example` | — |
