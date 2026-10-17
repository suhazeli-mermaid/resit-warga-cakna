<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use Illuminate\Http\Request;

class PublicDonationController extends Controller
{
    /**
     * Halaman Utama Kempen
     */
    public function index()
    {
        $campaigns = Campaign::where('is_active', true)
            ->orWhereNull('is_active')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('public.index', compact('campaigns'));
    }

    /**
     * Paparan Butiran Kempen Sumbangan (Padanan Slug / HTTP 404)
     */
    public function show($slug)
    {
        $campaign = Campaign::where('slug', $slug)
            ->orWhere('id', $slug)
            ->firstOrFail();

        return view('public.show', compact('campaign'));
    }
}