<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

return new class {
    public function up(): void
    {
        Capsule::schema()->create('contacts', function (Blueprint $table): void {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title')->nullable();
            $table->string('status')->default('lead');
            $table->string('source')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->json('tags')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['workspace_id', 'status']);
            $table->index(['workspace_id', 'company_id']);
            $table->index(['workspace_id', 'owner_id']);
            $table->index(['workspace_id', 'email']);
        });
    }

    public function down(): void
    {
        Capsule::schema()->dropIfExists('contacts');
    }
};
