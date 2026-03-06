<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Department;
use App\Models\GradingScheme;
use App\Models\Semester;
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

        // Departments
        $csDept = Department::create(['dept_name' => 'Computer Science', 'dept_code' => 'CSC']);
        $ggyDept = Department::create(['dept_name' => 'Geography', 'dept_code' => 'GGY']);
        $metDept = Department::create(['dept_name' => 'Meteorology', 'dept_code' => 'MET']);

        $lecturer = User::factory()->lecturer()->create([
            'name' => 'Admin User',
            'email' => 'lecturer@example.com',
            'department_id' => $csDept->id,
        ]);

        // Year 2026
        $year = Year::factory()->create([
            'name' => '2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_current' => true,
        ]);

        // Semester 1
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

        // Courses
        $csc3301 = Course::create([
            'name' => 'Programming Language Paradigms',
            'code' => 'CSC3301',
            'year_id' => $year->id,
            'credits' => 3,
            'dept_id' => $csDept->id,
        ]);

        $csc4035 = Course::create([
            'name' => 'Web Technologies and Programming',
            'code' => 'CSC4035',
            'year_id' => $year->id,
            'credits' => 3,
            'dept_id' => $csDept->id,
        ]);

        $ggy3061 = Course::create([
            'name' => 'Computer Techniques',
            'code' => 'GGY3061',
            'year_id' => $year->id,
            'credits' => 3,
            'dept_id' => $ggyDept->id,
        ]);

        $met3429 = Course::create([
            'name' => 'Computer Techniques',
            'code' => 'MET3429',
            'year_id' => $year->id,
            'credits' => 3,
            'dept_id' => $metDept->id,
        ]);

        // Course Offerings — Semester 1, 2026
        foreach ([$csc3301, $csc4035, $ggy3061, $met3429] as $course) {
            CourseOffering::create([
                'course_id' => $course->id,
                'semester_id' => $sem1->id,
                'grading_scheme_id' => $scheme?->id,
                'lecturer_id' => $lecturer->id,
                'ca_weight' => 40,
                'exam_weight' => 60,
                'status' => 'active',
            ]);
        }
    }
}
