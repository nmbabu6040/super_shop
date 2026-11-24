<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('path');
            $table->string('alt', 150)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);

            // Product id when this is the primary image, otherwise NULL. UNIQUE = one primary image per product.
            $table->foreignId('primary_for_product_id')
                ->nullable()
                ->unique()
                ->constrained('products')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->index(['product_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
