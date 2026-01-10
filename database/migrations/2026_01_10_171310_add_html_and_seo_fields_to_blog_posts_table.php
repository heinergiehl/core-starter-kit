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
            $table->longText('body_html')->nullable()->after('body_markdown');
            $table->string('featured_image')->nullable()->after('body_html');
            $table->string('meta_title')->nullable()->after('featured_image');
            $table->text('meta_description')->nullable()->after('meta_title');
            $table->integer('reading_time')->nullable()->after('meta_description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropColumn([
                'body_html',
                'featured_image',
                'meta_title',
                'meta_description',
                'reading_time',
            ]);
        });
    }
};
