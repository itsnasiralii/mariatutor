<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tutor_id')->constrained('tutor_profiles')->cascadeOnDelete();
            $table->string('document_type', 32);
            $table->string('original_name', 200);
            $table->string('stored_name', 80)->unique();
            $table->string('review_status', 24)->default('Pending');
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('review_note', 500)->nullable();
            $table->timestamp('uploaded_at')->useCurrent();
            $table->timestamps();
        });

        Schema::create('lead_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('tuition_requests')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 60);
            $table->string('details', 500)->nullable();
            $table->boolean('private')->default(true);
            $table->timestamps();
        });

        Schema::create('commission_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('rule_type', 24); // 'percent' or 'fixed'
            $table->integer('value');
            $table->boolean('active')->default(true);
            $table->timestamp('effective_at')->useCurrent();
            $table->timestamps();
        });

        Schema::create('payment_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('tuition_requests');
            $table->integer('amount_pkr');
            $table->integer('platform_commission_pkr');
            $table->integer('tutor_amount_pkr');
            $table->string('method', 32);
            $table->string('reference', 120)->nullable();
            $table->string('status', 24)->default('Recorded');
            $table->foreignId('recorded_by_id')->constrained('users');
            $table->string('notes', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('tuition_requests');
            $table->foreignId('tutor_id')->constrained('tutor_profiles');
            $table->foreignId('parent_user_id')->constrained('users');
            $table->integer('rating');
            $table->text('body')->nullable();
            $table->string('status', 24)->default('Pending');
            $table->foreignId('moderator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80);
            $table->string('resource_type', 60);
            $table->string('resource_id', 64)->nullable();
            $table->string('summary', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('app_errors', function (Blueprint $table) {
            $table->id();
            $table->string('error_ref', 20)->unique();
            $table->string('module', 80);
            $table->string('summary', 300);
            $table->text('details')->nullable();
            $table->boolean('resolved')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_errors');
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('payment_entries');
        Schema::dropIfExists('commission_rules');
        Schema::dropIfExists('lead_events');
        Schema::dropIfExists('verification_documents');
    }
};
