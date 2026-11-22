<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitation_rate_limit_locks', function (Blueprint $table) {
            // SHA-256 digest of the canonical recipient, project or issuer.
            // Unique rows provide an actual cross-owner MySQL lock target.
            $table->char('key_hash', 64)->primary();
            $table->timestamp('last_seen_at');
            $table->index('last_seen_at', 'invite_lock_last_seen');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('session_version')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('session_version');
        });

        Schema::dropIfExists('invitation_rate_limit_locks');
    }
};
