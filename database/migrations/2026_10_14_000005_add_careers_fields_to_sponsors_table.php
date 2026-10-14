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
            $table->string('careers_url', 2048)->nullable()->after('needs_second_check');
            $table->boolean('mentions_developer_roles')->nullable()->after('careers_url');
            $table->string('visa_sponsorship')->nullable()->after('mentions_developer_roles');
            $table->timestamp('careers_checked_at')->nullable()->after('visa_sponsorship');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sponsors', function (Blueprint $table) {
            $table->dropColumn(['careers_url', 'mentions_developer_roles', 'visa_sponsorship', 'careers_checked_at']);
        });
    }
};
