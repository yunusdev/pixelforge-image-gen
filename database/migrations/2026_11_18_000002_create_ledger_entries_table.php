<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The ledger: append-only credit movements in signed nanodollars (ADR-0001), and the
 * balance cached on the user, kept equal to their total in the same transaction.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->bigInteger('balance')->default(0);
        });

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('amount');
            $table->string('reason');
            // At most one charge per generation, whatever processes it (webhook, sweep, job).
            $table->foreignId('generation_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('balance');
        });
    }
};
