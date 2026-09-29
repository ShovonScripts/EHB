<x-app-layout
    title="Contact"
    description="Get in touch — questions, tips, and collaboration inquiries."
    :breadcrumbs="[['label' => 'Contact']]"
>
    <div class="container-reading py-14">
        <p class="eyebrow">Contact</p>
        <h1 class="mt-3 text-4xl font-bold text-ink">Get in Touch</h1>

        {{-- CMS intro block — a Pages entry with slug "contact" (DATABASE.md §97) --}}
        @if($introPage?->body)
            <div class="article-body mt-4 text-lg leading-relaxed text-muted">
                {!! nl2br(e($introPage->body)) !!}
            </div>
        @endif

        @if($profile?->short_bio)
            <p class="mt-4 leading-relaxed text-muted">{{ $profile->short_bio }}</p>
        @endif

        @if($profile?->social_links && !empty($profile->social_links['email']))
            <p class="mt-3 text-sm text-muted">
                Prefer email? Reach out directly at
                <a href="mailto:{{ $profile->social_links['email'] }}" class="link-accent">{{ $profile->social_links['email'] }}</a>
            </p>
        @endif

        @if(session('status'))
            <div id="form-success"
                 class="form-success-enter mt-6 border border-accent/30 bg-surface px-5 py-4 text-sm text-ink"
                 role="status"
                 tabindex="-1"
                 aria-live="polite">
                {{ session('status') }}
            </div>
        @endif

        {{-- ContactForm — FRONTEND.md §2 /contact (honeypot + throttle per SECURITY.md §9) --}}
        <form method="POST" action="{{ route('contact.store') }}" class="mt-8 space-y-5">
            @csrf

            {{-- Honeypot — hidden from real users, bots tend to fill it --}}
            <div class="absolute -left-[9999px] top-auto h-px w-px overflow-hidden" aria-hidden="true">
                <label for="website">Website</label>
                <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
            </div>

            <div>
                <label for="name" class="mb-1 block text-sm font-medium text-ink">Name <span class="text-accent">*</span></label>
                <input type="text"
                       id="name"
                       name="name"
                       value="{{ old('name') }}"
                       required
                       maxlength="255"
                       class="w-full border border-hairline bg-surface px-4 py-3 text-ink placeholder:text-muted focus:border-accent"
                       placeholder="Your name">
                @error('name')
                    <p class="mt-1 text-sm text-accent">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="email" class="mb-1 block text-sm font-medium text-ink">Email <span class="text-accent">*</span></label>
                <input type="email"
                       id="email"
                       name="email"
                       value="{{ old('email') }}"
                       required
                       maxlength="255"
                       class="w-full border border-hairline bg-surface px-4 py-3 text-ink placeholder:text-muted focus:border-accent"
                       placeholder="you@example.com">
                @error('email')
                    <p class="mt-1 text-sm text-accent">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="subject" class="mb-1 block text-sm font-medium text-ink">Subject</label>
                <input type="text"
                       id="subject"
                       name="subject"
                       value="{{ old('subject') }}"
                       maxlength="255"
                       class="w-full border border-hairline bg-surface px-4 py-3 text-ink placeholder:text-muted focus:border-accent"
                       placeholder="What is this about? (optional)">
                @error('subject')
                    <p class="mt-1 text-sm text-accent">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="message" class="mb-1 block text-sm font-medium text-ink">Message <span class="text-accent">*</span></label>
                <textarea id="message"
                          name="message"
                          rows="7"
                          required
                          maxlength="5000"
                          class="w-full border border-hairline bg-surface px-4 py-3 text-ink placeholder:text-muted focus:border-accent"
                          placeholder="Write your message…">{{ old('message') }}</textarea>
                @error('message')
                    <p class="mt-1 text-sm text-accent">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="btn-primary">Send Message</button>
        </form>

        @if($profile?->social_links)
            <div class="mt-10 flex flex-wrap gap-4 border-t border-hairline pt-6 text-sm">
                @foreach(['twitter' => 'X / Twitter', 'linkedin' => 'LinkedIn', 'facebook' => 'Facebook'] as $key => $label)
                    @if(!empty($profile->social_links[$key]))
                        <a href="{{ $profile->social_links[$key] }}" rel="noopener noreferrer" target="_blank" class="link-accent">{{ $label }}</a>
                    @endif
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
