<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_items', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 24)->index(); // city, area, subject, class, mode
            $table->string('name', 100);
            $table->foreignId('parent_id')->nullable()->constrained('catalog_items')->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['kind', 'name', 'parent_id'], 'uq_catalog_kind_name_parent');
        });

        Schema::create('area_adjacencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained('catalog_items')->cascadeOnDelete();
            $table->foreignId('nearby_area_id')->constrained('catalog_items')->cascadeOnDelete();
            $table->integer('priority')->default(1);
            $table->timestamps();

            $table->unique(['area_id', 'nearby_area_id'], 'uq_area_adjacency');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('area_adjacencies');
        Schema::dropIfExists('catalog_items');
    }
};
