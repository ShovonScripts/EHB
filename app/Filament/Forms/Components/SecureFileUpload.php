<?php

namespace App\Filament\Forms\Components;

use App\Support\SecureUpload;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\FileUpload;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * SECURITY.md §10–§11 — the app's hardened file upload component.
 *
 * Sits on top of Filament's FileUpload and makes three guarantees that the
 * stock component does not:
 *
 * 1. `acceptedFileTypes()` is a concrete, content-sniffed allowlist rather
 *    than `image/*` — the stock `image()` helper would also accept
 *    `image/svg+xml`, which is a stored-XSS vector when served from our own
 *    origin. `image()` is overridden below to keep that wildcard out.
 * 2. A server-side size ceiling is always set, so `maxSize()` can never be
 *    left unset by a form.
 * 3. Files are re-validated (and images re-encoded / EXIF-stripped) from
 *    their raw bytes on save, and stored under a random name whose extension
 *    comes from the sniffed MIME type — see SecureUpload.
 */
class SecureFileUpload extends FileUpload
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->acceptedFileTypes(SecureUpload::acceptedMimeTypes())
            ->maxSize(SecureUpload::MAX_KILOBYTES['image'])
            // Stored through our own sanitizing writer below, but keep a safe
            // name generator in place for any code path that calls
            // `saveUploadedFile()` directly.
            ->getUploadedFileNameForStorageUsing(
                fn (BaseFileUpload $component, TemporaryUploadedFile $file): string => SecureUpload::randomFileName(
                    SecureUpload::detectMimeType($file)
                )
            )
            ->saveUploadedFileUsing(function (BaseFileUpload $component, TemporaryUploadedFile $file): ?string {
                $upload = SecureUpload::sanitize(
                    $file,
                    (string) $component->getStatePath(),
                    $component->getAcceptedFileTypes(),
                );

                $disk = $component->getDisk();
                $path = trim(((string) $component->getDirectory()).'/'.$upload['filename'], '/');

                $disk->put($path, $upload['contents']);

                if ($component->getVisibility() === 'public') {
                    rescue(fn () => $disk->setVisibility($path, 'public'), report: false);
                }

                return $path;
            });
    }

    /**
     * Images only — and only the raster formats we can safely re-encode.
     * (Filament's own `image()` sets `image/*`, which includes SVG.)
     */
    public function image(): static
    {
        parent::image();

        return $this->acceptedFileTypes(SecureUpload::IMAGE_MIME_TYPES);
    }
}
