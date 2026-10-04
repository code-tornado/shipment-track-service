<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cargo_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('container_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();

            // Suppli references (read-only here).
            $table->string('proforma_invoice_no', 40);
            $table->string('exporter_ref', 40)->nullable();
            $table->string('customer_po', 40)->nullable();
            // Not unique: a net pen and its dead-fish collector share one tag.
            $table->string('tag_no', 40)->nullable();
            // Package / batch number from the packing slip; natural key for re-imports.
            $table->string('package_no', 40)->nullable();

            $table->unsignedInteger('quantity')->default(1);
            $table->string('unit', 10)->default('PC');
            $table->decimal('net_weight_kg', 10, 2)->nullable();
            $table->decimal('gross_weight_kg', 10, 2)->nullable();
            $table->boolean('is_stock')->default(false);
            $table->boolean('certificate_sent')->default(false);

            // Owned by this service, every change is audited in date_changes.
            $table->date('customer_delivery_date')->nullable();
            $table->date('delivered_at')->nullable();

            $table->text('comments')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'package_no']);
            $table->index(['company_id', 'proforma_invoice_no']);
            $table->index(['company_id', 'tag_no']);
            $table->index(['company_id', 'customer_delivery_date']);
            $table->index('customer_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cargo_items');
    }
};
