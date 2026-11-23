<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tutor_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('tutor_code', 20)->unique()->index();
            $table->string('full_name', 120);
            $table->string('gender', 24)->default('Prefer not to say');
            $table->string('cnic_gender_hint', 12)->nullable();
            $table->string('qualification', 200)->nullable();
            $table->text('experience')->nullable();
            $table->integer('expected_fee_min')->nullable();
            $table->integer('expected_fee_max')->nullable();
            $table->string('availability', 300)->nullable();
            $table->string('verification_status', 32)->default('Pending')->index();
            $table->text('verification_note')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('parent_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('full_name', 120);
            $table->timestamps();
        });

        Schema::create('tutor_catalog', function (Blueprint $table) {
            $table->foreignId('tutor_id')->constrained('tutor_profiles')->cascadeOnDelete();
            $table->foreignId('catalog_id')->constrained('catalog_items')->restrictOnDelete();
            $table->primary(['tutor_id', 'catalog_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tutor_catalog');
        Schema::dropIfExists('parent_profiles');
        Schema::dropIfExists('tutor_profiles');
    }
};
