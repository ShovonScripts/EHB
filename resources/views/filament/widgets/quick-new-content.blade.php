<x-filament-widgets::widget class="fi-wi-quick-new-content col-span-full">
    <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-muted">Quick Create Content</h2>
            <span class="text-xs text-muted">Select a content type to start drafting</span>
        </div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            <a
                href="{{ route('filament.admin.resources.content-items.create', ['content_type' => 'news']) }}"
                class="group flex items-center gap-3.5 rounded-lg border border-hairline bg-surface p-3.5 transition-all hover:border-accent hover:shadow-sm"
            >
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-accent transition-colors group-hover:bg-accent group-hover:text-white">
                    <x-heroicon-o-newspaper class="h-5 w-5" />
                </div>
                <div class="min-w-0">
                    <span class="block truncate text-sm font-semibold text-ink group-hover:text-accent">News</span>
                    <span class="block truncate text-xs text-muted">New article</span>
                </div>
            </a>

            <a
                href="{{ route('filament.admin.resources.content-items.create', ['content_type' => 'investigation']) }}"
                class="group flex items-center gap-3.5 rounded-lg border border-hairline bg-surface p-3.5 transition-all hover:border-accent hover:shadow-sm"
            >
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-accent transition-colors group-hover:bg-accent group-hover:text-white">
                    <x-heroicon-o-magnifying-glass-circle class="h-5 w-5" />
                </div>
                <div class="min-w-0">
                    <span class="block truncate text-sm font-semibold text-ink group-hover:text-accent">Investigation</span>
                    <span class="block truncate text-xs text-muted">Deep dive</span>
                </div>
            </a>

            <a
                href="{{ route('filament.admin.resources.content-items.create', ['content_type' => 'interview']) }}"
                class="group flex items-center gap-3.5 rounded-lg border border-hairline bg-surface p-3.5 transition-all hover:border-accent hover:shadow-sm"
            >
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-accent transition-colors group-hover:bg-accent group-hover:text-white">
                    <x-heroicon-o-chat-bubble-left-right class="h-5 w-5" />
                </div>
                <div class="min-w-0">
                    <span class="block truncate text-sm font-semibold text-ink group-hover:text-accent">Interview</span>
                    <span class="block truncate text-xs text-muted">Q&A conversation</span>
                </div>
            </a>

            <a
                href="{{ route('filament.admin.resources.content-items.create', ['content_type' => 'opinion']) }}"
                class="group flex items-center gap-3.5 rounded-lg border border-hairline bg-surface p-3.5 transition-all hover:border-accent hover:shadow-sm"
            >
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-accent transition-colors group-hover:bg-accent group-hover:text-white">
                    <x-heroicon-o-pencil-square class="h-5 w-5" />
                </div>
                <div class="min-w-0">
                    <span class="block truncate text-sm font-semibold text-ink group-hover:text-accent">Opinion</span>
                    <span class="block truncate text-xs text-muted">Analysis piece</span>
                </div>
            </a>

            <a
                href="{{ route('filament.admin.resources.content-items.create', ['content_type' => 'video']) }}"
                class="group flex items-center gap-3.5 rounded-lg border border-hairline bg-surface p-3.5 transition-all hover:border-accent hover:shadow-sm"
            >
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-accent transition-colors group-hover:bg-accent group-hover:text-white">
                    <x-heroicon-o-video-camera class="h-5 w-5" />
                </div>
                <div class="min-w-0">
                    <span class="block truncate text-sm font-semibold text-ink group-hover:text-accent">Video</span>
                    <span class="block truncate text-xs text-muted">Multimedia story</span>
                </div>
            </a>
        </div>
    </div>
</x-filament-widgets::widget>