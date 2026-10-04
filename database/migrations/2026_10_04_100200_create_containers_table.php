<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('containers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            // ISO 6346 number for sea/road, air waybill number for air freight.
            $table->string('container_no', 20);
            $table->string('seal_no', 40)->nullable();
            $table->string('container_type', 10)->nullable();
            $table->boolean('customs_cleared')->default(false);
            $table->string('storage_facility')->nullable();
            $table->date('storage_date')->nullable();
            $table->date('pickup_date')->nullable();
            $table->timestamps();

            $table->unique(['shipment_id', 'container_no']);
            $table->index(['company_id', 'container_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('containers');
    }
};
