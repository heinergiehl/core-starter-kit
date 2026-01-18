<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

echo "--- Queue Status ---\n";
echo "Pending Jobs: " . DB::table('jobs')->count() . "\n";

$failed = DB::table('failed_jobs')->get();
echo "Failed Jobs: " . $failed->count() . "\n";

foreach ($failed as $job) {
    echo "  - UUID: {$job->uuid}\n";
    echo "    Exception: " . Str::limit($job->exception, 200) . "\n";
    echo "--------------------------\n";
}
