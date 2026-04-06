<?php

namespace Tests\Unit\Models;

use App\Models\CourseOffering;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseOfferingVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_verification_token_sets_token_and_expiry(): void
    {
        $offering = CourseOffering::factory()->create();
        $offering->generateVerificationToken(3);

        $this->assertNotNull($offering->verification_token);
        $this->assertEquals(64, strlen($offering->verification_token));
        $this->assertNotNull($offering->verification_expires_at);
        $this->assertTrue($offering->verification_expires_at->isFuture());
    }

    public function test_revoke_verification_token_clears_fields(): void
    {
        $offering = CourseOffering::factory()->withVerificationToken()->create();
        $this->assertNotNull($offering->verification_token);

        $offering->revokeVerificationToken();

        $this->assertNull($offering->fresh()->verification_token);
        $this->assertNull($offering->fresh()->verification_expires_at);
    }

    public function test_has_valid_verification_token_returns_true_when_valid(): void
    {
        $offering = CourseOffering::factory()->withVerificationToken(3)->create();

        $this->assertTrue($offering->hasValidVerificationToken());
    }

    public function test_has_valid_verification_token_returns_false_when_expired(): void
    {
        $offering = CourseOffering::factory()->withVerificationToken(-1)->create();

        $this->assertFalse($offering->hasValidVerificationToken());
    }

    public function test_has_valid_verification_token_returns_false_when_no_token(): void
    {
        $offering = CourseOffering::factory()->create();

        $this->assertFalse($offering->hasValidVerificationToken());
    }

    public function test_generate_replaces_existing_token(): void
    {
        $offering = CourseOffering::factory()->withVerificationToken()->create();
        $oldToken = $offering->verification_token;

        $offering->generateVerificationToken(5);

        $this->assertNotEquals($oldToken, $offering->verification_token);
    }
}
