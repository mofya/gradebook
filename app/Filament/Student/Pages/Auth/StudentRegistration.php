<?php

namespace App\Filament\Student\Pages\Auth;

use App\Enums\Role;
use App\Models\Student;
use App\Models\User;
use App\Services\BackfillLabGradesService;
use App\Services\OtpAuthService;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\SimplePage;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * @property-read Schema $form
 * @property-read Schema $otpForm
 * @property-read Schema $accountForm
 */
class StudentRegistration extends SimplePage
{
    use WithRateLimiting;

    protected static string $layout = 'filament-panels::components.layout.simple';

    protected static ?string $slug = 'register';

    /**
     * Step 1: Enter student ID
     * Step 2: Verify OTP sent to institutional email
     * Step 3: Set personal email, password, GitHub
     */
    public int $step = 1;

    public ?int $studentId = null;

    public ?string $studentName = null;

    public ?string $studentEmail = null;

    public ?string $existingGithub = null;

    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    public static function getRoutePath(): string
    {
        return '/register';
    }

    public function mount(): void
    {
        if (Filament::auth()->check()) {
            redirect()->intended(Filament::getUrl());
        }

        $this->form->fill();
    }

    public function lookupStudent(): void
    {
        try {
            $this->rateLimit(10);
        } catch (TooManyRequestsException $exception) {
            Notification::make()
                ->title("Too many requests. Please wait {$exception->secondsUntilAvailable} seconds.")
                ->danger()
                ->send();

            return;
        }

        $data = $this->form->getState();

        $student = Student::query()
            ->where('student_id_number', $data['student_id_number'])
            ->first();

        if (! $student) {
            throw ValidationException::withMessages([
                'data.student_id_number' => 'No student found with that Student ID. Please check and try again.',
            ]);
        }

        if ($student->isRegistered()) {
            throw ValidationException::withMessages([
                'data.student_id_number' => 'This student ID has already been registered. Please log in instead.',
            ]);
        }

        $this->studentId = $student->id;
        $this->studentName = "{$student->first_name} {$student->last_name}";
        $this->studentEmail = $student->email;
        $this->existingGithub = $student->github_username;

        // Clear any stale proof from a previous abandoned attempt
        $previousStudentId = session()->get('registration-student-id');

        if ($previousStudentId) {
            session()->forget("registration-verified:{$previousStudentId}");
        }

        // Bind the looked-up student to the session so verifyOtp/register
        // cannot be tricked into acting on a different student
        session()->put('registration-student-id', $student->id);

        // Send OTP to institutional email for identity verification
        $otpService = app(OtpAuthService::class);
        $code = $otpService->generateOtp($student->email);
        $otpService->sendOtp($student->email, $code);

        $this->step = 2;
        $this->otpForm->fill();

        Notification::make()
            ->title('Verification code sent to your university email.')
            ->success()
            ->send();
    }

    public function verifyOtp(): void
    {
        // Load the student from session — not from client-mutable properties
        $sessionStudentId = session()->get('registration-student-id');
        $student = $sessionStudentId ? Student::find($sessionStudentId) : null;

        if (! $student) {
            $this->goBack();

            return;
        }

        $data = $this->otpForm->getState();

        $otpService = app(OtpAuthService::class);
        $result = $otpService->verifyOtp($student->email, $data['code']);

        if (! $result['success']) {
            throw ValidationException::withMessages([
                'data.code' => $result['message'],
            ]);
        }

        // Store server-side proof keyed to the session-bound student
        session()->put("registration-verified:{$student->id}", true);

        $this->step = 3;

        $this->accountForm->fill([
            'github_username' => $this->existingGithub,
        ]);
    }

    public function register(): void
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            Notification::make()
                ->title("Too many requests. Please wait {$exception->secondsUntilAvailable} seconds.")
                ->danger()
                ->send();

            return;
        }

        // Use the session-bound student ID — never trust the client property
        $sessionStudentId = session()->get('registration-student-id');

        // Check server-side OTP proof exists — don't consume yet so validation
        // errors let the user retry without restarting the whole flow
        if (! $sessionStudentId || ! session()->has("registration-verified:{$sessionStudentId}")) {
            $this->goBack();

            Notification::make()
                ->title('Please verify your identity first.')
                ->danger()
                ->send();

            return;
        }

        $data = $this->accountForm->getState();

        $student = Student::find($sessionStudentId);

        if (! $student || $student->isRegistered()) {
            Notification::make()
                ->title('This account has already been registered.')
                ->danger()
                ->send();

            $this->goBack();

            return;
        }

        // Block personal_email that matches any student's institutional email
        $institutionalCollision = Student::query()
            ->where('email', $data['personal_email'])
            ->exists();

        if ($institutionalCollision) {
            throw ValidationException::withMessages([
                'data.personal_email' => 'This email cannot be used. Please choose a different email address.',
            ]);
        }

        // Validate GitHub username exists if provided
        if (filled($data['github_username'])) {
            if (! $this->validateGithubUsername($data['github_username'])) {
                throw ValidationException::withMessages([
                    'data.github_username' => 'This GitHub username does not exist. Please check the spelling.',
                ]);
            }
        }

        // All validation passed — consume the proof now so it can't be replayed
        session()->pull("registration-verified:{$sessionStudentId}");
        session()->forget('registration-student-id');

        DB::transaction(function () use ($student, $data) {
            $student->update([
                'personal_email' => $data['personal_email'],
                'password' => $data['password'],
                'github_username' => $data['github_username'] ?: $student->github_username,
                'registered_at' => now(),
            ]);

            // Create or update the User record linked to personal email
            $user = User::query()
                ->where('email', $student->email)
                ->orWhere('email', $data['personal_email'])
                ->first();

            if ($user) {
                $user->update([
                    'email' => $data['personal_email'],
                    'password' => $data['password'],
                ]);
            } else {
                User::forceCreate([
                    'name' => "{$student->first_name} {$student->last_name}",
                    'email' => $data['personal_email'],
                    'password' => $data['password'],
                    'role' => Role::Student,
                    'email_verified_at' => now(),
                ]);
            }
        });

        // Backfill any unmatched lab grades if GitHub username was set
        $student->refresh();

        if ($student->github_username) {
            app(BackfillLabGradesService::class)->backfillForStudent($student);
        }

        Notification::make()
            ->title('Account created successfully!')
            ->body('You can now log in with your personal email and password.')
            ->success()
            ->send();

        redirect()->to(Filament::getPanel('student')->getLoginUrl());
    }

    public function goBack(): void
    {
        $sessionStudentId = session()->pull('registration-student-id');

        if ($sessionStudentId) {
            session()->forget("registration-verified:{$sessionStudentId}");
        }

        $this->step = 1;
        $this->studentId = null;
        $this->studentName = null;
        $this->studentEmail = null;
        $this->existingGithub = null;
        $this->form->fill();
    }

    protected function validateGithubUsername(string $username): bool
    {
        try {
            $response = Http::connectTimeout(3)
                ->timeout(5)
                ->get("https://api.github.com/users/{$username}");

            return $response->successful();
        } catch (\Throwable) {
            // If GitHub API is unreachable, allow the username through
            return true;
        }
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('student_id_number')
                    ->label('Student ID')
                    ->placeholder('Enter your student ID number')
                    ->required()
                    ->autofocus(),
            ]);
    }

    public function defaultOtpForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('data');
    }

    public function otpForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Verification Code')
                    ->placeholder('Enter 6-digit code')
                    ->required()
                    ->maxLength(6)
                    ->autofocus()
                    ->autocomplete('one-time-code'),
            ]);
    }

    public function defaultAccountForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('data');
    }

    public function accountForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('personal_email')
                    ->label('Personal Email')
                    ->helperText('This will be your login email. Use an email you check regularly.')
                    ->email()
                    ->required()
                    ->unique('students', 'personal_email')
                    ->unique('users', 'email')
                    ->autofocus(),
                TextInput::make('password')
                    ->label('Password')
                    ->password()
                    ->revealable()
                    ->required()
                    ->minLength(8)
                    ->confirmed(),
                TextInput::make('password_confirmation')
                    ->label('Confirm Password')
                    ->password()
                    ->revealable()
                    ->required(),
                TextInput::make('github_username')
                    ->label('GitHub Username')
                    ->helperText(fn (): ?string => $this->existingGithub
                        ? "We have \"{$this->existingGithub}\" on record. Update it here if needed."
                        : 'Enter your GitHub username so we can link your lab submissions.'
                    )
                    ->prefix('github.com/')
                    ->maxLength(39),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getLookupFormContentComponent(),
                $this->getOtpFormContentComponent(),
                $this->getAccountFormContentComponent(),
            ]);
    }

    public function getLookupFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('lookupStudent')
            ->footer([
                Actions::make($this->getLookupFormActions())
                    ->fullWidth()
                    ->key('lookup-form-actions'),
            ])
            ->visible(fn (): bool => $this->step === 1);
    }

    public function getOtpFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('otpForm')])
            ->id('otpForm')
            ->livewireSubmitHandler('verifyOtp')
            ->footer([
                Actions::make($this->getOtpFormActions())
                    ->fullWidth()
                    ->key('otp-form-actions'),
            ])
            ->visible(fn (): bool => $this->step === 2);
    }

    public function getAccountFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('accountForm')])
            ->id('accountForm')
            ->livewireSubmitHandler('register')
            ->footer([
                Actions::make($this->getAccountFormActions())
                    ->fullWidth()
                    ->key('account-form-actions'),
            ])
            ->visible(fn (): bool => $this->step === 3);
    }

    /**
     * @return array<Action | ActionGroup>
     */
    protected function getLookupFormActions(): array
    {
        return [
            Action::make('lookupStudent')
                ->label('Find My Account')
                ->submit('lookupStudent'),
        ];
    }

    /**
     * @return array<Action | ActionGroup>
     */
    protected function getOtpFormActions(): array
    {
        return [
            Action::make('verifyOtp')
                ->label('Verify')
                ->submit('verifyOtp'),
            Action::make('goBack')
                ->label('Back')
                ->link()
                ->action('goBack'),
        ];
    }

    /**
     * @return array<Action | ActionGroup>
     */
    protected function getAccountFormActions(): array
    {
        return [
            Action::make('register')
                ->label('Create Account')
                ->submit('register'),
        ];
    }

    protected function hasFullWidthFormActions(): bool
    {
        return true;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Register';
    }

    public function getHeading(): string|Htmlable|null
    {
        return match ($this->step) {
            2 => 'Verify Your Identity',
            3 => "Welcome, {$this->studentName}!",
            default => 'Claim Your Account',
        };
    }

    public function getSubheading(): string|Htmlable|null
    {
        return match ($this->step) {
            2 => $this->getMaskedEmailMessage(),
            3 => 'Set up your login credentials below.',
            default => 'Enter your student ID to get started.',
        };
    }

    protected function getMaskedEmailMessage(): string
    {
        if (! $this->studentEmail) {
            return 'Check your university email for the verification code.';
        }

        $parts = explode('@', $this->studentEmail);
        $masked = substr($parts[0], 0, 2).'***@'.($parts[1] ?? '');

        return "We sent a verification code to {$masked}";
    }
}
