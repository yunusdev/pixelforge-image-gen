<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A generation from submit to succeeded or failed. Every amount is integer nanodollars (ADR-0001).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Ours: sent as the Idempotency-Key and in metadata, so webhooks can match before the create call returns.
            $table->uuid('uuid')->unique();
            $table->string('pixelforge_id')->nullable()->unique();
            // waiting | queued | processing | succeeded | failed (App\Generations\GenerationStatus)
            $table->string('status', 20);

            // Settings
            $table->text('prompt');
            $table->string('size', 20);
            $table->string('quality', 20);
            $table->jsonb('reference_images');

            // Estimate, fixed at submit
            $table->bigInteger('estimate_low');
            $table->bigInteger('estimate_high');
            $table->bigInteger('estimate_provider_cost_low');
            $table->bigInteger('estimate_provider_cost_high');

            // Rates Pixelforge locked at acceptance, nanodollars per token
            $table->bigInteger('rate_input_text_tokens')->nullable();
            $table->bigInteger('rate_input_image_tokens')->nullable();
            $table->bigInteger('rate_output_tokens')->nullable();
            $table->bigInteger('rate_reasoning_tokens')->nullable();

            // Usage, known once succeeded
            $table->integer('usage_input_text_tokens')->nullable();
            $table->integer('usage_input_image_tokens')->nullable();
            $table->integer('usage_output_tokens')->nullable();
            $table->integer('usage_reasoning_tokens')->nullable();

            // Recorded once succeeded (ADR-0002)
            $table->bigInteger('provider_cost')->nullable();
            $table->bigInteger('actual_charge')->nullable();
            $table->boolean('capped')->default(false);
            $table->bigInteger('absorbed')->default(0);

            // Why it failed: a Pixelforge error code, or too_busy when retries ran out
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();

            $table->text('image_url')->nullable();
            $table->timestamp('image_url_expires_at')->nullable();

            // Submit bookkeeping: busy replies counted toward the 5-try limit
            $table->unsignedSmallInteger('submit_tries')->default(0);
            $table->string('last_submit_error')->nullable();

            $table->timestamps();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->index(['user_id', 'id']);
            $table->index(['status', 'updated_at']);
            $table->index('completed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generations');
    }
};
