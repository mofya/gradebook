<?php

namespace Database\Seeders;

use App\Models\Assessment;
use App\Models\AssessmentGroup;
use App\Models\AssessmentSubsection;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\GradingScheme;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Models\Year;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(GradingSchemeSeeder::class);

        $admin = User::factory()->admin()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        $lecturer1 = User::factory()->lecturer()->create([
            'name' => 'Dr. Mwansa Banda',
            'email' => 'mwansa@example.com',
        ]);

        $lecturer2 = User::factory()->lecturer()->create([
            'name' => 'Prof. Chiluba Nkandu',
            'email' => 'chiluba@example.com',
        ]);

        $year = Year::factory()->create([
            'name' => '2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_current' => true,
        ]);

        $sem1 = Semester::create([
            'year_id' => $year->id,
            'name' => 'Semester 1',
            'start_date' => '2026-01-15',
            'end_date' => '2026-06-15',
            'is_active' => true,
        ]);

        $sem2 = Semester::create([
            'year_id' => $year->id,
            'name' => 'Semester 2',
            'start_date' => '2026-07-15',
            'end_date' => '2026-12-15',
            'is_active' => false,
        ]);

        $scheme = GradingScheme::where('is_default', true)->first();

        $cs101 = Course::create([
            'name' => 'Introduction to Computer Science',
            'code' => 'CS-101',
            'year_id' => $year->id,
            'credits' => 3,
        ]);

        $cs201 = Course::create([
            'name' => 'Data Structures and Algorithms',
            'code' => 'CS-201',
            'year_id' => $year->id,
            'credits' => 4,
        ]);

        $math101 = Course::create([
            'name' => 'Calculus I',
            'code' => 'MTH-101',
            'year_id' => $year->id,
            'credits' => 3,
        ]);

        $offering1 = CourseOffering::create([
            'course_id' => $cs101->id,
            'semester_id' => $sem1->id,
            'grading_scheme_id' => $scheme?->id,
            'lecturer_id' => $lecturer1->id,
            'ca_weight' => 40,
            'exam_weight' => 60,
            'status' => 'active',
        ]);

        $offering2 = CourseOffering::create([
            'course_id' => $cs201->id,
            'semester_id' => $sem1->id,
            'grading_scheme_id' => $scheme?->id,
            'lecturer_id' => $lecturer2->id,
            'ca_weight' => 50,
            'exam_weight' => 50,
            'status' => 'active',
        ]);

        $offering3 = CourseOffering::create([
            'course_id' => $math101->id,
            'semester_id' => $sem1->id,
            'grading_scheme_id' => $scheme?->id,
            'lecturer_id' => $lecturer1->id,
            'ca_weight' => 30,
            'exam_weight' => 70,
            'status' => 'draft',
        ]);

        $this->seedAssessmentsAndGrades($offering1, $offering2);
        $this->seedStudents($offering1, $offering2, $offering3);
    }

    private function seedAssessmentsAndGrades(CourseOffering $offering1, CourseOffering $offering2): void
    {
        $caGroup1 = AssessmentGroup::create([
            'course_offering_id' => $offering1->id,
            'name' => 'Continuous Assessment',
            'type' => 'ca',
            'weight_percentage' => 100,
            'weight_mode' => 'percentage',
            'sort_order' => 0,
        ]);

        Assessment::create([
            'name' => 'Assignment 1',
            'assessment_group_id' => $caGroup1->id,
            'course_id' => $offering1->course_id,
            'max_raw_score' => 20,
            'weight' => 50,
            'sort_order' => 0,
        ]);

        Assessment::create([
            'name' => 'Test 1',
            'assessment_group_id' => $caGroup1->id,
            'course_id' => $offering1->course_id,
            'max_raw_score' => 30,
            'weight' => 50,
            'sort_order' => 1,
        ]);

        $examGroup1 = AssessmentGroup::create([
            'course_offering_id' => $offering1->id,
            'name' => 'Examination',
            'type' => 'exam',
            'weight_percentage' => 100,
            'weight_mode' => 'percentage',
            'sort_order' => 1,
        ]);

        $finalExam = Assessment::create([
            'name' => 'Final Exam',
            'assessment_group_id' => $examGroup1->id,
            'course_id' => $offering1->course_id,
            'max_raw_score' => 100,
            'has_subsections' => true,
            'weight' => 100,
            'sort_order' => 0,
        ]);

        AssessmentSubsection::create(['assessment_id' => $finalExam->id, 'name' => 'Section A', 'max_score' => 30, 'sort_order' => 0]);
        AssessmentSubsection::create(['assessment_id' => $finalExam->id, 'name' => 'Section B', 'max_score' => 40, 'sort_order' => 1]);
        AssessmentSubsection::create(['assessment_id' => $finalExam->id, 'name' => 'Section C', 'max_score' => 30, 'sort_order' => 2]);

        $caGroup2 = AssessmentGroup::create([
            'course_offering_id' => $offering2->id,
            'name' => 'Continuous Assessment',
            'type' => 'ca',
            'weight_percentage' => 100,
            'weight_mode' => 'percentage',
            'sort_order' => 0,
        ]);

        Assessment::create([
            'name' => 'Lab Report 1',
            'assessment_group_id' => $caGroup2->id,
            'course_id' => $offering2->course_id,
            'max_raw_score' => 25,
            'weight' => 30,
            'sort_order' => 0,
        ]);

        Assessment::create([
            'name' => 'Midterm',
            'assessment_group_id' => $caGroup2->id,
            'course_id' => $offering2->course_id,
            'max_raw_score' => 50,
            'weight' => 70,
            'sort_order' => 1,
        ]);

        $examGroup2 = AssessmentGroup::create([
            'course_offering_id' => $offering2->id,
            'name' => 'Examination',
            'type' => 'exam',
            'weight_percentage' => 100,
            'weight_mode' => 'percentage',
            'sort_order' => 1,
        ]);

        Assessment::create([
            'name' => 'Final Exam',
            'assessment_group_id' => $examGroup2->id,
            'course_id' => $offering2->course_id,
            'max_raw_score' => 100,
            'weight' => 100,
            'sort_order' => 0,
        ]);
    }

    private function seedStudents(CourseOffering $offering1, CourseOffering $offering2, CourseOffering $offering3): void
    {
        $students = Student::factory()->count(15)->create();

        $studentUser = User::factory()->student()->create([
            'name' => 'Student Demo',
            'email' => $students->first()->email,
        ]);

        $marks = [92, 85, 78, 72, 67, 63, 58, 55, 48, 40, 88, 75, 61, 53, 45];

        foreach ($students as $i => $student) {
            $enrollment1 = Enrollment::create([
                'student_id' => $student->id,
                'course_offering_id' => $offering1->id,
                'source' => 'manual',
                'status' => 'enrolled',
                'final_total' => $marks[$i],
            ]);

            if ($i < 10) {
                Enrollment::create([
                    'student_id' => $student->id,
                    'course_offering_id' => $offering2->id,
                    'source' => 'manual',
                    'status' => 'enrolled',
                ]);
            }

            if ($i < 5) {
                Enrollment::create([
                    'student_id' => $student->id,
                    'course_offering_id' => $offering3->id,
                    'source' => 'manual',
                    'status' => 'enrolled',
                ]);
            }
        }
    }
}
