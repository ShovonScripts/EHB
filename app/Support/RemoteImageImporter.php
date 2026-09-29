<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Turn a remote image URL into a file on our own disk.
 *
 * The remote fetch and its SSRF protections live in SafeRemoteImage. This class
 * is the seam between that and Filament: it takes the downloaded bytes, runs
 * them through exactly the same validation an upload goes through, and returns
 * a storage-relative path in the format Media.file_path expects.
 *
 * Note what this does *not* do: it does not create the Media row. The upload
 * component's dehydrate step already handles a storage path and attaches the
 * Media row (including alt text) itself, so handing it the path means an
 * imported image is indistinguishable from an uploaded one. Duplicating that
 * logic here would be a second place for media records to be created wrongly.
 */
class RemoteImageImporter
{
    public function __construct(private readonly string $directory) {}

    /**
     * Download, validate, sanitize and store one image from a URL.
     *
     * @param  int|null  $timeout  total seconds allowed for the download;
     *                             see SafeRemoteImage::fetch()
     * @return string storage-relative path, e.g. "journalist/profile/abc.webp"
     *
     * @throws ValidationException on any unsafe URL, non-image body, or
     *                             oversized/undownloadable resource
     */
    public function import(string $url, ?string $altText = null, ?int $timeout = null): string
    {
        $fetched = SafeRemoteImage::fetch($url, $timeout);

        // Run the bytes through SecureUpload exactly as an upload would:
        // content-sniffed MIME (not the URL or the response header), EXIF
        // stripped, flattened if it is a polyglot, dimensions bounded, and
        // converted to WebP when that is genuinely smaller. An SVG or a
        // text-file-disguised-as-a-jpg cannot survive this, which is the
        // point — the URL route must not be a weaker door than the upload one.
        $sanitized = SecureUpload::sanitize(
            $this->asUploadedFile($fetched['contents'], $fetched['mime']),
            'image URL',
            SecureUpload::IMAGE_MIME_TYPES,
        );

        $path = Str::finish($this->directory, '/').$sanitized['filename'];

        Storage::disk('public')->put($path, $sanitized['contents']);

        return $path;
    }

    /**
     * Wrap raw bytes in an UploadedFile, which is what SecureUpload accepts.
     *
     * The temp file is created in the system temp dir with a name derived from
     * the sniffed type — never from the URL, which is attacker-controlled and
     * could carry a path or a surprising extension.
     */
    private function asUploadedFile(string $contents, string $mime): UploadedFile
    {
        $extension = SecureUpload::EXTENSIONS[$mime] ?? 'bin';
        $tempPath = tempnam(sys_get_temp_dir(), 'import_').'.'.$extension;

        file_put_contents($tempPath, $contents);

        // test=true: this did not arrive as a genuine multipart upload, and
        // UploadedFile's ::getSize() paths assume a real HTTP upload otherwise.
        return new UploadedFile($tempPath, 'imported.'.$extension, $mime, null, true);
    }
}
