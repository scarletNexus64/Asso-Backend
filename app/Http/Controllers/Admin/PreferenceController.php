<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;

class PreferenceController extends Controller
{
    /**
     * Display user preferences statistics
     */
    public function index()
    {
        // Get all users with preferences
        $usersWithPreferences = User::whereNotNull('preferences')
            ->where('preferences', 'NOT LIKE', '[]')
            ->where('preferences', 'NOT LIKE', 'null')
            ->where('preferences', '!=', '')
            ->get();

        // Count preferences
        $preferenceStats = [];
        foreach ($usersWithPreferences as $user) {
            if (is_array($user->preferences) && isset($user->preferences['categories'])) {
                foreach ($user->preferences['categories'] as $pref) {
                    if (!isset($preferenceStats[$pref])) {
                        $preferenceStats[$pref] = ['count' => 0, 'users' => []];
                    }
                    $preferenceStats[$pref]['count']++;
                    $preferenceStats[$pref]['users'][] = $user;
                }
            }
        }

        // Sort by popularity
        arsort($preferenceStats);

        return view('admin.preferences.index', compact('preferenceStats', 'usersWithPreferences'));
    }
}
