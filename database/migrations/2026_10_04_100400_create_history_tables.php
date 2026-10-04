<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->text('note')->nullable();
            $table->string('source', 20);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_label');
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['shipment_id', 'occurred_at']);
        });

        Schema::create('date_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('subject_type', 40);
            $table->unsignedBigInteger('subject_id');
            $table->string('field', 40);
            $table->date('old_value')->nullable();
            $table->date('new_value')->nullable();
            $table->text('reason');
            $table->string('source', 20);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_label');
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['subject_type', 'subject_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('date_changes');
        Schema::dropIfExists('status_history');
    }
};
