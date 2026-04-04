<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    public function index()
    {
        $banners = Banner::where('is_active', true)
            ->orderBy('position')
            ->get()
            ->map(fn($banner) => [
                'id' => $banner->id,
                'title' => $banner->title,
                'image' => $banner->image ? asset('storage/' . $banner->image) : null,
                'link' => $banner->link,
                'position' => $banner->position,
            ]);

        return response()->json([
            'success' => true,
            'banners' => $banners,
        ]);
    }
}
