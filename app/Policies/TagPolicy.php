<?php

declare(strict_types=1);

namespace Kami\Cocktail\Policies;

use Kami\Cocktail\Models\Tag;
use Kami\Cocktail\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TagPolicy
{
    use HandlesAuthorization;

    public function create(User $user): bool
    {
        return $user->isBarAdmin(bar()->id)
            || $user->isBarModerator(bar()->id);
    }

    public function show(User $user, Tag $tag): bool
    {
        return $user->hasBarMembership($tag->bar_id);
    }

    public function edit(User $user, Tag $tag): bool
    {
        return $user->isBarAdmin($tag->bar_id)
            || $user->isBarModerator($tag->bar_id);
    }

    public function delete(User $user, Tag $tag): bool
    {
        return $user->isBarAdmin($tag->bar_id)
            || $user->isBarModerator($tag->bar_id);
    }
}
