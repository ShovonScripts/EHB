<?php

namespace App\Filament\Resources\Settings\Schemas;

use App\Filament\Forms\Components\MediaFileUpload;
use App\Filament\Resources\Settings\Tables\SettingsTable;
use App\Support\SeoSettings;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

/**
 * The form for a single setting.
 *
 * The form is bound to one `settings` row, so it edits one key/value pair — but
 * the control, label, help text and validation all come from the preset
 * (`App\Support\SeoSettings`) rather than being a bare "Value" text box.
 *
 * That is the whole point. The old form had two inputs called `key` and
 * `value` and nothing else, so setting the share image meant knowing a Media
 * id and typing it in. It was mistyped once during development, and because
 * `SiteSettings::defaultOgImageUrl()` resolved the bad id to null, every
 * social card on the site silently lost its image with nothing in the panel
 * saying why. A labelled image picker removes the possibility.
 *
 * This form is built for *one* record's key, and the record is not always
 * available where the form is registered: the resource's static `form()` has
 * no record, so `EditSetting::form()` and the list's edit action both call
 * `configure()` with the key they know. Passing null — a resource-level form,
 * or a row whose key is not in the preset — falls back to the generic
 * key/value editor, so a legacy or hand-added row is still editable rather
 * than becoming unreachable.
 *
 * @see SettingsTable
 *   The list page's edit modal, which builds this form from the row's key.
 */
class SettingForm
{
    public static function configure(Schema $schema, ?string $key = null): Schema
    {
        $definition = $key !== null ? SeoSettings::definition($key) : null;

        if ($definition === null) {
            return $schema->components(self::genericFields());
        }

        return $schema
            ->components([
                TextInput::make('key')
                    ->label('Setting key')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->helperText('The name this value is stored under. Renaming it orphans the current value and creates a new, empty setting.'),

                self::control($definition),
            ]);
    }

    /**
     * Fallback for a key the preset does not declare.
     *
     * Deliberately the old two-field editor: a row can exist that the preset
     * has never heard of (added by a migration, left over from a removed
     * feature), and it must stay editable. Showing a raw Value box is a much
     * smaller problem than a row you cannot open.
     *
     * No validation is attached here on purpose. The preset does not declare
     * what shape an unknown value has, so any rule invented for it (a length
     * cap, say) could refuse to save a legacy row whose current contents are
     * legitimate. A row with a declared type is validated below; one without
     * is left alone.
     *
     * @return array<int, mixed>
     */
    private static function genericFields(): array
    {
        return [
            TextInput::make('key')
                ->label('Setting key')
                ->required()
                ->unique(ignoreRecord: true),

            Textarea::make('value')
                ->label('Value')
                ->rows(3)
                ->columnSpanFull(),
        ];
    }

    /**
     * The control for one preset entry, with the rules its type implies.
     *
     * The rules are attached to the field rather than to the page that hosts
     * it. `EditSetting` and the list's edit modal both render this control, and
     * a rule owned by one of them was invisible to the other: the modal happily
     * accepted a 300-character contact email the page would have refused, and
     * the modal had *no* rules at all for every setting. A rule that travels
     * with its control cannot be left behind like that.
     *
     * `Field::rules()` appends, so the upload field keeps the validation
     * `SecureFileUpload` gives it (type sniffing, size, dimensions) and only
     * gains the preset's `nullable`.
     */
    private static function control(array $definition): mixed
    {
        $label = $definition['label'];
        $help = $definition['help'];

        $field = match ($definition['type']) {
            'image' => self::imageControl($definition),

            'textarea' => Textarea::make('value')
                ->label($label)
                ->rows(3)
                ->columnSpanFull()
                ->helperText($help),

            'toggle' => Toggle::make('value')
                ->label($label)
                ->helperText($help)
                // With nothing in the column yet the control must read as the
                // preset's default, or a fresh install would report itself
                // unindexable.
                ->dehydrateStateUsing(fn (mixed $state): bool => (bool) ($state ?? $definition['default'])),

            'handle' => TextInput::make('value')
                ->label($label)
                ->placeholder('@yourhandle')
                ->helperText($help.' A pasted profile URL is accepted and trimmed on save.'),

            'email' => TextInput::make('value')
                ->label($label)
                ->email()
                ->placeholder('name@example.com')
                ->helperText($help),

            'retired' => TextInput::make('value')
                ->label($label)
                ->helperText($help)
                ->disabled()
                ->dehydrated(false)
                ->placeholder('Not supported on this site'),

            // 'text', plus anything unknown — SeoSettingsTest asserts the type
            // list so an unrecognised type fails a test rather than quietly
            // rendering a text box for something that is not text.
            default => TextInput::make('value')
                ->label($label)
                ->helperText($help),
        };

        return $field->rules(self::rulesFor($definition['key']));
    }

    /**
     * The upload control for an image setting.
     *
     * The folder and crop ratio are per-setting rather than hardcoded, because
     * two image settings are used at two different shapes:
     *
     * - The share image stays in `settings/og`, where every file uploaded
     *   before this split already lives — moving the folder would change where
     *   new uploads land without moving anything old, gaining nothing.
     * - The card thumbnail goes to `settings/cards` and offers the 4:3 ratio
     *   (DESIGN_SYSTEM.md §7) in the crop tool, so the image arrives at the
     *   shape the grid crops it to instead of being cut off after upload.
     */
    private static function imageControl(array $definition): MediaFileUpload
    {
        $isCardImage = $definition['key'] === 'default_card_image_media_id';

        $control = MediaFileUpload::make('value')
            ->label($definition['label'])
            ->image()
            ->imageEditor()
            ->disk('public')
            ->directory($isCardImage ? 'settings/cards' : 'settings/og')
            ->columnSpanFull()
            ->helperText($definition['help']);

        // Ratios only when this setting pins one — the setter takes no null,
        // so the share image keeps the editor's defaults untouched and the
        // card gets 4:3 first with "free" still selectable behind it.
        return $isCardImage ? $control->imageEditorAspectRatioOptions(['4:3', null]) : $control;
    }

    /**
     * Validation rules for a key, from its declared type.
     *
     * Attached to the generated control by `control()`, so the same rules apply
     * wherever the control appears — the edit page and the list's edit modal.
     * A retired setting gets none: it is not written, so it must not be
     * validated either, and the column may hold anything the row has always
     * held.
     *
     * @return array<int, string>
     */
    public static function rulesFor(?string $key): array
    {
        return match (SeoSettings::typeOf((string) $key)) {
            'textarea' => ['nullable', 'string', 'max:2000'],
            'email' => ['nullable', 'string', 'max:255', 'email'],
            'handle' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable'],
            'toggle' => ['nullable', 'boolean'],
            'retired' => [],
            default => ['nullable', 'string', 'max:'.(Str::startsWith((string) $key, 'site_') ? 255 : 500)],
        };
    }
}
