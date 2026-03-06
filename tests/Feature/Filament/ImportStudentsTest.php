<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\ImportStudents;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ImportStudentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_page_renders(): void
    {
        $this->get(ImportStudents::getUrl())->assertSuccessful();
    }

    public function test_file_is_required_for_submit(): void
    {
        Livewire::test(ImportStudents::class)
            ->call('submit')
            ->assertHasErrors(['data.file']);
    }

    public function test_nested_upload_keys_can_be_written_without_error(): void
    {
        $uploadKey = (string) Str::uuid();

        Livewire::test(ImportStudents::class)
            ->set("data.file.{$uploadKey}", 'student-imports/tmp.xlsx')
            ->assertSet("data.file.{$uploadKey}", 'student-imports/tmp.xlsx');
    }
}
