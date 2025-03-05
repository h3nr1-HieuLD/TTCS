<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deployments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('project_name');
            $table->string('repository_url');
            $table->string('branch')->default('main');
            $table->string('environment')->default('production');
            $table->string('domain')->nullable();
            $table->json('environment_variables')->nullable();
            $table->string('status')->default('pending');
            $table->text('build_logs')->nullable();
            $table->string('container_id')->nullable();
            $table->timestamp('deployed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'project_name']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deployments');
    }
};