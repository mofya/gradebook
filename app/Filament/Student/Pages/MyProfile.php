<?php

namespace App\Filament\Student\Pages;

use App\Models\GradeAuditLog;
use App\Models\Student;
use App\Models\User;
use App\Services\BackfillLabGradesService;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * @property-read Schema $profileForm
 * @property-read Schema $passwordForm
 * @property-read Schema $githubForm
 * @property-read Schema $genderForm
 */
class MyProfile extends Page
{
    protected string $view = 'filament.student.pages.my-profile';

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $title = 'My Profile';

    protected static ?int $navigationSort = 10;

    protected ?Student $cachedStudent = null;

    /**
     * @var array<string, mixed>
     */
    public array $profileData = [];

    /**
     * @var array<string, mixed>
     */
    public array $passwordData = [];

    /**
     * @var array<string, mixed>
     */
    public array $githubData = [];

    /**
     * @var array<string, mixed>
     */
    public array $genderData = [];

    public function mount(): void
    {
        $student = $this->getStudent();

        if (! $student) {
            return;
        }

        $this->profileForm->fill([
            'personal_email' => $student->personal_email,
        ]);

        $this->passwordForm->fill();

        $this->githubForm->fill([
            'github_username' => $student->github_username,
        ]);

        $this->genderForm->fill([
            'gender' => $student->gender,
        ]);
    }

    public function defaultProfileForm(Schema $schema): Schema
    {
        return $schema->statePath('profileData');
    }

    public function profileForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('personal_email')
                    ->label('Personal Email')
                    ->helperText('This is the email you use to log in and receive notifications.')
                    ->email()
                    ->required(),
            ]);
    }

    public function defaultPasswordForm(Schema $schema): Schema
    {
        return $schema->statePath('passwordData');
    }

    public function passwordForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('current_password')
                    ->label('Current Password')
                    ->password()
                    ->revealable()
                    ->required(),
                TextInput::make('new_password')
                    ->label('New Password')
                    ->password()
                    ->revealable()
                    ->required()
                    ->minLength(8)
                    ->confirmed(),
                TextInput::make('new_password_confirmation')
                    ->label('Confirm New Password')
                    ->password()
                    ->revealable()
                    ->required(),
            ]);
    }

    public function defaultGithubForm(Schema $schema): Schema
    {
        return $schema->statePath('githubData');
    }

    public function githubForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('github_username')
                    ->label('GitHub Username')
                    ->prefix('github.com/')
                    ->maxLength(39),
            ]);
    }

    public function defaultGenderForm(Schema $schema): Schema
    {
        return $schema->statePath('genderData');
    }

    public function genderForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('gender')
                    ->label('Sex')
                    ->options([
                        'Male' => 'Male',
                        'Female' => 'Female',
                    ])
                    ->placeholder('Select...'),
            ]);
    }

    public function updateProfile(): void
    {
        if (! $this->checkRateLimit()) {
            return;
        }

        $data = $this->profileForm->getState();
        $data['personal_email'] = mb_strtolower($data['personal_email']);
        $student = $this->getStudent();

        if (! $student) {
            return;
        }

        // Check uniqueness (excluding current student)
        $emailTaken = Student::query()
            ->whereRaw('LOWER(personal_email) = ?', [$data['personal_email']])
            ->where('id', '!=', $student->id)
            ->exists();

        $userEmailTaken = User::query()
            ->whereRaw('LOWER(email) = ?', [$data['personal_email']])
            ->where('email', '!=', auth()->user()->email)
            ->exists();

        // Block personal_email that matches any student's institutional email
        $institutionalCollision = Student::query()
            ->whereRaw('LOWER(email) = ?', [$data['personal_email']])
            ->exists();

        if ($emailTaken || $userEmailTaken || $institutionalCollision) {
            throw ValidationException::withMessages([
                'profileData.personal_email' => 'This email cannot be used. Please choose a different email address.',
            ]);
        }

        $oldEmail = $student->personal_email;

        DB::transaction(function () use ($student, $data) {
            $student->update(['personal_email' => $data['personal_email']]);

            auth()->user()->update(['email' => $data['personal_email']]);
        });

        $this->logProfileChange($student, 'profile_email_updated', ['personal_email' => $oldEmail], ['personal_email' => $data['personal_email']]);

        $this->cachedStudent = null;

        Notification::make()
            ->title('Email updated successfully.')
            ->success()
            ->send();
    }

    public function updatePassword(): void
    {
        if (! $this->checkRateLimit()) {
            return;
        }

        $data = $this->passwordForm->getState();
        $student = $this->getStudent();

        if (! $student) {
            return;
        }

        if (! Hash::check($data['current_password'], $student->password)) {
            throw ValidationException::withMessages([
                'passwordData.current_password' => 'Current password is incorrect.',
            ]);
        }

        DB::transaction(function () use ($student, $data) {
            $student->update(['password' => $data['new_password']]);

            auth()->user()->update(['password' => $data['new_password']]);
        });

        $this->logProfileChange($student, 'profile_password_changed');

        $this->cachedStudent = null;
        $this->passwordForm->fill();

        Notification::make()
            ->title('Password updated successfully.')
            ->success()
            ->send();
    }

    public function updateGithub(): void
    {
        if (! $this->checkRateLimit()) {
            return;
        }

        $data = $this->githubForm->getState();
        $student = $this->getStudent();

        if (! $student) {
            return;
        }

        $username = trim($data['github_username'] ?? '');

        if ($username !== '') {
            // Validate exists on GitHub
            try {
                $response = Http::connectTimeout(3)->timeout(5)->get("https://api.github.com/users/{$username}");

                if (! $response->successful()) {
                    throw ValidationException::withMessages([
                        'githubData.github_username' => 'This GitHub username does not exist.',
                    ]);
                }
            } catch (ValidationException $e) {
                throw $e;
            } catch (\Throwable) {
                // GitHub API unreachable, allow it through
            }

            // Uniqueness check
            $taken = Student::query()
                ->where('github_username', $username)
                ->where('id', '!=', $student->id)
                ->exists();

            if ($taken) {
                throw ValidationException::withMessages([
                    'githubData.github_username' => 'This GitHub username is already linked to another student.',
                ]);
            }
        }

        $oldGithub = $student->github_username;

        $student->update([
            'github_username' => $username ?: null,
        ]);

        $this->logProfileChange($student, 'profile_github_updated', ['github_username' => $oldGithub], ['github_username' => $username ?: null]);

        $this->cachedStudent = null;

        // Backfill any unmatched lab grades for the new username
        if ($username !== '') {
            $backfill = app(BackfillLabGradesService::class)->backfillForStudent($student);

            if ($backfill['grades_created'] > 0) {
                Notification::make()
                    ->title('GitHub username updated.')
                    ->body($backfill['grades_created'].' lab grade(s) were automatically linked to your account.')
                    ->success()
                    ->send();

                return;
            }
        }

        Notification::make()
            ->title('GitHub username updated.')
            ->success()
            ->send();
    }

    public function updateGender(): void
    {
        if (! $this->checkRateLimit()) {
            return;
        }

        $data = $this->genderForm->getState();
        $student = $this->getStudent();

        if (! $student) {
            return;
        }

        $value = $data['gender'] ?? '';

        if ($value !== '' && ! in_array($value, ['Male', 'Female'], true)) {
            throw ValidationException::withMessages([
                'genderData.gender' => 'Please select a valid option.',
            ]);
        }

        $oldGender = $student->gender;

        $student->update([
            'gender' => $value ?: null,
        ]);

        $this->logProfileChange($student, 'profile_gender_updated', ['gender' => $oldGender], ['gender' => $value ?: null]);

        $this->cachedStudent = null;

        Notification::make()
            ->title('Sex updated.')
            ->success()
            ->send();
    }

    protected function getStudent(): ?Student
    {
        if ($this->cachedStudent === null) {
            $this->cachedStudent = Student::findByEmail(auth()->user()->email);
        }

        return $this->cachedStudent;
    }

    protected function checkRateLimit(): bool
    {
        $key = 'profile-update:'.auth()->id();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            Notification::make()->title('Too many attempts. Please wait a moment.')->danger()->send();

            return false;
        }

        RateLimiter::hit($key, 60);

        return true;
    }

    protected function logProfileChange(Student $student, string $action, ?array $oldValues = null, ?array $newValues = null): void
    {
        GradeAuditLog::create([
            'auditable_type' => Student::class,
            'auditable_id' => $student->id,
            'user_id' => auth()->id(),
            'action' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
        ]);
    }
}
