<?php

namespace App\Observers;

use App\Models\GradeAuditLog;
use App\Models\GradeResult;

class GradeResultObserver
{
    public function created(GradeResult $gradeResult): void
    {
        $this->logAudit($gradeResult, 'created', [], $gradeResult->getAttributes());
    }

    public function updated(GradeResult $gradeResult): void
    {
        $oldValues = array_intersect_key(
            $gradeResult->getOriginal(),
            $gradeResult->getDirty()
        );

        $this->logAudit($gradeResult, 'updated', $oldValues, $gradeResult->getDirty());
    }

    public function deleted(GradeResult $gradeResult): void
    {
        $this->logAudit($gradeResult, 'deleted', $gradeResult->getAttributes(), []);
    }

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    protected function logAudit(GradeResult $gradeResult, string $action, array $oldValues, array $newValues): void
    {
        GradeAuditLog::create([
            'auditable_type' => GradeResult::class,
            'auditable_id' => $gradeResult->id,
            'user_id' => auth()->id(),
            'action' => $action,
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'ip_address' => request()?->ip(),
        ]);
    }
}
