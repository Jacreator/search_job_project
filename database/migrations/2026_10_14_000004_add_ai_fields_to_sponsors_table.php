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
        Schema::table('sponsors', function (Blueprint $table) {
            $table->boolean('is_ai')->default(false)->after('tech_reason')->index();
            $table->string('ai_source')->nullable()->after('is_ai');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sponsors', function (Blueprint $table) {
            $table->dropIndex(['is_ai']);
            $table->dropColumn(['is_ai', 'ai_source']);
        });
    }
};
