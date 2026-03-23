<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('public_author_name')->nullable()->after('name');
            $table->string('public_author_title')->nullable()->after('public_author_name');
            $table->string('public_author_avatar_path')->nullable()->after('public_author_title');
            $table->text('public_author_bio')->nullable()->after('public_author_avatar_path');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'public_author_name',
                'public_author_title',
                'public_author_avatar_path',
                'public_author_bio',
            ]);
        });
    }
};
