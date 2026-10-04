<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('filename');
            $table->string('status', 20);
            $table->unsignedInteger('rows_total')->default(0);
            $table->unsignedInteger('rows_imported')->default(0);
            $table->unsignedInteger('rows_skipped')->default(0);
            $table->unsignedInteger('warnings_count')->default(0);
            $table->unsignedInteger('shipments_created')->default(0);
            $table->unsignedInteger('containers_created')->default(0);
            $table->unsignedInteger('cargo_items_created')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('import_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_run_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('level', 10);
            $table->string('column')->nullable();
            $table->text('message');
            $table->json('raw')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['import_run_id', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_issues');
        Schema::dropIfExists('import_runs');
    }
};
