<?php

namespace App\Http\Controllers;

use App\Models\JournalistProfile;
use App\Models\Page;

class AboutController extends Controller
{
    public function __invoke()
    {
        $profile = JournalistProfile::current();
        $profile?->load([
            'photo',
            'careerHistory',
            'education',
            'awards',
            'publications.logo',
        ]);

        abort_unless($profile, 404);

        // A CMS page keyed "about" becomes the editorially-owned intro block
        // above the structured bio (DATABASE.md §97).
        $introPage = Page::where('slug', 'about')->first();

        return view('about', compact('profile', 'introPage'));
    }
}
