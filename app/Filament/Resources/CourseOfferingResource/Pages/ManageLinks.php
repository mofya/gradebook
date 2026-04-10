<?php

namespace App\Filament\Resources\CourseOfferingResource\Pages;

use App\Filament\Resources\CourseOfferingResource;
use App\Models\CourseOffering;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;

class ManageLinks extends Page
{
    protected static string $resource = CourseOfferingResource::class;

    protected string $view = 'filament.resources.course-offering-resource.pages.manage-links';

    public CourseOffering $offering;

    public function mount(int|string $record): void
    {
        $this->offering = CourseOffering::with(['course', 'semester.year'])->findOrFail($record);

        $this->authorize('update', $this->offering);
    }

    public function getTitle(): string
    {
        return 'Manage Links';
    }

    public function getSubheading(): ?string
    {
        $code = $this->offering->course->code ?? '';
        $name = $this->offering->course->name ?? '';

        return "{$code} — {$name}";
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back_to_edit')
                ->label('Back to Edit')
                ->color('gray')
                ->icon('heroicon-o-arrow-left')
                ->url(fn () => CourseOfferingResource::getUrl('edit', ['record' => $this->offering])),
        ];
    }

    public function generateVerificationLink(int $days): void
    {
        $this->offering->generateVerificationToken($days);
        $this->offering->refresh();

        Notification::make()
            ->title('Verification link generated.')
            ->success()
            ->send();
    }

    public function extendVerificationLink(int $days): void
    {
        $this->offering->extendVerificationToken($days);
        $this->offering->refresh();

        Notification::make()
            ->title('Verification link expiry extended.')
            ->success()
            ->send();
    }

    public function revokeVerificationLink(): void
    {
        $this->offering->revokeVerificationToken();
        $this->offering->refresh();

        Notification::make()
            ->title('Verification link revoked.')
            ->success()
            ->send();
    }

    public function generatePublicGradeLink(int $days): void
    {
        $this->offering->generatePublicGradeToken($days);
        $this->offering->refresh();

        Notification::make()
            ->title('Public grade sheet link generated.')
            ->success()
            ->send();
    }

    public function extendPublicGradeLink(int $days): void
    {
        $this->offering->extendPublicGradeToken($days);
        $this->offering->refresh();

        Notification::make()
            ->title('Public grade sheet expiry extended.')
            ->success()
            ->send();
    }

    public function revokePublicGradeLink(): void
    {
        $this->offering->revokePublicGradeToken();
        $this->offering->refresh();

        Notification::make()
            ->title('Public grade sheet link revoked.')
            ->success()
            ->send();
    }

    public function getVerificationUrls(): array
    {
        if (! $this->offering->hasValidVerificationToken()) {
            return [];
        }

        return [
            'verify' => route('student.verify', ['token' => $this->offering->verification_token]),
            'grades' => route('student.grades', ['token' => $this->offering->verification_token]),
        ];
    }

    public function getPublicGradeUrl(): ?string
    {
        if (! $this->offering->hasValidPublicGradeToken()) {
            return null;
        }

        return route('class.grades', ['token' => $this->offering->public_grade_token]);
    }
}
