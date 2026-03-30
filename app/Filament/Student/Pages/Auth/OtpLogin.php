<?php

namespace App\Filament\Student\Pages\Auth;

use App\Models\Student;
use App\Services\OtpAuthService;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
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
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * @property-read Schema $form
 * @property-read Schema $passwordForm
 * @property-read Schema $otpForm
 */
class OtpLogin extends SimplePage
{
    use WithRateLimiting;

    /**
     * Step 1: identifier input
     * Step 2: password login (for registered students)
     * Step 3: OTP verification
     */
    public int $step = 1;

    public ?string $studentEmail = null;

    public ?int $studentId = null;

    public bool $studentHasPassword = false;

    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    public function mount(): void
    {
        if (Filament::auth()->check()) {
            redirect()->intended(Filament::getUrl());
        }

        $this->form->fill();
    }

    public function requestOtp(): void
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

        $data = $this->form->getState();

        $otpService = app(OtpAuthService::class);

        $throttle = $otpService->throttleCheck($data['identifier']);

        if (! $throttle['allowed']) {
            Notification::make()
                ->title("Too many OTP requests. Please wait {$throttle['seconds']} seconds.")
                ->danger()
                ->send();

            return;
        }

        $student = $otpService->resolveStudent($data['identifier']);

        if (! $student) {
            throw ValidationException::withMessages([
                'data.identifier' => 'No student found with that email or student ID.',
            ]);
        }

        $this->studentId = $student->id;
        $this->studentEmail = $student->preferredEmail();
        $this->studentHasPassword = $student->isRegistered();

        // Bind the resolved student to the session so loginWithPassword/verifyOtp
        // cannot be tricked into authenticating a different student
        session()->put('login-student-id', $student->id);

        // If student has a password, show password form first
        if ($this->studentHasPassword) {
            $this->step = 2;
            $this->passwordForm->fill();

            return;
        }

        // Otherwise, go straight to OTP
        $this->sendOtpToStudent($student, $otpService);
        $this->step = 3;
        $this->otpForm->fill();
    }

    public function loginWithPassword(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            Notification::make()
                ->title("Too many requests. Please wait {$exception->secondsUntilAvailable} seconds.")
                ->danger()
                ->send();

            return null;
        }

        $data = $this->passwordForm->getState();

        $student = $this->getSessionStudent();

        if (! $student || ! Hash::check($data['password'], $student->password)) {
            throw ValidationException::withMessages([
                'data.password' => 'Incorrect password.',
            ]);
        }

        $otpService = app(OtpAuthService::class);
        $user = $otpService->ensureUserExists($student);

        session()->forget('login-student-id');
        Filament::auth()->login($user);
        session()->regenerate();

        return app(LoginResponse::class);
    }

    public function switchToOtp(): void
    {
        $student = $this->getSessionStudent();

        if (! $student) {
            $this->goBack();

            return;
        }

        $otpService = app(OtpAuthService::class);
        $this->sendOtpToStudent($student, $otpService);
        $this->step = 3;
        $this->otpForm->fill();
    }

    public function verifyOtp(): ?LoginResponse
    {
        $data = $this->otpForm->getState();

        $student = $this->getSessionStudent();

        if (! $student) {
            $this->goBack();

            return null;
        }

        $otpService = app(OtpAuthService::class);

        // Verify against the session-bound student's preferred email, not client state
        $result = $otpService->verifyOtp($student->preferredEmail(), $data['code']);

        if (! $result['success']) {
            throw ValidationException::withMessages([
                'data.code' => $result['message'],
            ]);
        }

        $user = $otpService->ensureUserExists($student);

        session()->forget('login-student-id');
        Filament::auth()->login($user);
        session()->regenerate();

        return app(LoginResponse::class);
    }

    public function goBack(): void
    {
        session()->forget('login-student-id');
        $this->step = 1;
        $this->studentEmail = null;
        $this->studentId = null;
        $this->studentHasPassword = false;
        $this->form->fill();
    }

    protected function getSessionStudent(): ?Student
    {
        $sessionStudentId = session()->get('login-student-id');

        return $sessionStudentId ? Student::find($sessionStudentId) : null;
    }

    protected function sendOtpToStudent(Student $student, OtpAuthService $otpService): void
    {
        $email = $student->preferredEmail();
        $this->studentEmail = $email;

        if ($otpService->canResend($email)) {
            $code = $otpService->generateOtp($email);
            $otpService->sendOtp($email, $code);
        }

        Notification::make()
            ->title('Verification code sent to your email.')
            ->success()
            ->send();
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
                TextInput::make('identifier')
                    ->label('Email or Student ID')
                    ->placeholder('Enter your email or student ID number')
                    ->required()
                    ->autofocus()
                    ->autocomplete('email'),
            ]);
    }

    public function defaultPasswordForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('data');
    }

    public function passwordForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('password')
                    ->label('Password')
                    ->password()
                    ->revealable()
                    ->required()
                    ->autofocus()
                    ->autocomplete('current-password'),
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

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getIdentifierFormContentComponent(),
                $this->getPasswordFormContentComponent(),
                $this->getOtpFormContentComponent(),
            ]);
    }

    public function getIdentifierFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('requestOtp')
            ->footer([
                Actions::make($this->getIdentifierFormActions())
                    ->fullWidth()
                    ->key('identifier-form-actions'),
            ])
            ->visible(fn (): bool => $this->step === 1);
    }

    public function getPasswordFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('passwordForm')])
            ->id('passwordForm')
            ->livewireSubmitHandler('loginWithPassword')
            ->footer([
                Actions::make($this->getPasswordFormActions())
                    ->fullWidth()
                    ->key('password-form-actions'),
            ])
            ->visible(fn (): bool => $this->step === 2);
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
            ->visible(fn (): bool => $this->step === 3);
    }

    /**
     * @return array<Action | ActionGroup>
     */
    protected function getIdentifierFormActions(): array
    {
        return [
            Action::make('requestOtp')
                ->label('Continue')
                ->submit('requestOtp'),
        ];
    }

    /**
     * @return array<Action | ActionGroup>
     */
    protected function getPasswordFormActions(): array
    {
        return [
            Action::make('loginWithPassword')
                ->label('Log In')
                ->submit('loginWithPassword'),
            Action::make('switchToOtp')
                ->label('Use verification code instead')
                ->link()
                ->action('switchToOtp'),
            Action::make('goBack')
                ->label('Back')
                ->link()
                ->action('goBack'),
        ];
    }

    /**
     * @return array<Action | ActionGroup>
     */
    protected function getOtpFormActions(): array
    {
        return [
            Action::make('verifyOtp')
                ->label('Verify & Login')
                ->submit('verifyOtp'),
            Action::make('goBack')
                ->label('Back')
                ->link()
                ->action('goBack'),
        ];
    }

    protected function hasFullWidthFormActions(): bool
    {
        return true;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Student Login';
    }

    public function getHeading(): string|Htmlable|null
    {
        return match ($this->step) {
            2 => 'Enter Your Password',
            3 => 'Enter Verification Code',
            default => 'Student Login',
        };
    }

    public function getSubheading(): string|Htmlable|null
    {
        return match ($this->step) {
            2 => 'Or use a verification code sent to your email.',
            3 => $this->getMaskedEmailMessage(),
            default => 'Enter your email or student ID to continue.',
        };
    }

    protected function getMaskedEmailMessage(): string
    {
        if (! $this->studentEmail) {
            return 'Check your email for the verification code.';
        }

        // Mask email: show first 2 chars + domain
        $parts = explode('@', $this->studentEmail);
        $masked = substr($parts[0], 0, 2).'***@'.($parts[1] ?? '');

        return "We sent a code to {$masked}";
    }
}
