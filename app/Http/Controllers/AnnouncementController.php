<?php

namespace App\Http\Controllers;

use App\Domain\Content\Models\Announcement;
use Illuminate\Http\JsonResponse;

class AnnouncementController extends Controller
{
    public function dismiss(Announcement $announcement): JsonResponse
    {
        $dismissed = session('dismissed_announcements', []);
        $dismissed[] = $announcement->id;
        session(['dismissed_announcements' => array_unique($dismissed)]);

        return response()->json(['success' => true]);
    }
}
