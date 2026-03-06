<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CourseController extends Controller
{
    /**
     * List all courses.
     */
    public function index(): AnonymousResourceCollection
    {
        $courses = Course::query()
            ->with(['department', 'year'])
            ->paginate(20);

        return CourseResource::collection($courses);
    }

    /**
     * Show a single course.
     */
    public function show(Course $course): CourseResource
    {
        $course->load(['department', 'year', 'assessments']);

        return new CourseResource($course);
    }
}
