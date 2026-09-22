<?php

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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_super_admin')->default(false);
            $table->foreignId('location_id')->nullable()->constrained()->restrictOnDelete();
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable()->constrained()->restrictOnDelete();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable()->constrained()->restrictOnDelete();
        });

        $now = now();
        $locationId = DB::table('locations')->insertGetId([
            'name' => config('app.company_name') ?: 'Main Office',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('users')->update(['location_id' => $locationId]);
        DB::table('users')->where('is_admin', true)->update([
            'is_super_admin' => true,
            'location_id' => null,
        ]);
        DB::table('leads')->update(['location_id' => $locationId]);
        DB::table('sales')->update(['location_id' => $locationId]);

        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable(false)->change();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('location_id');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('location_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('location_id');
            $table->dropColumn('is_super_admin');
        });
    }
};
