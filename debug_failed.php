<?php

use Illuminate\Support\Facades\DB;

$failed = DB::table('failed_jobs')->orderBy('id', 'desc')->first();

if ($failed) {
    echo "=== Latest Failed Job ===\n";
    echo "UUID: " . ($failed->uuid ?? 'N/A') . "\n";
    echo "Failed At: " . ($failed->failed_at ?? 'N/A') . "\n";
    echo "\n=== Exception (first 3000 chars) ===\n";
    echo substr($failed->exception ?? 'No exception', 0, 3000) . "\n";
} else {
    echo "No failed jobs found.\n";
}
