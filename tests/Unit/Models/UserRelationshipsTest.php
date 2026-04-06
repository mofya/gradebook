<?php

namespace Tests\Unit\Models;

use App\Models\CourseOffering;
use App\Models\GradeAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_course_offerings_relationship_returns_has_many(): void
    {
        $user = User::factory()->lecturer()->create();

        $this->assertInstanceOf(HasMany::class, $user->courseOfferings());
    }

    public function test_course_offerings_returns_assigned_offerings(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $offering = CourseOffering::factory()->create(['lecturer_id' => $lecturer->id]);
        CourseOffering::factory()->create(); // unrelated

        $this->assertCount(1, $lecturer->courseOfferings);
        $this->assertTrue($lecturer->courseOfferings->first()->is($offering));
    }

    public function test_graded_results_relationship_returns_has_many(): void
    {
        $user = User::factory()->lecturer()->create();

        $this->assertInstanceOf(HasMany::class, $user->gradedResults());
    }

    public function test_audit_logs_relationship_returns_has_many(): void
    {
        $user = User::factory()->admin()->create();

        $this->assertInstanceOf(HasMany::class, $user->auditLogs());
    }

    public function test_audit_logs_returns_user_logs(): void
    {
        $user = User::factory()->admin()->create();
        GradeAuditLog::factory()->create(['user_id' => $user->id]);

        $this->assertCount(1, $user->auditLogs);
    }
}
