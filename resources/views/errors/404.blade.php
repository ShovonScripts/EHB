@php($profile = \App\Models\JournalistProfile::current())

<x-app-layout
    title="Page Not Found"
>
    <div class="py-16 text-center">
        <div class="container-reading">
            <p class="eyebrow">Page not found</p>
            <h1 class="mt-3 text-4xl font-bold text-ink">404</h1>
            <p class="mt-4 text-muted">
                The page you're looking for doesn't exist or may have been moved.
                Try the full archive or search instead.
            </p>
            <div class="mt-8 flex flex-wrap justify-center gap-4">
                <a href="{{ route('archive') }}" class="btn-outline">Browse Archive</a>
                <a href="{{ route('home') }}" class="btn-primary">Back to Home</a>
            </div>
        </div>
    </div>
</x-app-layout>
