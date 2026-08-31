<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;

class ViewService
{
    public function record(Model $model): void
    {
        $sessionId = session()->getId();

        $exists = $model->views()
            ->where('session_id', $sessionId)
            ->where('created_at', '>=', now()->subMinutes(30))
            ->exists();

        if ($exists) {
            return;
        }

        $model->views()->create([
            'user_id' => auth()->id(),
            'session_id' => $sessionId,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }
}
