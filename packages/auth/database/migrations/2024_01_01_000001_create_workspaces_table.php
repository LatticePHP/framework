<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedBigInteger('owner_id');
            $table->json('settings')->nullable();
            $table->string('logo_url', 2048)->nullable();
            $table->timestamps();

            $table->foreign('owner_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->index('owner_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspaces');
    }
};
