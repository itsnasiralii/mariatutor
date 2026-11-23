<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tuition_requests', function (Blueprint $table) {
            $table->id();
            $table->string('public_ref', 16)->unique()->index();
            $table->foreignId('parent_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('area_id')->constrained('catalog_items');
            $table->foreignId('subject_id')->constrained('catalog_items');
            $table->foreignId('class_id')->constrained('catalog_items');
            $table->foreignId('mode_id')->constrained('catalog_items');
            $table->string('subarea_text', 120)->nullable();
            $table->integer('budget_min')->nullable();
            $table->integer('budget_max')->nullable();
            $table->string('preferred_time', 120);
            $table->string('requirements', 500)->nullable();
            $table->string('tutor_gender_preference', 24)->default('Any');
            $table->string('status', 32)->default('New request')->index();
            $table->foreignId('assigned_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('selected_tutor_id')->nullable()->constrained('tutor_profiles')->nullOnDelete();
            $table->timestamp('contact_released_at')->nullable();
            $table->string('commission_snapshot_type', 24)->nullable();
            $table->integer('commission_snapshot_value')->nullable();
            $table->timestamps();
        });

        Schema::create('opportunity_exposures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('tuition_requests')->cascadeOnDelete();
            $table->foreignId('tutor_id')->constrained('tutor_profiles')->cascadeOnDelete();
            $table->timestamp('shown_at');
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('interested_at')->nullable();
            $table->string('interest_note', 240)->nullable();
            $table->timestamps();

            $table->unique(['request_id', 'tutor_id'], 'uq_exposure_request_tutor');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunity_exposures');
        Schema::dropIfExists('tuition_requests');
    }
};
