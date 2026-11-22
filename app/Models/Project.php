<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'created_by'];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    /** An owner is implicit; other users have an explicit project role. */
    public function members()
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    public function isOwner(User $user): bool
    {
        return (int) $this->created_by === (int) $user->getKey();
    }

    public function hasMember(User $user, ?string $role = null): bool
    {
        return $this->members()->whereKey($user->getKey())
            ->when($role !== null, fn ($query) => $query->wherePivot('role', $role))
            ->exists();
    }

    public function scopeAccessibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function ($q) use ($user) {
            $q->where('created_by', $user->getKey())
                ->orWhereHas('members', fn ($members) => $members->whereKey($user->getKey()));
        });
    }

    public function scopeEditableBy(Builder $query, User $user): Builder
    {
        return $query->where(function ($q) use ($user) {
            $q->where('created_by', $user->getKey())
                ->orWhereHas('members', fn ($members) => $members
                    ->whereKey($user->getKey())->wherePivot('role', 'editor'));
        });
    }
}
