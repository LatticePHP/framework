<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

return new class {
    public function up(): void
    {
        Capsule::schema()->create('notes', function (Blueprint $table): void {
            $table->id();
            $table->text('content');
            $table->string('notable_type');
            $table->unsignedBigInteger('notable_id');
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_pinned')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['notable_type', 'notable_id']);
            $table->index(['workspace_id', 'notable_type', 'notable_id']);
        });
    }

    public function down(): void
    {
        Capsule::schema()->dropIfExists('notes');
    }
};
