<?php

namespace Database\Seeders;

use App\Models\EmailVerificationChallenge;
use App\Models\Project;
use App\Models\ProjectInvitation;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Synthetic, disposable browser-test data. The double opt-in prevents this
 * seeder from being used on a production or a normal local database.
 *
 * Do not call from DatabaseSeeder or any deploy script.
 */
class TaskForgeE2eSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing')
            || env('TASKFORGE_ALLOW_E2E_SEED') !== '1') {
            throw new RuntimeException('E2E data seeding is permitted only in an explicitly opted-in testing environment.');
        }

        $password = Hash::make('Test-Only-A7x9-S3cure-Pass!');

        $owner = User::create([
            'name' => 'E2E Project Owner',
            'email' => 'e2e-owner@example.test',
            'password' => $password,
        ]);
        $owner->forceFill(['email_verified_at' => now()])->save();

        $viewer = User::create([
            'name' => 'E2E Readonly Viewer',
            'email' => 'e2e-viewer@example.test',
            'password' => $password,
        ]);
        $viewer->forceFill(['email_verified_at' => now()])->save();

        $invitee = User::create([
            'name' => 'E2E Pending Invitee',
            'email' => 'e2e-invitee@example.test',
            'email_verified_at' => null,
            'password' => $password,
        ]);
        User::create([
            'name' => 'E2E Pending Verification',
            'email' => 'e2e-pending@example.test',
            'email_verified_at' => null,
            'password' => $password,
        ]);

        $project = Project::create([
            'name' => 'E2E Launch Project',
            'description' => 'Disposable browser-test workspace',
            'created_by' => $owner->id,
        ]);
        $project->members()->attach($viewer->id, ['role' => 'viewer']);

        Task::create([
            'title' => 'E2E Seeded Backlog Task',
            'description' => 'A harmless example task',
            'project_id' => $project->id,
            'created_by' => $owner->id,
            'status' => 'TODO',
            'priority' => 'MEDIUM',
        ]);

        // 80-character tokens exist only in synthetic CI fixtures; the
        // application stores hashes, exactly as it does in production.
        EmailVerificationChallenge::create([
            'user_id' => $invitee->id,
            'email' => $invitee->email,
            'token_hash' => hash('sha256', str_repeat('v', 80)),
            'expires_at' => now()->addMinutes(30),
        ]);

        ProjectInvitation::create([
            'project_id' => $project->id,
            'invited_by' => $owner->id,
            'email' => $invitee->email,
            'role' => 'editor',
            'token_hash' => hash('sha256', str_repeat('i', 80)),
            'expires_at' => now()->addDays(7),
        ]);
    }
}
