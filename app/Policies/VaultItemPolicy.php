<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VaultItem;

class VaultItemPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, VaultItem $item): bool
    {
        return $item->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, VaultItem $item): bool
    {
        return $item->user_id === $user->id;
    }

    public function delete(User $user, VaultItem $item): bool
    {
        return $item->user_id === $user->id;
    }

    public function restore(User $user, VaultItem $item): bool
    {
        return $item->user_id === $user->id;
    }

    public function forceDelete(User $user, VaultItem $item): bool
    {
        return $item->user_id === $user->id;
    }
}
