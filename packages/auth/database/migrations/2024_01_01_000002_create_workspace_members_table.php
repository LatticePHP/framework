<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_members', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('user_id');
            $table->string('role', 50)->default('member');
            $table->timestamp('joined_at')->nullable();
            $table->unsignedBigInteger('invited_by')->nullable();
            $table->timestamps();

            $table->foreign('workspace_id')
                ->references('id')
                ->on('workspaces')
                ->onDelete('cascade');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->foreign('invited_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->unique(['workspace_id', 'user_id']);
            $table->index('user_id');
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_members');
    }
};
