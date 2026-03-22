<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

return new class {
    public function up(): void
    {
        Capsule::schema()->create('workspaces', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->json('settings')->nullable();
            $table->string('logo_url')->nullable();
            $table->timestamps();
        });

        Capsule::schema()->create('workspace_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('member');
            $table->timestamp('joined_at')->nullable();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['workspace_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Capsule::schema()->dropIfExists('workspace_members');
        Capsule::schema()->dropIfExists('workspaces');
    }
};
