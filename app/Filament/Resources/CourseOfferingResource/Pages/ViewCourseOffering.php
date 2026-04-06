<?php

namespace App\Filament\Resources\CourseOfferingResource\Pages;

use App\Filament\Resources\CourseOfferingResource;
use Filament\Actions;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewCourseOffering extends ViewRecord
{
    protected static string $resource = CourseOfferingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('verification_link')
                ->label(fn () => $this->record->hasValidVerificationToken() ? 'Verification Link' : 'Generate Verification Link')
                ->color('success')
                ->icon('heroicon-o-link')
                ->schema([
                    Forms\Components\TextInput::make('expiry_days')
                        ->label('Link expires in (days)')
                        ->numeric()
                        ->default(3)
                        ->minValue(1)
                        ->maxValue(30)
                        ->required(),
                ])
                ->modalHeading('Student Verification Link')
                ->modalDescription(function () {
                    if ($this->record->hasValidVerificationToken()) {
                        $expires = $this->record->verification_expires_at->diffForHumans();
                        $url = route('student.verify', ['token' => $this->record->verification_token]);

                        return "Active link (expires {$expires}): {$url}";
                    }

                    return 'Generate a shareable link for students to verify and update their details.';
                })
                ->modalSubmitActionLabel('Generate Link')
                ->action(function (array $data) {
                    $this->record->generateVerificationToken((int) $data['expiry_days']);
                    $url = route('student.verify', ['token' => $this->record->verification_token]);

                    Notification::make()
                        ->title('Verification link generated.')
                        ->body($url)
                        ->success()
                        ->persistent()
                        ->send();
                })
                ->extraModalFooterActions([
                    Actions\Action::make('revoke_link')
                        ->label('Revoke Link')
                        ->color('danger')
                        ->visible(fn () => $this->record->hasValidVerificationToken())
                        ->requiresConfirmation()
                        ->modalHeading('Revoke Verification Link')
                        ->modalDescription('This will immediately invalidate the current verification link.')
                        ->action(function () {
                            $this->record->revokeVerificationToken();
                            Notification::make()->title('Verification link revoked.')->success()->send();
                        }),
                ]),

            EditAction::make(),
        ];
    }
}
