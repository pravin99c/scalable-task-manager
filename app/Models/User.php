<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected static function booted(): void
    {
        static::updating(function (self $user) {
            // A changed email address must prove ownership again.
            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
                $user->session_version = ((int) $user->getRawOriginal('session_version')) + 1;
                $user->remember_token = Str::random(60);
            }
        });

        static::updated(function (self $user) {
            if (! $user->wasChanged('email')) {
                return;
            }

            // This hook applies to the API and the legacy admin user editor.
            // For the API, EmailChangeService ensures the whole operation is
            // inside a transaction; the administrator editor does likewise.
            $previous = Str::lower((string) $user->getRawOriginal('email'));
            $id = $user->getKey();
            $memberProjectIds = DB::table('project_user')
                ->where('user_id', $id)->pluck('project_id');

            if ($memberProjectIds->isNotEmpty()) {
                Task::query()->where('assigned_to', $id)
                    ->whereIn('project_id', $memberProjectIds)
                    ->update(['assigned_to' => null]);
            }

            DB::table('project_user')->where('user_id', $id)->delete();

            // Do not retain invitation authority associated with the old
            // mailbox (either as invitee or as issuer).
            ProjectInvitation::query()
                ->whereNull('accepted_at')->whereNull('revoked_at')
                ->where(function ($q) use ($id, $previous) {
                    $q->where('email', $previous)->orWhere('invited_by', $id);
                })->update(['revoked_at' => now()]);

            EmailVerificationChallenge::query()
                ->where('user_id', $id)->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);

            DB::table('password_reset_tokens')->where('email', $previous)->delete();
            DB::table('sessions')->where('user_id', $id)->delete();
            $user->tokens()->delete();
        });
    }

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    public function hasRole(string $slug): bool
    {
        return $this->roles()->where('slug', $slug)->exists();
    }

    public function hasPermission(string $permissionSlug): bool
    {
        return $this->roles()->whereHas('permissions', function ($query) use ($permissionSlug) {
            $query->where('slug', $permissionSlug);
        })->exists();
    }
}
