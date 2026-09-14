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
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_status_id')->constrained()->restrictOnDelete();
            $table->string('sale_type');
            $table->string('street_address');
            $table->string('city');
            $table->string('state', 2);
            $table->string('postal_code', 10);
            $table->decimal('price', 12, 2);
            $table->decimal('commission_percentage', 3, 1);
            $table->decimal('gross_commission', 12, 2);
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
