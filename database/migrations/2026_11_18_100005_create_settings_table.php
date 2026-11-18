<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('group', 50);
            $table->string('key', 100);
            // Plain text, or Laravel-encrypted ciphertext when is_encrypted is true.
            $table->longText('value')->nullable();
            $table->string('type', 20)->default('string');
            $table->boolean('is_encrypted')->default(false);
            // Public settings may be shown on the storefront. Encrypted ones are never public.
            $table->boolean('is_public')->default(false);
            // Changes on every write, so the audit log can record "secret changed" without the secret.
            $table->timestamp('value_updated_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['group', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
