<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_invitations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->string('email');
            $table->string('role', 50)->default('member');
            $table->string('token', 64)->unique();
            $table->unsignedBigInteger('invited_by')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->foreign('workspace_id')
                ->references('id')
                ->on('workspaces')
                ->onDelete('cascade');

            $table->foreign('invited_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->index(['workspace_id', 'email']);
            $table->index('email');
            $table->index('token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_invitations');
    }
};
