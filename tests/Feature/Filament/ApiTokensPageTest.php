<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\ApiTokens;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ApiTokensPageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->actingAs($this->admin);
    }

    public function test_page_renders(): void
    {
        Livewire::test(ApiTokens::class)
            ->assertSuccessful();
    }

    public function test_shows_empty_state(): void
    {
        Livewire::test(ApiTokens::class)
            ->assertSee('No API tokens yet.');
    }

    public function test_create_token(): void
    {
        Livewire::test(ApiTokens::class)
            ->set('tokenName', 'test-token')
            ->call('createToken')
            ->assertSet('tokenName', '')
            ->assertNotSet('plainTextToken', null);

        $this->assertDatabaseHas('personal_access_tokens', [
            'name' => 'test-token',
            'tokenable_id' => $this->admin->id,
        ]);
    }

    public function test_create_token_requires_name(): void
    {
        Livewire::test(ApiTokens::class)
            ->set('tokenName', '')
            ->call('createToken')
            ->assertSet('plainTextToken', null);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_revoke_token(): void
    {
        $token = $this->admin->createToken('disposable');

        Livewire::test(ApiTokens::class)
            ->assertSee('disposable')
            ->call('revokeToken', $token->accessToken->id)
            ->assertDontSee('disposable');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_dismiss_token_clears_plain_text(): void
    {
        Livewire::test(ApiTokens::class)
            ->set('tokenName', 'my-token')
            ->call('createToken')
            ->assertNotSet('plainTextToken', null)
            ->call('dismissToken')
            ->assertSet('plainTextToken', null);
    }

    public function test_student_cannot_access(): void
    {
        $student = User::factory()->student()->create();
        $this->actingAs($student);

        Livewire::test(ApiTokens::class)
            ->assertForbidden();
    }
}
