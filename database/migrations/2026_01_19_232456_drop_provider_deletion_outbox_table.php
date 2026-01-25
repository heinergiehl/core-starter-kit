<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('provider_deletion_outbox');
    }

    /**
     * Reverse the migrations.
     *
     * Note: This table is no longer used with the provider-first architecture.
     * If you need to restore it, recreate with appropriate schema.
     */
    public function down(): void
    {
        // Intentionally left empty - table is deprecated
    }
};
