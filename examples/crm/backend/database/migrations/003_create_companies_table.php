<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

return new class {
    public function up(): void
    {
        Capsule::schema()->create('companies', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('domain')->nullable();
            $table->string('industry')->nullable();
            $table->string('size')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('website')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['workspace_id', 'industry']);
            $table->index(['workspace_id', 'name']);
        });
    }

    public function down(): void
    {
        Capsule::schema()->dropIfExists('companies');
    }
};
