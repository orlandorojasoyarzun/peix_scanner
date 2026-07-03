<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class ScanController extends Controller
{
    public function home(): View
    {
        return view('pages.home');
    }

    public function create(): View
    {
        return view('pages.scan');
    }

    public function store(Request $request)
    {
        return response()->json(['ok' => true]);
    }

    public function confirm(string $scan): View
    {
        return view('pages.confirm', ['scan' => $scan]);
    }

    public function confirmStore(Request $request, string $scan)
    {
        return response()->json(['ok' => true]);
    }

    public function show(string $species): View
    {
        return view('pages.species', ['species' => $species]);
    }
}
