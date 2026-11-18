<?php

use App\Models\User;
use Database\Seeders\GraderAdminSeeder;
use Illuminate\Support\Facades\Hash;

test('seeding creates the grader admin once, with the sign-up bonus, who can open the report', function () {
    config(['admin.grader.email' => 'grader@example.com', 'admin.grader.password' => 'correct-horse']);

    $this->seed(GraderAdminSeeder::class);
    $this->seed(GraderAdminSeeder::class);

    $grader = User::query()->where('email', 'grader@example.com')->sole();
    expect($grader->is_admin)->toBeTrue()
        ->and(Hash::check('correct-horse', $grader->password))->toBeTrue()
        ->and($grader->ledgerEntries()->count())->toBe(1);

    $this->post(route('login.store'), ['email' => 'grader@example.com', 'password' => 'correct-horse'])->assertRedirect();
    $this->assertAuthenticatedAs($grader);
});

test('without a grader password nothing is seeded', function () {
    config(['admin.grader.email' => 'grader@example.com', 'admin.grader.password' => null]);

    $this->seed(GraderAdminSeeder::class);

    expect(User::query()->where('email', 'grader@example.com')->exists())->toBeFalse();
});
