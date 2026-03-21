<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->string('content_source', 32)->nullable()->after('locale');
            $table->string('content_source_key')->nullable()->after('content_source');
            $table->string('content_source_path')->nullable()->after('content_source_key');
            $table->string('content_source_hash', 64)->nullable()->after('content_source_path');
            $table->timestamp('content_source_synced_at')->nullable()->after('content_source_hash');

            $table->unique('content_source_path');
            $table->index(['content_source', 'content_source_key'], 'blog_posts_content_source_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropUnique(['content_source_path']);
            $table->dropIndex('blog_posts_content_source_lookup_index');
            $table->dropColumn([
                'content_source',
                'content_source_key',
                'content_source_path',
                'content_source_hash',
                'content_source_synced_at',
            ]);
        });
    }
};
