<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $defaultLocale = (string) config('saas.locales.default', config('app.locale', 'en'));

        Schema::table('blog_posts', function (Blueprint $table) {
            $table->uuid('translation_group_uuid')->nullable()->after('id');
            $table->string('locale', 8)->nullable()->after('translation_group_uuid');
        });

        DB::table('blog_posts')
            ->select('id')
            ->orderBy('id')
            ->chunkById(100, function ($posts) use ($defaultLocale): void {
                foreach ($posts as $post) {
                    DB::table('blog_posts')
                        ->where('id', $post->id)
                        ->update([
                            'translation_group_uuid' => (string) Str::uuid(),
                            'locale' => $defaultLocale,
                        ]);
                }
            });

        Schema::table('blog_posts', function (Blueprint $table) {
            $table->uuid('translation_group_uuid')->nullable(false)->change();
            $table->string('locale', 8)->nullable(false)->change();
        });

        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropUnique('blog_posts_slug_unique');
            $table->unique(['locale', 'slug']);
            $table->unique(['translation_group_uuid', 'locale']);
            $table->index(['locale', 'status', 'published_at'], 'blog_posts_locale_status_published_at_index');
            $table->index('translation_group_uuid', 'blog_posts_translation_group_uuid_index');
        });
    }

    public function down(): void
    {
        $defaultLocale = (string) config('saas.locales.default', config('app.locale', 'en'));

        DB::table('blog_posts')
            ->where('locale', '!=', $defaultLocale)
            ->delete();

        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropUnique(['locale', 'slug']);
            $table->dropUnique(['translation_group_uuid', 'locale']);
            $table->dropIndex('blog_posts_locale_status_published_at_index');
            $table->dropIndex('blog_posts_translation_group_uuid_index');
            $table->unique('slug');
        });

        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropColumn([
                'translation_group_uuid',
                'locale',
            ]);
        });
    }
};
