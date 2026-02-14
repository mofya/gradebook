<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\AuditLog;
use App\Models\GradeAuditLog;
use App\Models\GradeResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuditLogPageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->actingAs($this->admin);
    }

    public function test_audit_log_page_renders(): void
    {
        Livewire::test(AuditLog::class)
            ->assertSuccessful();
    }

    public function test_audit_log_shows_entries(): void
    {
        GradeAuditLog::create([
            'auditable_type' => GradeResult::class,
            'auditable_id' => 1,
            'user_id' => $this->admin->id,
            'action' => 'updated',
            'old_values' => ['raw_score' => 50],
            'new_values' => ['raw_score' => 75],
            'reason' => 'Correction',
            'ip_address' => '127.0.0.1',
        ]);

        Livewire::test(AuditLog::class)
            ->assertSee('Updated')
            ->assertSee($this->admin->name)
            ->assertSee('Correction');
    }

    public function test_audit_log_shows_empty_state(): void
    {
        Livewire::test(AuditLog::class)
            ->assertSee('No audit log entries found.');
    }
}
