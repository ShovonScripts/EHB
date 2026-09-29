<?php

namespace App\Console\Commands;

use App\Models\Media;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Why is an uploaded image missing from the public site?
 *
 * The report is always the same shape — "the admin shows it, the page doesn't"
 * — and it has four unrelated causes that look identical in a browser:
 *
 *   1. **The file is not on this server.** A Media row points at a path under
 *      `storage/app/public`, and a redeploy, a container rebuild, or a missing
 *      persistent volume leaves the row behind without the bytes. The admin
 *      panel can still show a preview, because a preview fetched from a stale
 *      cache or rendered from the temporary upload looks the same as one
 *      fetched from disk.
 *   2. **`public/storage` is missing, stale, or not traversable** — and the
 *      `/storage/{path}` fallback route is not answering either. This is the
 *      Windows-junction case documented in config/filesystems.php.
 *   3. **Something in front of Laravel answers `/storage`** — an old
 *      `public/storage` *directory* left by an earlier deploy, a web-server
 *      rule, or a proxy/CDN. The request then never reaches the fallback route.
 *   4. **Nothing is wrong with the file or its URL** and the page is serving a
 *      cached copy, or the template never had an image on it.
 *
 * Guessing between those from the outside is what costs the most time, so this
 * command checks each layer and says which one is failing — and what to do.
 *
 *     php artisan media:doctor
 *     php artisan media:doctor --file=journalist/profile/abc.webp
 *     php artisan media:doctor --no-http
 */
class MediaDoctor extends Command
{
    protected $signature = 'media:doctor
        {--file= : Check this one storage path instead of every Media row}
        {--limit=5 : How many existing files to probe over HTTP}
        {--no-http : Skip the self-request check (offline, or a locked-down host)}';

    protected $description = 'Diagnose why uploaded media is not appearing on the public site';

    /**
     * Problems that should fail the command — a non-zero exit means "there is
     * something to fix", so it can be used in a deploy check.
     *
     * @var array<int, string>
     */
    private array $problems = [];

    public function handle(): int
    {
        $diskName = 'public';
        $disk = config("filesystems.disks.{$diskName}") ?? [];

        $this->newLine();
        $this->components->info('The disk');
        $this->line('  url:        '.(string) ($disk['url'] ?? '(none — URLs fall back to the bare path)'));
        $this->line('  root:       '.(string) ($disk['root'] ?? '(none)'));
        $this->line('  visibility: '.(string) ($disk['visibility'] ?? 'private (default)'));
        $this->line('  serve:      '.((($disk['serve'] ?? false) === true) ? 'true' : 'false'));

        if (($disk['visibility'] ?? 'private') !== 'public') {
            $this->components->warn(
                'The disk is not marked public, so the /storage fallback route requires a signed URL. '
                .'An ordinary <img src> cannot sign anything and will get a 404 in production.'
            );
        }

        $this->checkLink();
        $files = $this->checkFiles($diskName);
        $this->checkRoutes();

        if (! $this->option('no-http')) {
            $this->probeOverHttp($files);
        } else {
            $this->components->info('The HTTP check was skipped (--no-http).');
        }

        $this->newLine();

        if ($this->problems === []) {
            $this->components->info('Every layer this command can see is healthy — the file exists, and the URL for it is served.');

            return self::SUCCESS;
        }

        $this->components->error('Found '.count($this->problems).' problem(s):');

        foreach ($this->problems as $problem) {
            $this->line('  • '.$problem);
        }

        return self::FAILURE;
    }

    /**
     * `public/storage` — the symlink `storage:link` creates.
     *
     * Three distinct states, and only the first is healthy: absent (the
     * fallback route has to answer every request), present but a real directory
     * (an earlier deploy copied files there; new uploads go to
     * `storage/app/public` and never appear), or present and pointing somewhere
     * else (a moved or rebuilt storage path).
     */
    private function checkLink(): void
    {
        $this->newLine();
        $this->components->info('The public symlink');

        $link = public_path('storage');
        $expected = storage_path('app/public');

        if (! file_exists($link) && ! is_link($link)) {
            $this->line('  No public/storage entry. Laravel\'s /storage route is then the only thing that can serve uploads.');
            $this->line('  Fix: php artisan storage:link   (harmless if the route already works)');

            return;
        }

        if (! is_link($link)) {
            $this->components->warn('  public/storage exists but is a real directory, not a symlink.');
            $this->line('  Files uploaded to storage/app/public will not be visible through it.');
            $this->line('  Fix: remove that directory, then run php artisan storage:link');

            $this->problems[] = 'public/storage is a real directory, not a symlink — uploads do not reach it.';

            return;
        }

        $target = realpath($link);

        if ($target === false || $target !== realpath($expected)) {
            $this->components->warn('  public/storage points at '.($target ?: '(a missing target)'));
            $this->line('  Expected: '.$expected);
            $this->line('  Fix: php artisan storage:link (after removing the stale link)');

            $this->problems[] = 'public/storage points somewhere other than storage/app/public.';

            return;
        }

        $this->line('  public/storage -> '.$target.' (ok)');
    }

    /**
     * Does every Media row have its bytes on this disk?
     *
     * This is the check that catches the deploy-shaped failure: the row is in
     * the database (which is often a managed service with its own backups) and
     * the file was on a container filesystem that has since been replaced.
     *
     * @return array<int, string> storage paths that exist, for the HTTP probe
     */
    private function checkFiles(string $diskName): array
    {
        $this->newLine();
        $this->components->info('The files behind the Media rows');

        $one = $this->option('file');

        if ($one) {
            $path = (string) $one;

            if (Storage::disk($diskName)->exists($path)) {
                $this->line('  '.$path.' — present on the '.$diskName.' disk.');

                return [$path];
            }

            $this->components->warn('  '.$path.' is NOT on the '.$diskName.' disk.');

            $this->problems[] = "The file {$path} is not on this server's {$diskName} disk.";

            return [];
        }

        $media = Media::query()->orderBy('id')->limit(500)->get();

        if ($media->isEmpty()) {
            $this->line('  No Media rows yet — nothing to check.');

            return [];
        }

        $present = [];
        $missing = [];

        foreach ($media as $row) {
            if ($row->disk !== $diskName) {
                $this->components->warn("  #{$row->id} is stored on the '{$row->disk}' disk, not '{$diskName}'.");
                $this->problems[] = "Media #{$row->id} uses the '{$row->disk}' disk; its URL will not be served by the public route.";

                continue;
            }

            if (Storage::disk($diskName)->exists($row->file_path)) {
                $present[] = $row->file_path;
            } else {
                $missing[] = $row;
            }
        }

        $this->line(sprintf('  %d of %d files are present on disk.', count($present), $media->count()));

        foreach (array_slice($missing, 0, 10) as $row) {
            $this->line("  missing: #{$row->id}  {$row->file_path}");
        }

        if ($missing !== []) {
            $count = count($missing);

            $this->components->warn(
                "  {$count} Media row(s) have no file on this server. "
                .'A redeploy or a missing persistent volume is the usual cause — '
                .'re-upload those images, or restore storage/app/public from a backup.'
            );

            $this->problems[] = "{$count} Media row(s) point at files that are not on this server.";
        }

        return $present;
    }

    /**
     * Which route answers `/storage/{path}`?
     *
     * Laravel registers one per serveable local disk and keys them by URI, so
     * when two disks share `/storage` — here the private `local` disk and the
     * public one — the *last* registered route is the one that runs. That is
     * the public disk (it is declared after `local` in config/filesystems.php),
     * which is the behaviour the public site needs, but it is worth seeing
     * explicitly rather than assuming: if the order ever changes, every image
     * starts returning 404 in production, because the private disk's route
     * requires a signed URL.
     */
    private function checkRoutes(): void
    {
        $this->newLine();
        $this->components->info('The /storage route');

        $prefix = '/storage';
        $diskUrl = config('filesystems.disks.public.url');

        if (is_string($diskUrl) && $diskUrl !== '') {
            $prefix = rtrim((string) (parse_url($diskUrl, PHP_URL_PATH) ?: $diskUrl), '/');
        }

        $uri = trim($prefix, '/').'/{path}';
        $matches = [];

        foreach (Route::getRoutes() as $route) {
            if (in_array('GET', $route->methods(), true) && $route->uri() === $uri) {
                $matches[] = $route->getName() ?: $route->getActionName();
            }
        }

        if ($matches === []) {
            $this->components->warn('  No GET route is registered for '.$uri.'.');

            if (app()->routesAreCached()) {
                $this->line('  Routes are cached — rebuild them after changing the disks config:');
                $this->line('    php artisan route:clear && php artisan route:cache');
            } else {
                $this->line('  Set "serve" => true on the public disk in config/filesystems.php.');
            }

            $this->problems[] = "No route is registered for {$uri}, so uploads can only be served by the symlink.";

            return;
        }

        foreach ($matches as $name) {
            $this->line('  '.$uri.'  ->  '.$name);
        }

        if (count($matches) > 1) {
            $this->line('  The last one listed is the one that answers (routes are keyed by URI).');
        }

        if (app()->routesAreCached()) {
            $this->line('  Routes are cached; config changes need a route cache rebuild.');
        }
    }

    /**
     * Actually fetch a few of the URLs the site emits.
     *
     * The point is to test the same path a browser takes: web server, symlink,
     * fallback route, and whatever sits in front. A status other than 200 is
     * the answer, and it also tells us whether the failure is above Laravel
     * (403/404 from the web server or a proxy) or inside it.
     *
     * @param  array<int, string>  $paths
     */
    private function probeOverHttp(array $paths): void
    {
        $this->newLine();
        $this->components->info('Fetching '.min(count($paths), (int) $this->option('limit')).' of the URLs the site emits');

        if ($paths === []) {
            $this->line('  No existing files to probe.');

            return;
        }

        // `app.url` is what `url()` builds these links from, so a wrong value
        // makes every image URL point at another host even though the file is
        // on this one. Printing it next to the probe makes that visible.
        $this->line('  app.url: '.config('app.url'));

        foreach (array_slice($paths, 0, max(1, (int) $this->option('limit'))) as $path) {
            $url = url('/storage/'.$path);

            try {
                $response = Http::timeout(5)->get($url);
                $status = $response->status();
            } catch (Throwable $e) {
                $this->components->warn('  '.$url.' — could not be fetched ('.$e->getMessage().')');
                $this->line('  If this host cannot reach itself, use --no-http and check the URL from a browser.');

                $this->problems[] = 'The app could not fetch its own media URL — check APP_URL and any firewall.';

                return;
            }

            $this->line(sprintf('  %s  %s', str_pad((string) $status, 4), $url));

            if ($status !== 200) {
                $this->problems[] = "GET {$url} returned {$status} rather than 200.";

                if (in_array($status, [403, 404], true)) {
                    $this->line('  The file is on disk but the URL is not being served: something in front of Laravel');
                    $this->line('  is answering /storage (an old public/storage directory, a web-server rule, or a proxy).');
                }

                return;
            }
        }
    }
}
