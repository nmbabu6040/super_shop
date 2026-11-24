<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignId('tax_rate_id')->nullable()->constrained('tax_rates')->restrictOnDelete();
            $table->string('product_type', 20)->default('general');
            $table->json('name');
            $table->json('description')->nullable();
            $table->string('slug', 150)->unique();

            // Stock behaviour flags (used by the inventory phase). Medicines always track batches and expiry.
            $table->boolean('track_batches')->default(false);
            $table->boolean('track_expiry')->default(false);
            $table->boolean('track_serial')->default(false);
            $table->decimal('reorder_level', 14, 4)->default(0);
            $table->decimal('min_stock', 14, 4)->default(0);
            $table->decimal('max_stock', 14, 4)->nullable();

            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_sold_online')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category_id', 'is_active']);
            $table->index(['is_active', 'is_sold_online']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
