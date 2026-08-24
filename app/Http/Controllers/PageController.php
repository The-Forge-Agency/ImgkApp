<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class PageController extends Controller
{
    public function landing(): View
    {
        return view('landing');
    }

    public function studio(): View
    {
        return view('studio', ['presets' => config('imgk.presets')]);
    }

    public function docs(): View
    {
        return view('docs', ['presets' => config('imgk.presets')]);
    }
}
