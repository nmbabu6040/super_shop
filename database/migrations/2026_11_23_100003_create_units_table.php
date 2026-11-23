<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table): void {
            $table->id();
            // Stable machine code (e.g. "kg"); never changes after creation.
            $table->string('code', 20)->unique();
            $table->json('name');
            // Short text shown on prices and receipts, e.g. "kg".
            $table->string('symbol', 10);
            // Whether quantities may be fractional (weight, volume), and how many decimals.
            $table->boolean('is_decimal')->default(false);
            $table->unsignedTinyInteger('decimal_places')->default(0);
            // A derived unit points to a base unit: 1 derived = conversion_factor x base (g -> kg: 0.001).
            $table->foreignId('base_unit_id')->nullable()->constrained('units')->restrictOnDelete();
            $table->decimal('conversion_factor', 14, 6)->default(1);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
