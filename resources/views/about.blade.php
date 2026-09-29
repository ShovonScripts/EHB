@php
    $jsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'Person',
        'name' => $profile->name,
        'jobTitle' => $profile->title,
        'url' => route('about'),
    ];
    if ($profile->long_bio) {
        $jsonLd['description'] = $profile->long_bio;
    }
    if ($profile->social_links) {
        $sameAs = array_values(array_filter([
            $profile->social_links['twitter'] ?? null,
            $profile->social_links['linkedin'] ?? null,
            $profile->social_links['facebook'] ?? null,
        ]));
        if ($sameAs) {
            $jsonLd['sameAs'] = $sameAs;
        }
    }
@endphp

<x-app-layout
    :title="'About '.$profile->name"
    :description="\Illuminate\Support\Str::limit($profile->short_bio ?? 'Biography, career history, education, and awards.', 155)"
    :json-ld="$jsonLd"
    :breadcrumbs="[['label' => 'About']]"
>
    <div class="container-reading py-14">
        <p class="eyebrow">About</p>
        <h1 class="mt-3 text-4xl font-bold text-ink">{{ $profile->name }}</h1>
        <p class="mt-2 text-lg text-muted">{{ $profile->title }}</p>

        {{-- CMS intro block — a Pages entry with slug "about" (DATABASE.md §97) --}}
        @if($introPage?->body)
            <div class="article-body mt-6 text-lg leading-relaxed text-muted">
                {!! nl2br(e($introPage->body)) !!}
            </div>
        @endif

        {{-- Social / professional links — FR-307 --}}
        @if($profile->social_links)
            <div class="mt-4 flex flex-wrap gap-4 text-sm">
                @foreach(['twitter' => 'X / Twitter', 'linkedin' => 'LinkedIn', 'facebook' => 'Facebook'] as $key => $label)
                    @if(!empty($profile->social_links[$key]))
                        <a href="{{ $profile->social_links[$key] }}" rel="noopener noreferrer" target="_blank" class="link-accent">{{ $label }}</a>
                    @endif
                @endforeach
                @if(!empty($profile->social_links['email']))
                    <a href="mailto:{{ $profile->social_links['email'] }}" class="link-accent">Email</a>
                @endif
            </div>
        @endif

        @if($profile->photo)
            <img src="{{ $profile->photo->url }}"
                 alt="Portrait of {{ $profile->name }}"
                 class="mt-8 aspect-[4/5] w-full max-w-sm border border-hairline object-cover"
                 width="400" height="500">
        @endif

        @if($profile->long_bio)
            <div class="mt-10 article-body">
                {!! nl2br(e($profile->long_bio)) !!}
            </div>
        @elseif($profile->short_bio)
            <p class="mt-8 leading-relaxed text-muted">{{ $profile->short_bio }}</p>
        @endif

        {{-- Skills / beats — FR-306 --}}
        @if($profile->skills)
            <section class="mt-12">
                <h2 class="text-2xl font-bold text-ink">Beats &amp; Expertise</h2>
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach($profile->skills as $skill)
                        <span class="border border-hairline bg-surface px-3 py-1.5 text-sm text-muted">{{ $skill }}</span>
                    @endforeach
                </div>
            </section>
        @endif
    </div>

    {{-- Career history — FR-302 --}}
    @if($profile->careerHistory->isNotEmpty())
        <section class="border-y border-hairline bg-surface">
            <div class="container-reading py-12">
                <h2 class="text-2xl font-bold text-ink">Career</h2>
                <ol class="mt-6 border-l border-hairline">
                    @foreach($profile->careerHistory as $entry)
                        <li class="relative pb-8 pl-6 last:pb-0">
                            <span class="absolute top-1.5 -left-[5px] h-2.5 w-2.5 rounded-full bg-accent"></span>
                            <p class="font-serif text-lg font-bold text-ink">{{ $entry->role }}</p>
                            <p class="text-sm font-medium text-accent">{{ $entry->organization }}</p>
                            <p class="mt-1 text-xs text-muted">
                                {{ $entry->start_date?->format('M Y') ?? '?' }}
                                –
                                {{ $entry->end_date ? $entry->end_date->format('M Y') : 'Present' }}
                            </p>
                            @if($entry->description)
                                <p class="mt-2 text-sm leading-relaxed text-muted">{{ $entry->description }}</p>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>
    @endif

    {{-- Education — FR-303 --}}
    @if($profile->education->isNotEmpty())
        <section class="container-reading py-12">
            <h2 class="text-2xl font-bold text-ink">Education</h2>
            <ul class="mt-6 space-y-5">
                @foreach($profile->education as $entry)
                    <li class="border border-hairline bg-surface p-5">
                        <p class="font-serif text-lg font-bold text-ink">{{ $entry->institution }}</p>
                        <p class="text-sm text-accent">{{ $entry->program }}</p>
                        <p class="mt-1 text-xs text-muted">
                            {{ $entry->start_date?->format('M Y') ?? '?' }}
                            –
                            {{ $entry->end_date ? $entry->end_date->format('M Y') : 'Present' }}
                        </p>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- Awards — FR-116 / FR-304 --}}
    @if($profile->awards->isNotEmpty())
        <section class="border-y border-hairline bg-surface">
            <div class="container-reading py-12">
                <h2 class="text-2xl font-bold text-ink">Awards &amp; Recognition</h2>
                <ul class="mt-6 space-y-5">
                    @foreach($profile->awards as $award)
                        <li class="border border-hairline bg-paper p-5">
                            <div class="flex items-baseline justify-between gap-4">
                                <p class="font-serif text-lg font-bold text-ink">{{ $award->title }}</p>
                                <span class="shrink-0 text-sm font-semibold text-accent">{{ $award->year }}</span>
                            </div>
                            <p class="mt-1 text-sm text-muted">{{ $award->awarding_body }}</p>
                            @if($award->description)
                                <p class="mt-2 text-sm leading-relaxed text-muted">{{ $award->description }}</p>
                            @endif
                            @if($award->url)
                                <a href="{{ $award->url }}" rel="noopener noreferrer" target="_blank" class="link-accent mt-2 inline-block text-sm">Learn more →</a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    {{-- Publications he's written for — FR-305 --}}
    @if($profile->publications->isNotEmpty())
        <section class="container-reading py-12">
            <h2 class="text-2xl font-bold text-ink">Published In</h2>
            <div class="mt-5 flex flex-wrap gap-3">
                @foreach($profile->publications as $pub)
                    <a href="{{ route('publications.show', $pub->slug) }}"
                       class="filter-chip">{{ $pub->name }}</a>
                @endforeach
            </div>
        </section>
    @endif
</x-app-layout>
