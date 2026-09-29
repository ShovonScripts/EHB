<?php

namespace App\Filament\Resources\Settings\Tables;

use App\Filament\Resources\Settings\Schemas\SettingForm;
use App\Models\Setting;
use App\Support\SeoSettings;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SettingsTable
{
    /**
     * The list *is* the settings screen.
     *
     * Previously this showed `key` / `value` / `updated_at` — raw snake_case
     * keys, no hint what anything was for, and an edit action that navigated
     * away to a two-field form. With a dozen settings that meant a dozen round
     * trips to review the lot.
     *
     * Four things make it the screen rather than a table of rows:
     *
     * - Rows carry the preset's label and group, so the list reads as
     *   "Social sharing · Default share image" rather than
     *   "default_og_image_media_id". A key the preset does not know keeps its
     *   raw name, so a legacy row is visible rather than silently mixed in
     *   with the real ones.
     * - Editing happens in a modal — and the modal is built from the row's key,
     *   so it holds the *same* control the standalone edit page does: an image
     *   picker for the share image, a toggle for the indexable switch, a
     *   labelled field carrying the preset's help text and its rules.
     *   Previously the modal fell back to the resource's key/value form, so
     *   every setting on the screen the user actually works from was a bare
     *   text box.
     * - The whole set is on one screen: pagination is off (see below) and the
     *   rows are in the preset's declared order, so the list reads group by
     *   group.
     * - A Group filter narrows the list to one group — how someone finds the
     *   Twitter handle without reading eleven rows.
     *
     * Every column reads a real attribute — `display_label`, `setting_group`,
     * `display_value`, `is_supported` are accessors on the model, not closures
     * here. A column named after something the model does not have, combined
     * with a `state()` closure, renders until the PHP process dies with a
     * stack overflow; see the note on Setting's accessors.
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('display_label')
                    ->label('Setting')
                    ->searchable(['key'])
                    ->sortable(['key'])
                    ->weight('medium')
                    ->wrap(),

                // Not sortable. A `sortable(['key'])` badge sorted by the raw
                // key, which is not group order in any sense — clicking it
                // shuffled "Default share image" in among the contact and
                // robots rows. The default order is the declared group order,
                // which is the only group ordering that means anything here.
                TextColumn::make('setting_group')
                    ->label('Group')
                    ->badge(),

                TextColumn::make('display_value')
                    ->label('Value')
                    ->placeholder('—')
                    ->wrap()
                    ->limit(70),

                // A retired row is inert by design, not broken. The icon is the
                // quickest way to see which rows do nothing without opening
                // each one.
                IconColumn::make('is_supported')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),
            ])
            // One row per declared setting, and all of them on one screen. The
            // table paginated at ten a page, which put the eleventh setting —
            // "X / Twitter handle" — on page two. A settings screen that hides
            // a setting behind a page control is the same failure as a setting
            // with no field at all: you cannot tell it is there.
            ->paginated(false)
            ->defaultSort(fn (Builder $query): Builder => self::orderByPreset($query))
            ->filters([
                SelectFilter::make('setting_group')
                    ->label('Group')
                    ->options(self::groupOptions())
                    // `setting_group` is a preset accessor, not a column, so
                    // this resolves the group's keys and filters on those
                    // rather than asking the database for a column it does not
                    // have.
                    ->query(function (Builder $query, array $data): Builder {
                        $group = $data['value'] ?? null;

                        if (blank($group)) {
                            return $query;
                        }

                        if ($group === SeoSettings::GROUP_UNKNOWN) {
                            return $query->whereNotIn('key', self::presetKeys());
                        }

                        return $query->whereIn('key', array_column(SeoSettings::group($group), 'key'));
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->modalWidth('2xl')
                    // The modal edits one row, so it is built from that row's
                    // key — the same call `EditSetting` makes. Without this the
                    // action fell back to the resource's form, which has no
                    // record and therefore could only offer a raw Value box.
                    ->schema(fn (Setting $record, Schema $schema): Schema => SettingForm::configure($schema, $record->key)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Order rows the way `SeoSettings::preset()` declares them.
     *
     * The preset documents its order as "the order they should appear in the
     * panel", and its groups run identity → social → cards → search → contact.
     * Sorting
     * by `key` instead put "Default share image" between "Contact email" and
     * "Extra robots.txt rules", so the Group badge — the only thing on screen
     * saying what a row is for — appeared in a different order on every row,
     * and the five groups the preset declares were not visible as groups at
     * all.
     *
     * A row the preset does not know sorts last: it is an anomaly, and the end
     * of the list is where an anomaly shows rather than being interleaved with
     * the live settings.
     *
     * The CASE expression is built with bindings and the connection's own
     * identifier grammar so it runs unchanged on SQLite (which quotes
     * identifiers with double quotes) and MySQL (where `key` is a reserved word
     * and needs backticks).
     */
    private static function orderByPreset(Builder $query): Builder
    {
        $keys = self::presetKeys();

        if ($keys === []) {
            return $query;
        }

        $cases = [];
        $bindings = [];

        foreach ($keys as $position => $key) {
            $cases[] = 'WHEN ? THEN ?';
            $bindings[] = $key;
            $bindings[] = $position;
        }

        $bindings[] = count($keys);

        return $query->orderByRaw(
            'CASE '.$query->getQuery()->getGrammar()->wrap('key').' '.implode(' ', $cases).' ELSE ? END',
            $bindings
        );
    }

    /**
     * The Group filter's options: every declared group in declaration order,
     * plus one bucket for the rows the preset does not know.
     *
     * @return array<string, string>
     */
    private static function groupOptions(): array
    {
        $options = [];

        foreach (SeoSettings::groups() as $group) {
            $options[$group] = $group;
        }

        $options[SeoSettings::GROUP_UNKNOWN] = SeoSettings::GROUP_UNKNOWN;

        return $options;
    }

    /**
     * Every key the preset declares, in declaration order.
     *
     * @return array<int, string>
     */
    private static function presetKeys(): array
    {
        return array_column(SeoSettings::preset(), 'key');
    }
}
