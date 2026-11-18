<?php

namespace Database\Seeders;

use App\Credits\Ledger;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Seeds the admin account graders log in with. Safe to run again: it never duplicates the user or the bonus. */
class GraderAdminSeeder extends Seeder
{
    public function run(Ledger $ledger): void
    {
        $email = config('admin.grader.email');
        $password = config('admin.grader.password');

        if (! is_string($email) || ! is_string($password) || $password === '') {
            $this->command?->warn('Set GRADER_ADMIN_PASSWORD to seed the grader admin.');

            return;
        }

        DB::transaction(function () use ($ledger, $email, $password): void {
            $grader = User::query()->firstOrNew(['email' => $email]);
            $isNew = ! $grader->exists;

            $grader->forceFill([
                'name' => $grader->name ?? 'Grader',
                'password' => $password,
                'email_verified_at' => $grader->email_verified_at ?? now(),
                'is_admin' => true,
            ])->save();

            if ($isNew) {
                $ledger->signUpBonus($grader);
            }
        });
    }
}
