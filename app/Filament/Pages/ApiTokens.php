<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Sanctum\PersonalAccessToken;

class ApiTokens extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'API Tokens';

    protected string $view = 'filament.pages.api-tokens';

    public string $tokenName = '';

    public ?string $plainTextToken = null;

    public function getTokensProperty(): Collection
    {
        return auth()->user()->tokens()
            ->orderByDesc('created_at')
            ->get();
    }

    public function createToken(): void
    {
        $name = trim($this->tokenName);

        if (blank($name)) {
            Notification::make()
                ->title('Please enter a token name.')
                ->danger()
                ->send();

            return;
        }

        $token = auth()->user()->createToken($name);

        $this->plainTextToken = $token->plainTextToken;
        $this->tokenName = '';

        Notification::make()
            ->title('API token created.')
            ->body('Copy the token now — it won\'t be shown again.')
            ->success()
            ->send();
    }

    public function dismissToken(): void
    {
        $this->plainTextToken = null;
    }

    public function revokeToken(int $tokenId): void
    {
        $token = PersonalAccessToken::find($tokenId);

        if ($token && $token->tokenable_id === auth()->id()) {
            $token->delete();

            Notification::make()
                ->title('Token revoked.')
                ->success()
                ->send();
        }
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && ($user->isAdmin() || $user->isLecturer());
    }
}
