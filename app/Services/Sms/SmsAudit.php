<?php

namespace App\Services\Sms;

use App\Models\SmsAuditLog;
use Illuminate\Database\Eloquent\Model;

class SmsAudit
{
    public static function log(string $action, ?Model $subject = null, array $meta = []): void
    {
        SmsAuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'meta' => $meta ?: null,
            'ip' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }
}
