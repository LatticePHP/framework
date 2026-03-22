<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

return new class {
    public function up(): void
    {
        Capsule::schema()->create('activities', function (Blueprint $table): void {
            $table->id();
            $table->string('type');
            $table->string('subject');
            $table->text('description')->nullable();
            $table->timestamp('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('deal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['workspace_id', 'type']);
            $table->index(['workspace_id', 'due_date']);
            $table->index(['workspace_id', 'completed_at']);
            $table->index(['workspace_id', 'contact_id']);
            $table->index(['workspace_id', 'deal_id']);
        });
    }

    public function down(): void
    {
        Capsule::schema()->dropIfExists('activities');
    }
};
