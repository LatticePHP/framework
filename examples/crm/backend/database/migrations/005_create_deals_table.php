<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

return new class {
    public function up(): void
    {
        Capsule::schema()->create('deals', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->decimal('value', 15, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('stage')->default('lead');
            $table->integer('probability')->default(0);
            $table->date('expected_close_date')->nullable();
            $table->date('actual_close_date')->nullable();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->text('lost_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['workspace_id', 'stage']);
            $table->index(['workspace_id', 'owner_id']);
            $table->index(['workspace_id', 'contact_id']);
            $table->index(['workspace_id', 'company_id']);
        });
    }

    public function down(): void
    {
        Capsule::schema()->dropIfExists('deals');
    }
};
