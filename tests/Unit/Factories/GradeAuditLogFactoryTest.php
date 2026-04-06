<?php

namespace Tests\Unit\Factories;

use App\Models\GradeAuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradeAuditLogFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_valid_audit_log(): void
    {
        $log = GradeAuditLog::factory()->create();

        $this->assertDatabaseHas('grade_audit_logs', ['id' => $log->id]);
        $this->assertNotNull($log->auditable_type);
        $this->assertNotNull($log->user_id);
        $this->assertContains($log->action, ['created', 'updated', 'deleted']);
    }
}
