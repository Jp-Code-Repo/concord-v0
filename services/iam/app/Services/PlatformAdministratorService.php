<?php

namespace App\Services;

use App\Models\PlatformAdministrator;

class PlatformAdministratorService
{
    /**
     * Determine whether a user holds Concord's central Administrator designation.
     */
    public function isDesignated(string $userId): bool
    {
        return PlatformAdministrator::query()
            ->where('id', 1)
            ->where('user_id', $userId)
            ->exists();
    }

    /**
     * Return the user ID holding the central Administrator designation, if any.
     */
    public function designatedUserId(): ?string
    {
        $userId = PlatformAdministrator::query()
            ->whereKey(1)
            ->value('user_id');

        return $userId === null ? null : (string) $userId;
    }
}