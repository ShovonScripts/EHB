{{--
    A rich text editor with no third-party editor behind it.

    See App\Filament\Forms\Components\RichTextField for the reasoning. The
    short version: a contenteditable region, a toolbar, and a hidden input that
    carries the Livewire state.

    The whole widget is inside `wire:ignore` so a re-render caused by some other
    field cannot replace the region mid-sentence or move the caret. Livewire
    still reads the hidden input's value when it builds the next request, which
    is the only thing the mirror script is for.

    Two notes on the x-data block below, both learned the hard way:

    - It is raw HTML. Blade passes it through byte for byte, so the browser
      receives the JavaScript exactly as typed. The link guard's regex is
      therefore written `/^https?:\/\//`. An earlier version "escaped" it to
      `/^https?:\\/\\//`, which closes the regex literal early and turns the
      whole x-data object into a syntax error — silently, because the field
      still renders and still looks correct while `mirror()`, `exec()` and
      `onPaste()` are all undefined and the field saves an empty string
      forever. RichTextFieldTest runs the rendered block through `node --check`
      so that cannot come back.

    - Keep prose out of it. Every comment inside the attribute is shipped to the
      browser on every render, and it makes assertions against the rendered
      source ambiguous. Explanations belong in Blade comments like this one.
--}}
@php
    // Supplied by RichTextField::getExtraViewData(). Not @props: this is a
    // Filament field view, not a Blade component, so props are passed in as
    // view data rather than declared.
    $initialHtml = is_string($state ?? null) ? $state : '';
@endphp

<div class="w-full">
    <div
        wire:ignore
        x-data="{
            // Keep the hidden input in step with the region. The region is the
            // source of truth while editing; this only mirrors it.
            mirror() {
                const input = this.$refs.input;
                const html = this.$refs.editor.innerHTML;
                if (input && input.value !== html) {
                    input.value = html;
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                }
            },
            // Paste as plain text. Copying out of Word or Google Docs otherwise
            // drags in a page of inline styles and class names, which then get
            // stored and shipped to readers. A quality fix, not a security one
            // — the server sanitises regardless.
            onPaste(event) {
                event.preventDefault();
                const text = (event.clipboardData || window.clipboardData).getData('text/plain');
                document.execCommand('insertText', false, text);
                this.mirror();
            },
            exec(command, argument = null) {
                this.$refs.editor.focus();
                document.execCommand(command, false, argument);
                this.mirror();
            },
            link() {
                const url = window.prompt('Link URL (https://…)');
                if (!url) return;
                if (!/^https?:\/\//i.test(url)) {
                    window.alert('Links must start with http:// or https://');
                    return;
                }
                this.exec('createLink', url);
            },
        }"
        class="overflow-hidden rounded-lg border border-hairline bg-paper focus-within:border-accent"
    >
        {{-- Toolbar. type=button so it cannot submit a surrounding form. --}}
        <div class="flex flex-wrap items-center gap-1 border-b border-hairline bg-surface px-2 py-1.5" role="toolbar" aria-label="Formatting">
            @foreach($buttons as $button)
                <button
                    type="button"
                    x-on:click="exec(@js($button['command']), @js($button['argument']))"
                    x-on:mousedown.prevent
                    class="rounded px-2 py-1 text-xs font-medium text-ink transition-colors hover:bg-paper focus:bg-paper focus:outline-none"
                    title="{{ $button['label'] }}"
                >{{ $button['label'] }}</button>
            @endforeach

            <button
                type="button"
                x-on:click="link()"
                x-on:mousedown.prevent
                class="rounded px-2 py-1 text-xs font-medium text-ink transition-colors hover:bg-paper focus:bg-paper focus:outline-none"
                title="Link"
            >Link</button>

            <button
                type="button"
                x-on:click="exec('removeFormat')"
                x-on:mousedown.prevent
                class="rounded px-2 py-1 text-xs font-medium text-ink transition-colors hover:bg-paper focus:bg-paper focus:outline-none"
                title="Clear formatting"
            >Clear</button>
        </div>

        {{--
            The editing surface. Server-rendered with the current value so the
            text is present on first paint, before Alpine initialises.
        --}}
        <div
            x-ref="editor"
            x-on:input="mirror()"
            x-on:blur="mirror()"
            x-on:paste="onPaste($event)"
            contenteditable="true"
            role="textbox"
            aria-multiline="true"
            aria-label="{{ $getLabel() }}"
            class="{{ $editorHeight }} px-3 py-2 text-sm leading-relaxed text-ink focus:outline-none"
        >{!! $initialHtml !!}</div>
    </div>

    {{--
        The value Livewire actually submits.
    --}}
    <input
        type="hidden"
        x-ref="input"
        wire:model="{{ $getStatePath() }}"
        value="{{ $initialHtml }}"
    >
</div>
