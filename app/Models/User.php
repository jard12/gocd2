<?php

declare(strict_types=1);

namespace Kami\Cocktail\Models;

use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * @return Collection<int, UserIngredient>
     */
    public function getShelfIngredients(int $barId): Collection
    {
        return $this->getBarMembership($barId)?->userIngredients ?? new Collection();
    }

    /**
     * @return HasMany<BarMembership>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(BarMembership::class);
    }

    public function joinBarAs(Bar $bar, UserRoleEnum $role = UserRoleEnum::General): BarMembership
    {
        $existingMembership = $this->getBarMembership($bar->id);
        if ($existingMembership !== null) {
            return $existingMembership;
        }

        $barMemberShip = new BarMembership();
        $barMemberShip->bar_id = $bar->id;
        $barMemberShip->user_role_id = $role->value;

        $this->memberships()->save($barMemberShip);

        return $barMemberShip;
    }

    public function leaveBar(Bar $bar): void
    {
        $this->getBarMembership($bar->id)->delete();
    }

    /**
     * @return HasMany<Bar>
     */
    public function ownedBars(): HasMany
    {
        return $this->hasMany(Bar::class, 'created_user_id');
    }

    public function getBarMembership(int $barId): ?BarMembership
    {
        return $this->memberships->where('bar_id', $barId)->first();
    }

    public function hasBarMembership(int $barId): bool
    {
        return $this->getBarMembership($barId)?->id !== null;
    }

    public function isBarAdmin(int $barId): bool
    {
        return $this->hasBarRole($barId, UserRoleEnum::Admin);
    }

    public function isBarModerator(int $barId): bool
    {
        return $this->hasBarRole($barId, UserRoleEnum::Moderator);
    }

    public function isBarGeneral(int $barId): bool
    {
        return $this->hasBarRole($barId, UserRoleEnum::General);
    }

    public function isBarGuest(int $barId): bool
    {
        return $this->hasBarRole($barId, UserRoleEnum::Guest);
    }

    public function makeAnonymous(): self
    {
        $this->email = 'userdeleted' . Str::random(8);
        $this->name = 'Deleted User';
        $this->email_verified_at = null;
        $this->password = 'deleted';
        $this->remember_token = null;
        $this->created_at = now();
        $this->updated_at = now();
        $this->memberships()->delete();

        return $this;
    }

    private function hasBarRole(int $barId, UserRoleEnum $role): bool
    {
        return $this->memberships
            ->where('bar_id', $barId)
            ->where('user_role_id', $role->value)
            ->count() > 0;
    }
}
