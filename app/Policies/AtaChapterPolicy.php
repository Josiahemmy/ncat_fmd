<?php

namespace App\Policies;

use App\Models\AtaChapter;
use App\Models\User;

class AtaChapterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ata.view');
    }

    public function view(User $user): bool
    {
        return $user->can('ata.view');
    }

    public function create(User $user): bool
    {
        return $user->can('ata.manage');
    }

    public function update(User $user, AtaChapter $chapter): bool
    {
        return $user->can('ata.manage');
    }

    public function delete(User $user, AtaChapter $chapter): bool
    {
        return $user->can('ata.manage');
    }
}
