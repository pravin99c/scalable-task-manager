<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_invitations', function (Blueprint $table) {
            $table->timestamp('delivered_at')->nullable();
        });

        Schema::table('email_verification_challenges', function (Blueprint $table) {
            $table->timestamp('delivered_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('project_invitations', function (Blueprint $table) {
            $table->dropColumn('delivered_at');
        });
        Schema::table('email_verification_challenges', function (Blueprint $table) {
            $table->dropColumn('delivered_at');
        });
    }
};
