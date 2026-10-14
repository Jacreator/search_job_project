<?php

declare(strict_types=1);

use App\Support\CompanyName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sponsors', function (Blueprint $table) {
            $table->string('normalised_name')->default('')->after('name')->index();
        });

        // Fill existing rows. New and renamed rows are set by the model and the import.
        DB::table('sponsors')->select(['id', 'name'])->chunkById(1000, function ($rows): void {
            DB::transaction(function () use ($rows): void {
                foreach ($rows as $row) {
                    DB::table('sponsors')
                        ->where('id', $row->id)
                        ->update(['normalised_name' => CompanyName::normalise((string) $row->name)]);
                }
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sponsors', function (Blueprint $table) {
            $table->dropIndex(['normalised_name']);
            $table->dropColumn('normalised_name');
        });
    }
};
