<?php

namespace App\Filament\Student\Pages\Auth;

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
use Illuminate\Validation\ValidationException;

/**
 * @property-read Schema $form
 * @property-read Schema $otpForm
 */
class OtpLogin extends SimplePage
{
    use WithRateLimiting;

    public int $step = 1;

    public ?string $studentEmail = null;

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

        $this->studentEmail = $student->email;
        $this->step = 2;
        $this->otpForm->fill();

        if ($otpService->canResend($student->email)) {
            $code = $otpService->generateOtp($student->email);
            $otpService->sendOtp($student->email, $code);
        }

        Notification::make()
            ->title('Verification code sent to your email.')
            ->success()
            ->send();
    }

    public function verifyOtp(): ?LoginResponse
    {
        $data = $this->otpForm->getState();

        $otpService = app(OtpAuthService::class);
        $result = $otpService->verifyOtp($this->studentEmail, $data['code']);

        if (! $result['success']) {
            throw ValidationException::withMessages([
                'data.code' => $result['message'],
            ]);
        }

        $student = $otpService->resolveStudent($this->studentEmail);
        $user = $otpService->ensureUserExists($student);

        Filament::auth()->login($user);
        session()->regenerate();

        return app(LoginResponse::class);
    }

    public function goBack(): void
    {
        $this->step = 1;
        $this->studentEmail = null;
        $this->form->fill();
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

    /**
     * @return array<Action | ActionGroup>
     */
    protected function getIdentifierFormActions(): array
    {
        return [
            Action::make('requestOtp')
                ->label('Send Verification Code')
                ->submit('requestOtp'),
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
        if ($this->step === 2) {
            return 'Enter Verification Code';
        }

        return 'Student Login';
    }

    public function getSubheading(): string|Htmlable|null
    {
        if ($this->step === 2) {
            return "We sent a code to {$this->studentEmail}";
        }

        return 'Enter your email or student ID to receive a login code.';
    }
}
