<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 40);
            $table->string('status', 20);
            $table->string('shipping_method', 10);
            $table->string('shipping_line')->nullable();
            $table->string('origin_port')->nullable();
            $table->string('destination_port');
            $table->string('incoterm', 10)->nullable();
            $table->date('etd');
            $table->date('eta');
            $table->date('ata')->nullable();
            $table->timestamp('status_changed_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'reference']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'eta']);
            $table->index(['company_id', 'etd']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
