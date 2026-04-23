<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Share;
use App\Models\User;

final class SharePolicy
{
    public function delete(User $user, Share $share): bool
    {
        return $share->user_id === $user->id;
    }
}
