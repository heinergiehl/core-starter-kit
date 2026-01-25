<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->string('status')->default('draft')->after('is_published');
        });

        // Migrate existing data
        \Illuminate\Support\Facades\DB::table('blog_posts')->where('is_published', true)->update(['status' => 'published']);
        \Illuminate\Support\Facades\DB::table('blog_posts')->where('is_published', false)->update(['status' => 'draft']);

        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropColumn('is_published');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->boolean('is_published')->default(false)->after('status');
        });

        // Revert data
        \Illuminate\Support\Facades\DB::table('blog_posts')->where('status', 'published')->update(['is_published' => true]);
        \Illuminate\Support\Facades\DB::table('blog_posts')->where('status', '!=', 'published')->update(['is_published' => false]);

        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
