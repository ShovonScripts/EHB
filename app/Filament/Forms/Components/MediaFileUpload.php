<?php

namespace App\Filament\Forms\Components;

use App\Models\Media;
use Filament\Forms\Components\FileUpload;
use Illuminate\Support\Facades\Auth;

/**
 * A hardened file upload that stores a Media library row reference in an
 * integer *_media_id foreign-key column instead of writing the raw file path
 * into the column (which would fail on an integer FK).
 *
 * - On hydrate: media id  -> file path (so FileUpload can preview it).
 * - On dehydrate: file path -> media id (reusing an existing Media row
 *   for the same path so re-saving the form does not duplicate rows).
 *
 * Extends SecureFileUpload, so every image field in the admin (featured
 * image, OG image, profile photo, publication logo, topic image, award
 * certificate) gets the SECURITY.md §10–§11 file validation, EXIF stripping,
 * and randomized filenames without each form having to opt in.
 *
 * ADMIN_PANEL.md §7 — alt text is required for all published images.
 * The component accepts an optional `altTextField()` to specify a sibling
 * form field that holds the alt text. When provided, the alt text is
 * validated as required on publish and stored with the Media record.
 */
class MediaFileUpload extends SecureFileUpload
{
    protected ?string $altTextField = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->afterStateHydrated(function (FileUpload $component, $state, callable $set) {
                if ($state === null || $state === '' || ! is_numeric($state)) {
                    return;
                }

                $media = Media::find($state);

                $set($this->getName(), $media?->file_path);

                // Also hydrate the alt_text field if configured
                if ($this->altTextField && $media) {
                    $set($this->altTextField, $media->alt_text);
                }
            })
            ->mutateDehydratedStateUsing(function ($state, callable $get) {
                if ($state === null || $state === '') {
                    return null;
                }

                // State was hydrated from (or already is) a media id.
                if (is_numeric($state)) {
                    // If alt_text field is configured and has a value, update the Media record
                    if ($this->altTextField && $altText = $get($this->altTextField)) {
                        Media::where('id', (int) $state)->update(['alt_text' => $altText]);
                    }

                    return (int) $state;
                }

                // A (new or previously uploaded) storage path: attach it
                // to a Media row and store that row's id in the FK column.
                $existing = Media::query()
                    ->where('disk', 'public')
                    ->where('file_path', $state)
                    ->first();

                if ($existing) {
                    if ($this->altTextField && $altText = $get($this->altTextField)) {
                        $existing->update(['alt_text' => $altText]);
                    }

                    return $existing->id;
                }

                $altText = $this->altTextField ? $get($this->altTextField) : null;

                $media = Media::create([
                    'type' => 'image',
                    'file_path' => $state,
                    'disk' => 'public',
                    'original_filename' => basename($state),
                    'alt_text' => $altText,
                    'uploaded_by' => Auth::id() ?? 1,
                ]);

                return $media->id;
            });
    }

    /**
     * Set the name of a sibling form field that holds the alt text for this image.
     * When set, the alt text will be required on publish (handled by form validation)
     * and stored with the Media record.
     */
    public function altTextField(string $fieldName): static
    {
        $this->altTextField = $fieldName;

        return $this;
    }
}
