<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customers and products are reference data owned by Suppli. The service
 * keeps the few fields it needs to label cargo and to search by them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('name_normalized');
            $table->string('suppli_ref')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'name_normalized']);
            $table->index(['company_id', 'suppli_ref']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('material_code')->nullable();
            $table->string('description');
            $table->string('net_type', 40)->default('other');
            $table->timestamps();

            $table->unique(['company_id', 'material_code']);
            $table->index(['company_id', 'description']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
        Schema::dropIfExists('customers');
    }
};
