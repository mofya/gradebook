<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Filament\Pages\ApiTokens;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ApiTokensPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_token_with_selected_validity(): void
    {
        $user = User::factory()->create(['role' => Role::Admin]);
        $this->actingAs($user);

        Livewire::test(ApiTokens::class)
            ->set('tokenName', 'pipeline')
            ->set('tokenValidityMinutes', 10080)
            ->call('createToken')
            ->assertSuccessful();

        $token = $user->tokens()->where('name', 'pipeline')->firstOrFail();

        $this->assertNotNull($token->expires_at);
        // 7 days ± 1 minute leeway
        $expected = now()->addMinutes(10080);
        $this->assertLessThan(60, abs($token->expires_at->diffInSeconds($expected)));
    }

    public function test_defaults_to_24_hours_when_invalid_value_submitted(): void
    {
        $user = User::factory()->create(['role' => Role::Admin]);
        $this->actingAs($user);

        Livewire::test(ApiTokens::class)
            ->set('tokenName', 'default-fallback')
            ->set('tokenValidityMinutes', 999999)
            ->call('createToken')
            ->assertSuccessful();

        $token = $user->tokens()->where('name', 'default-fallback')->firstOrFail();
        $expected = now()->addMinutes(1440);
        $this->assertLessThan(60, abs($token->expires_at->diffInSeconds($expected)));
    }

    public function test_empty_name_does_not_create_token(): void
    {
        $user = User::factory()->create(['role' => Role::Admin]);
        $this->actingAs($user);

        Livewire::test(ApiTokens::class)
            ->set('tokenName', '')
            ->call('createToken')
            ->assertSuccessful();

        $this->assertEquals(0, $user->tokens()->count());
    }
}
