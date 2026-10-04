<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sponsors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('town')->nullable();
            $table->string('county')->nullable();
            $table->string('route');
            $table->string('rating');
            $table->string('rating_grade', 1)->nullable();
            $table->boolean('rating_grade_manual')->default(false);
            $table->string('region')->nullable();
            $table->unsignedSmallInteger('priority')->default(0)->index();
            $table->string('company_number')->nullable();
            $table->string('company_status')->nullable();
            $table->json('sic_codes')->nullable();
            $table->boolean('is_tech')->nullable();
            $table->string('tech_reason')->nullable();
            $table->string('website')->nullable();
            $table->unsignedTinyInteger('confidence')->nullable();
            $table->boolean('confirmed')->default(false);
            $table->string('status')->default('pending')->index();
            $table->string('skip_reason')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'name', 'town']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sponsors');
    }
};
