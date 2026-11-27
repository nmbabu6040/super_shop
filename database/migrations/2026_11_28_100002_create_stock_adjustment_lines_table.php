<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('stock_adjustment_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_adjustment_id')->constrained('stock_adjustments')->restrictOnDelete();
            $table->foreignId('variant_id')->constrained('product_variants')->restrictOnDelete();

            // What was requested.
            $table->string('batch_number', 60)->nullable();
            $table->date('expiry_date')->nullable();
            // Signed: positive adds stock, negative removes it.
            $table->decimal('quantity_change', 14, 4);
            $table->decimal('unit_cost', 14, 4)->nullable();
            $table->string('notes', 255)->nullable();
            $table->unsignedSmallInteger('line_number');

            // What happened when the adjustment was approved.
            $table->foreignId('batch_id')->nullable()->constrained('stock_batches')->restrictOnDelete();
            $table->foreignId('stock_movement_id')->nullable()->constrained('stock_movements')->restrictOnDelete();
            $table->decimal('applied_unit_cost', 14, 4)->nullable();
            $table->decimal('total_cost', 16, 2)->nullable();
            $table->timestamps();

            $table->index('variant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustment_lines');
    }
};
