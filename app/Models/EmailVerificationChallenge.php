<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailVerificationChallenge extends Model
{
    protected $fillable = ['user_id', 'email', 'token_hash', 'expires_at', 'consumed_at'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'consumed_at' => 'datetime'];
    }

    public function isPending(): bool
    {
        return $this->consumed_at === null && $this->expires_at->isFuture();
    }
}
