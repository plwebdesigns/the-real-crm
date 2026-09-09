<?php

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
        Schema::table('leads', function (Blueprint $table) {
            $table->string('location')->nullable();
            $table->string('property_type')->nullable();
            $table->string('price_range')->nullable();
            $table->unsignedTinyInteger('bedrooms')->nullable();
            $table->decimal('bathrooms', 3, 1)->nullable();
            $table->boolean('garage')->nullable();
            $table->boolean('pool')->nullable();
            $table->text('notes')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn([
                'location',
                'property_type',
                'price_range',
                'bedrooms',
                'bathrooms',
                'garage',
                'pool',
                'notes',
            ]);
        });
    }
};
