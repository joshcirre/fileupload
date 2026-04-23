<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shares', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('disk');
            $table->string('path');
            $table->unsignedBigInteger('size');
            $table->string('mime_type')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->boolean('delete_after_first_download')->default(false);
            $table->timestamp('first_downloaded_at')->nullable();
            $table->unsignedInteger('download_count')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }
};
