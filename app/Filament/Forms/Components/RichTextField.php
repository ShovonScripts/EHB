<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Field;

/**
 * A rich text editor built for this application, with no editor dependency.
 *
 * ## Why this exists instead of Filament's RichEditor
 *
 * Filament's `RichEditor` is a wrapper around a third-party WYSIWYG (TipTap,
 * with its own ProseMirror dependency tree). That is a large amount of
 * third-party JavaScript running inside the admin panel — the one part of this
 * application that is not published to the web but is still reachable by anyone
 * who can log in, and the part where a supply-chain compromise would have the
 * most room to work. This field is a contenteditable region, a toolbar, and a
 * hidden input, and it pulls in nothing.
 *
 * ## How it talks to Livewire
 *
 * The contenteditable region is *not* bound to the form state. A hidden
 * `<input>` carries `wire:model`, and a small script mirrors the region's HTML
 * into it on every `input` event. Two reasons:
 *
 * 1. Livewire can only serialise form controls. A contenteditable `div` is
 *    invisible to it, so without the mirror the field would always save empty.
 * 2. The mirror means Livewire sees an ordinary string. No custom entanglement,
 *    no component-side state to keep in sync.
 *
 * The whole widget sits inside `wire:ignore`, which is the same escape hatch
 * Filament's own FilePond uses: the DOM-diff step is told to leave it alone so
 * a re-render triggered by some *other* field cannot wipe half-typed text or
 * move the caret. That does not stop Livewire reading the input's value when it
 * builds the next request, which is the only thing the mirror is for.
 *
 * ## Security
 *
 * Nothing here is a security boundary, and it must not be mistaken for one. The
 * browser can be told to do anything, and the client-side paste handler below
 * strips formatting as a matter of output quality, not safety. The actual
 * guarantee is server-side: whatever arrives is passed through
 * `HtmlSanitizer::sanitize()` by the model on save, exactly as the main article
 * body is. A `<script>` pasted into this field does not survive the round trip.
 */
class RichTextField extends Field
{
    protected string $view = 'filament.forms.components.rich-text-field';

    /** Tailwind height class for the editing region. */
    protected string $editorHeight = 'min-h-[22rem]';

    /**
     * Block-level formats offered in the toolbar.
     *
     * Deliberately short. A journalist writing a story needs emphasis,
     * headings, lists, quotes and links — not a full word processor — and
     * every extra button is another thing to get right and to sanitise.
     *
     * @var array<string, array{0: string, 1: string, 2: string}> label, execCommand, value
     */
    protected array $blockButtons = [
        ['Bold', 'bold', ''],
        ['Italic', 'italic', ''],
        ['Underline', 'underline', ''],
        ['H2', 'formatBlock', 'h2'],
        ['H3', 'formatBlock', 'h3'],
        ['Paragraph', 'formatBlock', 'p'],
        ['Quote', 'formatBlock', 'blockquote'],
        ['Bulleted', 'insertUnorderedList', ''],
        ['Numbered', 'insertOrderedList', ''],
    ];

    /**
     * @param  array<int, array{0: string, 1: string, 2?: string}>  $buttons
     */
    public function toolbarButtons(array $buttons): static
    {
        $this->blockButtons = $buttons;

        return $this;
    }

    /**
     * How tall the editing region is.
     *
     * A writing surface needs room. 10rem fits a toolbar and a couple of lines
     * and leaves the rest of a long form above the fold, which is wrong for a
     * field whose entire purpose is holding a body of text — you end up
     * scrolling inside a small box while the page around it has space to
     * spare. 22rem is roughly a screenful of prose, so a journalist can see a
     * paragraph and a half without the region scrolling.
     */
    public function editorHeight(string $height): static
    {
        $this->editorHeight = $height;

        return $this;
    }

    public function getEditorHeight(): string
    {
        return $this->editorHeight;
    }

    /**
     * Toolbar entries for the view, with the `execCommand` argument the
     * browser needs already resolved.
     *
     * @return array<int, array{label: string, command: string, value: string, argument: ?string}>
     */
    public function getToolbarButtons(): array
    {
        return array_map(function (array $button): array {
            [$label, $command, $value] = [$button[0], $button[1], $button[2] ?? ''];

            return [
                'label' => $label,
                'command' => $command,
                'value' => $value,
                // formatBlock takes the tag as its argument; every other
                // command takes none.
                'argument' => $command === 'formatBlock' ? $value : null,
            ];
        }, $this->blockButtons);
    }

    public function isLiveOnBlur(): bool
    {
        return false;
    }

    /**
     * Data handed to the view.
     *
     * Filament passes a field view the component's public methods (as
     * `$getLabel()`, `$getStatePath()`, …) plus whatever this returns, so
     * `buttons` and `state` land in the view as plain variables.
     *
     * @return array<string, mixed>
     */
    public function getExtraViewData(): array
    {
        return [
            'buttons' => $this->getToolbarButtons(),
            'state' => $this->getState(),
            'editorHeight' => $this->getEditorHeight(),
        ];
    }
}
