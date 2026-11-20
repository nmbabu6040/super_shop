<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('staff_invitations', function (Blueprint $table): void {
            $table->id();
            // One open invitation per user: sending again replaces the previous token.
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            // Only the SHA-256 hash of the token is stored; the plain token exists only in the email.
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_invitations');
    }
};
