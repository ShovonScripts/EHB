<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\JournalistProfile;
use App\Models\Page;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function create()
    {
        $profile = JournalistProfile::current();

        // A CMS page keyed "contact" becomes the editorially-owned intro text
        // above the form (DATABASE.md §97).
        $introPage = Page::where('slug', 'contact')->first();

        return view('contact', compact('profile', 'introPage'));
    }

    public function store(Request $request)
    {
        // Honeypot (SECURITY.md §9): real users never see/fill this field.
        if ($request->filled('website')) {
            return redirect()->route('contact.create')
                ->with('status', 'Thank you — your message has been sent.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        Contact::create($validated);

        return redirect()
            ->route('contact.create')
            ->with('status', 'Thank you — your message has been sent.');
    }
}
