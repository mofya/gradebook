<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\YearResource\Pages\CreateYear;
use App\Filament\Resources\YearResource\Pages\EditYear;
use App\Filament\Resources\YearResource\Pages\ListYears;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class YearResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_list_page_renders(): void
    {
        $this->get(ListYears::getUrl())->assertSuccessful();
    }

    public function test_can_list_years(): void
    {
        $years = Year::factory()->count(3)->create();

        Livewire::test(ListYears::class)
            ->assertCanSeeTableRecords($years);
    }

    public function test_create_page_renders(): void
    {
        $this->get(CreateYear::getUrl())->assertSuccessful();
    }

    public function test_can_create_year(): void
    {
        Livewire::test(CreateYear::class)
            ->fillForm([
                'name' => '2024-2025',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('years', ['name' => '2024-2025']);
    }

    public function test_name_is_required(): void
    {
        Livewire::test(CreateYear::class)
            ->fillForm([
                'name' => '',
            ])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required']);
    }

    public function test_name_must_be_unique(): void
    {
        Year::factory()->create(['name' => '2024-2025']);

        Livewire::test(CreateYear::class)
            ->fillForm([
                'name' => '2024-2025',
            ])
            ->call('create')
            ->assertHasFormErrors(['name' => 'unique']);
    }

    public function test_edit_page_renders(): void
    {
        $year = Year::factory()->create();

        $this->get(EditYear::getUrl(['record' => $year]))->assertSuccessful();
    }

    public function test_can_edit_year(): void
    {
        $year = Year::factory()->create();

        Livewire::test(EditYear::class, ['record' => $year->getRouteKey()])
            ->fillForm([
                'name' => 'Updated Year',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('years', ['id' => $year->id, 'name' => 'Updated Year']);
    }

    public function test_can_delete_year(): void
    {
        $year = Year::factory()->create();

        Livewire::test(EditYear::class, ['record' => $year->getRouteKey()])
            ->callAction('delete');

        $this->assertDatabaseMissing('years', ['id' => $year->id]);
    }
}
