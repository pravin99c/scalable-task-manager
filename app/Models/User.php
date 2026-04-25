<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the roles for the user.
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    /**
     * Check if user has a specific permission.
     */
    public function hasPermission($permissionSlug)
    {
        foreach ($this->roles as $role) {
            if ($role->permissions->where('slug', $permissionSlug)->count() > 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if user has a specific role.
     */
    public function hasRole($roleSlug)
    {
        return $this->roles->where('slug', $roleSlug)->count() > 0;
    }
}
