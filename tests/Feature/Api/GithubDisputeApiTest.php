<?php

namespace Tests\Feature\Api;

use App\Models\Student;
use App\Models\User;
use App\Models\UsernameDispute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GithubDisputeApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_resolve_github_reassigns_username(): void
    {
        $holder = Student::factory()->create(['github_username' => 'disputed-user']);
        $claimant = Student::factory()->create(['student_id_number' => 'SNDISPUTE001']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/students/resolve-github', [
                'github_username' => 'disputed-user',
                'correct_student_id' => 'SNDISPUTE001',
                'resolution_notes' => 'Verified via email.',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.action', 'reassigned')
            ->assertJsonPath('data.previous_holder', $holder->student_id_number);

        $this->assertNull($holder->fresh()->github_username);
        $this->assertEquals('disputed-user', $claimant->fresh()->github_username);
    }

    public function test_resolve_github_assigns_unclaimed_username(): void
    {
        $student = Student::factory()->create(['student_id_number' => 'SNDISPUTE002']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/students/resolve-github', [
                'github_username' => 'brand-new-user',
                'correct_student_id' => 'SNDISPUTE002',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.action', 'assigned')
            ->assertJsonPath('data.previous_holder', null);

        $this->assertEquals('brand-new-user', $student->fresh()->github_username);
    }

    public function test_resolve_github_no_change_if_already_correct(): void
    {
        Student::factory()->create([
            'student_id_number' => 'SNDISPUTE003',
            'github_username' => 'already-mine',
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/students/resolve-github', [
                'github_username' => 'already-mine',
                'correct_student_id' => 'SNDISPUTE003',
            ])
            ->assertOk()
            ->assertJsonPath('data.action', 'no_change');
    }

    public function test_resolve_github_resolves_pending_disputes(): void
    {
        $holder = Student::factory()->create(['github_username' => 'contested']);
        $claimant = Student::factory()->create(['student_id_number' => 'SNDISPUTE004']);

        UsernameDispute::factory()->create([
            'claimant_student_id' => $claimant->id,
            'current_holder_student_id' => $holder->id,
            'github_username' => 'contested',
            'status' => 'pending',
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/students/resolve-github', [
                'github_username' => 'contested',
                'correct_student_id' => 'SNDISPUTE004',
            ]);

        $this->assertDatabaseHas('username_disputes', [
            'github_username' => 'contested',
            'status' => 'resolved',
        ]);
    }

    public function test_resolve_github_creates_audit_logs(): void
    {
        Student::factory()->create(['github_username' => 'auditme']);
        Student::factory()->create(['student_id_number' => 'SNDISPUTE005']);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/students/resolve-github', [
                'github_username' => 'auditme',
                'correct_student_id' => 'SNDISPUTE005',
            ]);

        $this->assertDatabaseHas('grade_audit_logs', ['action' => 'github_removed']);
        $this->assertDatabaseHas('grade_audit_logs', ['action' => 'github_reassigned']);
    }

    public function test_resolve_github_404_for_unknown_student(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/students/resolve-github', [
                'github_username' => 'someone',
                'correct_student_id' => 'NONEXISTENT',
            ])
            ->assertNotFound();
    }

    public function test_list_pending_disputes(): void
    {
        UsernameDispute::factory()->create(['status' => 'pending']);
        UsernameDispute::factory()->create(['status' => 'resolved']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/disputes');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_student_cannot_access_resolve_endpoint(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student, 'sanctum')
            ->postJson('/api/v1/students/resolve-github', [
                'github_username' => 'someone',
                'correct_student_id' => 'SN001',
            ])
            ->assertForbidden();
    }
}
