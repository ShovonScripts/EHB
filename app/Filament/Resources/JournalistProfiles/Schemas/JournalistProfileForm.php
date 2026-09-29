<?php

namespace App\Filament\Resources\JournalistProfiles\Schemas;

use App\Filament\Forms\Components\MediaFileUpload;
use App\Support\RemoteImageImporter;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;

class JournalistProfileForm
{
    /**
     * Seconds this form allows for an import, against
     * `SafeRemoteImage::TIMEOUT_SECONDS` (20s) elsewhere.
     *
     * Tighter on purpose. The import runs inside a Livewire request that the
     * admin is watching — the panel shows its processing state for the whole
     * duration — so the ceiling here is a UX budget, not a networking one. At
     * 20s a dead host looked like a hung page, which invites the instinct to
     * click Save again and submit the form twice.
     *
     * It is not as tight as it could be, because a false negative is the worse
     * failure: a real portrait served slowly simply fails, and the admin
     * retries or uploads the file directly. 8s still covers a 10 MB image on
     * an ordinary broadband connection.
     *
     * Reachability is bounded separately and much more tightly by
     * `SafeRemoteImage::CONNECT_TIMEOUT_SECONDS` (5s), so a host that is not
     * answering at all fails long before this ceiling is reached.
     */
    private const IMPORT_TIMEOUT_SECONDS = 8;

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // No `user_id` field. There is one admin and one profile, so a
                // dropdown asking which account owns it has nothing to
                // distinguish and is a footgun: picking the wrong row would
                // detach the profile from the user that `contentItems()` reads
                // its bylines through. The column stays on the model and keeps
                // its seeded value — removing the field from the schema means
                // EditRecord::fill() never touches it.
                TextInput::make('name')
                    ->required(),

                TextInput::make('title')
                    ->label('Professional Title')
                    ->required()
                    ->placeholder('e.g., Senior Reporter, Investigative Journalist'),

                MediaFileUpload::make('photo_media_id')
                    ->label('Profile Photo')
                    ->image()
                    ->imageEditor()
                    ->disk('public')
                    ->directory('journalist/profile')
                    ->altTextField('photo_alt_text')
                    ->columnSpanFull()
                    ->helperText('A square portrait works best — the homepage shows it at 288px. Large photos are stored as WebP automatically.'),

                // Import-by-URL, as an alternative to uploading a file. The
                // image is downloaded once and stored on our own disk, then
                // handed to the upload field above as a storage path — so it
                // goes through the identical validation (mime sniffed from
                // the bytes, EXIF stripped, dimensions bounded, WebP when
                // smaller) and ends up as an ordinary Media row. Nothing on
                // the public site ever references the third-party URL.
                //
                // `live(onBlur: true)` is what makes the behaviour below real,
                // and it is load-bearing rather than cosmetic:
                //
                // Without it, Livewire renders `wire:model` — deferred — so the
                // value is not sent to the server when the field loses focus.
                // `afterStateUpdated` therefore never ran on blur; it ran on
                // whatever request happened next, which on a form with no other
                // live fields meant *Save*. The import then executed inside the
                // save request, so:
                //
                //   - a bad URL was reported only after submitting, not
                //     immediately, which is the opposite of the intent;
                //   - Save performed a network fetch, blocking for up to
                //     SafeRemoteImage::TIMEOUT_SECONDS (20s) on a dead host,
                //     with no indication the form was waiting on anything;
                //   - the admin's other edits were coupled to the fate of a
                //     third-party URL they were only trying out.
                //
                // Firing on blur decouples them: the download is attempted and
                // its outcome reported while the admin is still looking at the
                // field, and Save stays a pure database write.
                TextInput::make('photo_image_url')
                    ->label('…or import from a URL')
                    ->url()
                    ->placeholder('https://example.com/portrait.jpg')
                    ->dehydrated(false)
                    ->columnSpanFull()
                    ->live(onBlur: true)
                    ->helperText('The image is downloaded once and stored here, so the site does not depend on that site staying up. JPEG, PNG, WebP or GIF, up to 10 MB.')
                    ->afterStateUpdated(function (?string $state, callable $set, callable $get): void {
                        if (blank($state)) {
                            return;
                        }

                        try {
                            $path = (new RemoteImageImporter('journalist/profile'))
                                ->import($state, null, self::IMPORT_TIMEOUT_SECONDS);
                        } catch (ValidationException $e) {
                            // Surface the real reason (private address, not an
                            // image, too large, unreachable) rather than a
                            // generic failure, then clear the field.
                            Notification::make()
                                ->danger()
                                ->title('Could not import that image')
                                ->body($e->errors()['url'][0] ?? 'The URL could not be imported.')
                                ->persistent()
                                ->send();

                            $set('photo_image_url', null);

                            return;
                        }

                        $set('photo_media_id', $path);

                        // Offer the alt text the public view would generate, so
                        // the CMS record and the rendered page agree. The field
                        // stays editable and is still required to save.
                        $name = trim((string) $get('name'));

                        $set('photo_alt_text', $name !== '' ? 'Portrait of '.$name : null);

                        $set('photo_image_url', null);

                        Notification::make()
                            ->success()
                            ->title('Image imported')
                            ->body('Saved to your library. Add alt text below, then save.')
                            ->send();
                    }),

                TextInput::make('photo_alt_text')
                    ->label('Profile Photo Alt Text')
                    ->placeholder('e.g., Portrait of Emrul Hasan Bappi')
                    ->required(fn (callable $get): bool => $get('photo_media_id') !== null)
                    ->columnSpanFull()
                    // Actually enforced, matching every other image field in
                    // the admin (see ContentItemForm's featured_image_alt_text).
                    // This previously only *said* "required" in the helper text
                    // while allowing an empty value, which is how the uploaded
                    // photo ended up with no alt text at all.
                    ->helperText('Describe the photo for screen readers. Required while a photo is set.'),

                Textarea::make('short_bio')
                    ->label('Short Bio')
                    ->rows(3)
                    ->columnSpanFull()
                    ->helperText('Brief summary for homepage/about teaser'),

                Textarea::make('long_bio')
                    ->label('Full Biography')
                    ->rows(8)
                    ->columnSpanFull()
                    ->helperText('Complete biography for About page'),

                TextInput::make('social_links.email')
                    ->label('Public Email')
                    ->email()
                    ->placeholder('name@example.com'),

                TextInput::make('social_links.twitter')
                    ->label('X / Twitter URL')
                    ->url()
                    ->placeholder('https://x.com/username'),

                TextInput::make('social_links.linkedin')
                    ->label('LinkedIn URL')
                    ->url()
                    ->placeholder('https://linkedin.com/in/username'),

                TextInput::make('social_links.facebook')
                    ->label('Facebook URL')
                    ->url()
                    ->placeholder('https://facebook.com/username'),

                TagsInput::make('skills')
                    ->label('Skills / Beats')
                    ->placeholder('Add a skill or beat')
                    ->columnSpanFull()
                    ->helperText('e.g., Politics, Investigative Reporting, Human Rights, Climate'),
            ]);
    }
}
