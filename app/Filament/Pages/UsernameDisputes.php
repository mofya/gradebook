<?php

namespace App\Filament\Pages;

use App\Models\GradeAuditLog;
use App\Models\Student;
use App\Models\UsernameDispute;
use App\Services\BackfillLabGradesService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class UsernameDisputes extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static string|\UnitEnum|null $navigationGroup = 'Students & Grading';

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'Username Disputes';

    protected string $view = 'filament.pages.username-disputes';

    public static function getNavigationBadge(): ?string
    {
        $count = UsernameDispute::where('status', 'pending')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public function getViewData(): array
    {
        $disputes = UsernameDispute::with([
            'claimant',
            'currentHolder',
            'courseOffering.course',
            'resolvedByUser',
        ])
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderBy('created_at', 'desc')
            ->get();

        return [
            'disputes' => $disputes,
        ];
    }

    public function assignToClaimant(int $disputeId): void
    {
        $dispute = UsernameDispute::with(['claimant', 'currentHolder'])->findOrFail($disputeId);

        if ($dispute->status !== 'pending') {
            Notification::make()->title('This dispute has already been resolved.')->warning()->send();

            return;
        }

        $currentHolder = $dispute->currentHolder;
        $claimant = $dispute->claimant;
        $username = $dispute->github_username;

        // Clear from current holder
        GradeAuditLog::create([
            'auditable_type' => Student::class,
            'auditable_id' => $currentHolder->id,
            'user_id' => auth()->id(),
            'action' => 'github_removed',
            'old_values' => ['github_username' => $username],
            'new_values' => ['github_username' => null],
            'reason' => "Dispute #{$dispute->id}: reassigned to {$claimant->student_id_number}.",
            'ip_address' => request()->ip(),
        ]);

        $currentHolder->update(['github_username' => null]);

        // Assign to claimant
        $oldGithub = $claimant->github_username;
        $claimant->update(['github_username' => $username]);

        GradeAuditLog::create([
            'auditable_type' => Student::class,
            'auditable_id' => $claimant->id,
            'user_id' => auth()->id(),
            'action' => 'github_reassigned',
            'old_values' => ['github_username' => $oldGithub],
            'new_values' => ['github_username' => $username],
            'reason' => "Dispute #{$dispute->id}: reassigned from {$currentHolder->student_id_number}.",
            'ip_address' => request()->ip(),
        ]);

        // Resolve dispute
        $dispute->update([
            'status' => 'resolved',
            'resolved_by' => auth()->id(),
            'resolved_at' => now(),
            'resolution_notes' => 'Assigned to claimant via admin UI.',
        ]);

        // Backfill lab grades
        $backfill = app(BackfillLabGradesService::class)->backfillForStudent($claimant);

        $msg = "Username '{$username}' reassigned to {$claimant->first_name} {$claimant->last_name}.";
        if (($backfill['grades_created'] ?? 0) > 0) {
            $msg .= " {$backfill['grades_created']} lab grade(s) backfilled.";
        }

        Notification::make()->title('Dispute resolved.')->body($msg)->success()->send();
    }

    public function keepCurrentHolder(int $disputeId): void
    {
        $dispute = UsernameDispute::findOrFail($disputeId);

        if ($dispute->status !== 'pending') {
            Notification::make()->title('This dispute has already been resolved.')->warning()->send();

            return;
        }

        $dispute->update([
            'status' => 'rejected',
            'resolved_by' => auth()->id(),
            'resolved_at' => now(),
            'resolution_notes' => 'Kept with current holder via admin UI.',
        ]);

        Notification::make()->title('Dispute rejected.')->body('Username stays with the current holder.')->success()->send();
    }
}
