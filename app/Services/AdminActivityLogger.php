<?php

namespace App\Services;

use App\Models\AdminActivityLog;
use App\Models\User;
use App\Support\ActiveBranch;
use Illuminate\Database\Eloquent\Model;

final class AdminActivityLogger
{
    public static function log(
        string $action,
        string $description,
        ?int $branchId = null,
        ?Model $subject = null,
        array $metadata = [],
    ): void {
        $user = auth()->user();
        if (! $user instanceof User || ! self::shouldLogFor($user)) {
            return;
        }

        if ($branchId === null) {
            $branchId = ActiveBranch::id() ?? ($user->branch_id ? (int) $user->branch_id : null);
        }

        AdminActivityLog::query()->create([
            'user_id' => $user->id,
            'branch_id' => $branchId,
            'action' => $action,
            'description' => mb_substr($description, 0, 65535),
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'metadata' => $metadata === [] ? null : $metadata,
            'created_at' => now(),
        ]);
    }

    public static function shouldLogFor(User $user): bool
    {
        return $user->isAdmin() && ! $user->isSuperAdmin();
    }
}
