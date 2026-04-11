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
        // Get all users with non-empty preferences (PostgreSQL JSON compatible)
        $usersWithPreferences = User::whereNotNull('preferences')
            ->get()
            ->filter(function ($user) {
                // Filter out empty arrays, null values, and empty strings
                return !empty($user->preferences)
                    && is_array($user->preferences)
                    && count($user->preferences) > 0
                    && isset($user->preferences['categories'])
                    && is_array($user->preferences['categories'])
                    && count($user->preferences['categories']) > 0;
            });

        // Count preferences
        $preferenceStats = [];
        foreach ($usersWithPreferences as $user) {
            foreach ($user->preferences['categories'] as $pref) {
                if (!isset($preferenceStats[$pref])) {
                    $preferenceStats[$pref] = ['count' => 0, 'users' => []];
                }
                $preferenceStats[$pref]['count']++;
                $preferenceStats[$pref]['users'][] = $user;
            }
        }

        // Sort by popularity
        arsort($preferenceStats);

        // Count users without preferences (PostgreSQL compatible)
        $totalUsers = User::count();
        $usersWithoutPreferences = $totalUsers - count($usersWithPreferences);

        return view('admin.preferences.index', compact('preferenceStats', 'usersWithPreferences', 'usersWithoutPreferences'));
    }
}
