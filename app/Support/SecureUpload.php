<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * SECURITY.md §10–§12 — server-side upload hardening.
 *
 * The Media library upload is the only write surface an admin has, so every
 * file is re-examined on the server instead of trusting what the browser
 * claimed (filename, extension, or declared MIME type):
 *
 * - The MIME type is sniffed from the file *contents* (finfo), never from
 *   the extension, and must be in the hard-coded allowlist below.
 * - Images are re-encoded with GD. That strips EXIF (privacy: no
 *   geolocation/device metadata from source photos) and destroys polyglot
 *   payloads (e.g. a PHP script prefixed to a valid JPEG).
 * - Oversized images are downscaled before being written (perf, NFR-002).
 * - Documents must carry the `%PDF-` magic bytes.
 * - Stored names are random and use an extension derived from the sniffed
 *   MIME type, so a crafted name can neither traverse paths nor overwrite
 *   an existing file, and can never be served as a script.
 *
 * The hard-coded allowlist is the authority: a form can narrow it (see
 * `allowedMimeTypes()`) but can never widen it.
 */
class SecureUpload
{
    /** SECURITY.md §10 — accepted MIME types, grouped by media type. */
    public const IMAGE_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ];

    public const DOCUMENT_MIME_TYPES = [
        'application/pdf',
    ];

    public const VIDEO_MIME_TYPES = [
        'video/mp4',
        'video/webm',
        'video/quicktime',
    ];

    public const AUDIO_MIME_TYPES = [
        'audio/mpeg',
        'audio/wav',
        'audio/x-wav',
        'audio/ogg',
    ];

    /**
     * Sniffed MIME type => stored file extension. Also doubles as the master
     * allowlist: anything missing here is rejected outright.
     */
    public const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'application/pdf' => 'pdf',
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'video/quicktime' => 'mov',
        'audio/mpeg' => 'mp3',
        'audio/wav' => 'wav',
        'audio/x-wav' => 'wav',
        'audio/ogg' => 'ogg',
    ];

    /** SECURITY.md §10 — server-side size ceiling (KB) per sniffed type. */
    public const MAX_KILOBYTES = [
        'image' => 10240,             // 10 MB
        'application/pdf' => 20480,   // 20 MB
        'video' => 51200,             // 50 MB
        'audio' => 20480,             // 20 MB
    ];

    /** Longest edge an uploaded image is downscaled to on storage. */
    public const MAX_IMAGE_DIMENSION = 2400;

    /** Hard ceiling on decoded pixels, to avoid memory-exhaustion uploads. */
    public const MAX_IMAGE_PIXELS = 40000000; // 40 MP

    /** JPEG/WebP re-encode quality. */
    public const IMAGE_QUALITY = 85;

    /**
     * Byte size above which a PNG is re-encoded as WebP, if that is smaller.
     *
     * PNG stores a photo as RGB(A) and routinely lands multiple megabytes for
     * something displayed at 288px — a real 1268x1240 headshot upload came out
     * at 2.1 MB, which every homepage visit and every social crawler had to
     * download. WebP carries alpha just as PNG does, so converting is
     * capability-preserving, and WebP is already on the allowlist.
     *
     * The threshold plus the "only if smaller" check below mean graphics are
     * left alone: a logo, icon or diagram is small, compresses well as PNG, and
     * has no reason to be touched. Only a PNG big enough to be worth
     * converting gets converted, and only when WebP genuinely comes out
     * smaller — so no file ever grows.
     */
    public const PNG_TO_WEBP_THRESHOLD_BYTES = 150 * 1024;

    /**
     * Every MIME type the application will ever store.
     *
     * @return array<string>
     */
    public static function acceptedMimeTypes(): array
    {
        return array_keys(self::EXTENSIONS);
    }

    /**
     * Narrow the application allowlist to what a form asked for. A form may
     * only ever restrict the hard-coded list, never extend it (SECURITY.md §10).
     *
     * @param  array<string>|null  $requested  MIME types, wildcards allowed (e.g. image/*)
     * @return array<string>
     */
    public static function allowedMimeTypes(?array $requested): array
    {
        $requested = array_filter(array_map(
            fn ($type) => strtolower(trim((string) $type)),
            $requested ?? []
        ));

        if ($requested === []) {
            return self::acceptedMimeTypes();
        }

        $resolved = array_values(array_filter(
            self::acceptedMimeTypes(),
            function (string $mime) use ($requested): bool {
                foreach ($requested as $pattern) {
                    if (Str::is($pattern, $mime)) {
                        return true;
                    }
                }

                return false;
            }
        ));

        // A form asking for something entirely outside the allowlist gets the
        // full (still restricted) application list rather than an open door.
        return $resolved ?: self::acceptedMimeTypes();
    }

    /**
     * Random, allowlisted storage name for an upload. Never derived from the
     * client-supplied filename (SECURITY.md §10).
     */
    public static function randomFileName(string $mimeType): string
    {
        return Str::random(40).'.'.self::EXTENSIONS[$mimeType];
    }

    /**
     * Validate + sanitize one uploaded file.
     *
     * @param  array<string>|null  $allowedMimeTypes  narrowed allowlist (see allowedMimeTypes())
     * @return array{contents: string, mime: string, filename: string}
     *
     * @throws ValidationException when the upload fails any §10/§11 check
     */
    public static function sanitize(UploadedFile $file, string $attribute = 'file', ?array $allowedMimeTypes = null): array
    {
        if (! self::canSniffMimeTypes()) {
            // Same reasoning as the GD guard below: a deployment fact, reported
            // as a form error rather than a 500. Every check in this method
            // starts from the sniffed type, so without ext-fileinfo there is
            // nothing here that can run — `new finfo` raises `Error`, which no
            // form catches, and the admin would see "Server Error" on an
            // upload with nothing saying why.
            throw self::reject(
                $attribute,
                'This server cannot inspect uploaded files: the PHP fileinfo extension is not installed. Uploads will work once it is enabled.'
            );
        }

        $mime = self::detectMimeType($file);
        $allowed = self::allowedMimeTypes($allowedMimeTypes);

        if (! in_array($mime, $allowed, true) || ! isset(self::EXTENSIONS[$mime])) {
            throw self::reject(
                $attribute,
                'The :attribute must be one of the following file types: '.implode(', ', array_keys(self::EXTENSIONS)).'.'
            );
        }

        $maximum = self::maximumKilobytes($mime);

        if ($maximum !== null && $file->getSize() > $maximum * 1024) {
            throw self::reject($attribute, 'The :attribute may not be larger than '.number_format($maximum / 1024).' MB.');
        }

        $contents = self::contents($file);

        if (str_starts_with($mime, 'image/')) {
            // Re-encode: strips EXIF, flattens polyglots, bounds the dimensions.
            // The stored type can differ from the uploaded one (see
            // PHOTO_PNG_TARGET), so the effective mime is returned too and the
            // filename is derived from that — never leaving a WebP named .png.
            [$contents, $mime] = self::reencodeImage($contents, $mime, $attribute);
        } elseif ($mime === 'application/pdf') {
            if (! str_starts_with($contents, '%PDF-')) {
                throw self::reject($attribute, 'The :attribute is not a valid PDF document.');
            }
        }
        // Video/audio are only content-sniffed (no ffmpeg dependency in V1).

        return [
            'contents' => $contents,
            'mime' => $mime,
            'filename' => self::randomFileName($mime),
        ];
    }

    /**
     * Whether the image operations this class depends on are available.
     *
     * Every function below is provided by GD (`imagecreatefromstring`,
     * `imagescale`, `imagejpeg`, `imagepng`, `imagewebp`), which is why
     * composer.json declares `ext-gd` rather than treating it as optional:
     * without it no image can be re-encoded, and an image that is not
     * re-encoded is not one this application is willing to store.
     */
    public static function canProcessImages(): bool
    {
        return function_exists('imagecreatefromstring')
            && function_exists('imagejpeg')
            && function_exists('imagepng');
    }

    /**
     * Whether content sniffing is available.
     *
     * Every check in `sanitize()` begins with the type sniffed from the file's
     * bytes, and that sniffing is `ext-fileinfo`. composer.json requires it for
     * the same reason it requires ext-gd: without it there is no upload path
     * this class is willing to accept.
     */
    public static function canSniffMimeTypes(): bool
    {
        return class_exists('finfo');
    }

    /**
     * Content-based MIME detection. Falls back to sniffing bytes read through
     * the upload object when the temporary file is not on the local filesystem.
     */
    public static function detectMimeType(UploadedFile $file): string
    {
        $path = $file->getRealPath();

        if (is_string($path) && $path !== '' && is_file($path)) {
            $detected = @(new \finfo(FILEINFO_MIME_TYPE))->file($path);

            if (is_string($detected) && $detected !== '') {
                return strtolower($detected);
            }
        }

        $detected = @(new \finfo(FILEINFO_MIME_TYPE))->buffer(self::contents($file));

        return is_string($detected) && $detected !== '' ? strtolower($detected) : 'application/octet-stream';
    }

    /**
     * Raw bytes of the upload, read either from disk or through the upload
     * object (Livewire's temporary files live on their own storage disk).
     */
    public static function contents(UploadedFile $file): string
    {
        $path = $file->getRealPath();

        if (is_string($path) && $path !== '' && is_file($path)) {
            $contents = @file_get_contents($path);

            if (is_string($contents)) {
                return $contents;
            }
        }

        if (method_exists($file, 'get')) {
            $contents = $file->get();

            if (is_string($contents)) {
                return $contents;
            }
        }

        throw self::reject('file', 'The :attribute could not be read.');
    }

    /**
     * SECURITY.md §11 — decode, bound, and re-encode an image, dropping all
     * metadata (EXIF/GPS/ICC) in the process.
     *
     * Returns the encoded bytes AND the type they are actually in, which is
     * not always the type that came in — see PHOTO_PNG_TARGET.
     *
     * @return array{0: string, 1: string}
     */
    private static function reencodeImage(string $contents, string $mimeType, string $attribute): array
    {
        if (! self::canProcessImages()) {
            // A deployment fact, not a bad upload. Re-encoding is the security
            // boundary (§11) and it is GD that does it, so without the
            // extension there is no safe way to store an image — but the
            // caller should be told that plainly. Calling imagecreatefromstring
            // directly raises an uncatchable-by-the-form `Error`, which surfaces
            // as a 500 on save with nothing on screen about the cause.
            throw self::reject(
                $attribute,
                'This server cannot process images: the PHP GD extension is not installed. Uploads will work once it is enabled.'
            );
        }

        $size = @getimagesizefromstring($contents);

        if ($size === false) {
            throw self::reject($attribute, 'The :attribute is not a valid image.');
        }

        [$width, $height] = $size;

        if ($width < 1 || $height < 1 || ($width * $height) > self::MAX_IMAGE_PIXELS) {
            throw self::reject($attribute, 'The :attribute has invalid image dimensions.');
        }

        $image = @imagecreatefromstring($contents);

        if ($image === false) {
            throw self::reject($attribute, 'The :attribute is not a valid image.');
        }

        if (max($width, $height) > self::MAX_IMAGE_DIMENSION) {
            $scaled = $width >= $height
                ? imagescale($image, self::MAX_IMAGE_DIMENSION, -1, IMG_BICUBIC)
                : imagescale($image, -1, self::MAX_IMAGE_DIMENSION, IMG_BICUBIC);

            if ($scaled !== false) {
                imagedestroy($image);
                $image = $scaled;
            }
        }

        // Keep transparency for the formats that support it.
        if (in_array($mimeType, ['image/png', 'image/webp', 'image/gif'], true)) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
        }

        ob_start();

        $encoded = match ($mimeType) {
            'image/jpeg' => imagejpeg($image, null, self::IMAGE_QUALITY),
            'image/png' => imagepng($image, null, 8),
            'image/webp' => function_exists('imagewebp') ? imagewebp($image, null, self::IMAGE_QUALITY) : false,
            'image/gif' => imagegif($image),
            default => false,
        };

        $output = ob_get_clean();

        if ($encoded === false || ! is_string($output) || $output === '') {
            imagedestroy($image);

            throw self::reject($attribute, 'The :attribute could not be processed as an image.');
        }

        $target = $mimeType;

        // A large PNG is very likely a photograph, where PNG is the wrong
        // format. Try WebP and take it only if it is genuinely smaller, so a
        // logo, icon or diagram — small, and well compressed as PNG — is left
        // exactly as it was uploaded. GIF is never converted: it may be
        // animated, and WebP is not a safe stand-in for that.
        if ($mimeType === 'image/png'
            && function_exists('imagewebp')
            && strlen($output) >= self::PNG_TO_WEBP_THRESHOLD_BYTES) {
            ob_start();
            $webpEncoded = imagewebp($image, null, self::IMAGE_QUALITY);
            $webpOutput = ob_get_clean();

            if ($webpEncoded !== false
                && is_string($webpOutput)
                && $webpOutput !== ''
                && strlen($webpOutput) < strlen($output)) {
                $output = $webpOutput;
                $target = 'image/webp';
            }
        }

        imagedestroy($image);

        return [$output, $target];
    }

    /** Size ceiling (KB) for a sniffed MIME type. */
    private static function maximumKilobytes(string $mimeType): int
    {
        if (isset(self::MAX_KILOBYTES[$mimeType])) {
            return self::MAX_KILOBYTES[$mimeType];
        }

        return self::MAX_KILOBYTES[match (true) {
            str_starts_with($mimeType, 'video/') => 'video',
            str_starts_with($mimeType, 'audio/') => 'audio',
            str_starts_with($mimeType, 'image/') => 'image',
            default => 'application/pdf',
        }];
    }

    /** A validation failure carries the form field's own state path. */
    private static function reject(string $attribute, string $message): ValidationException
    {
        return ValidationException::withMessages([$attribute => $message]);
    }
}
