<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\Style;
use App\Services\Otp\OtpChannel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Is this box fit to take real bookings?
 *
 * Written because every one of these was found by hand, late, by reading
 * config on a laptop and guessing what production would differ on. A
 * deployment should be able to answer the question itself.
 *
 * Checks are grouped by what goes wrong if they fail: `stop` means somebody
 * gets hurt — a client's account taken, a debug page leaking the database
 * password. `warn` means the shop looks unfinished or a feature quietly does
 * nothing. The command exits non-zero on any stop, so a pipeline can gate
 * on it.
 */
class Preflight extends Command
{
    protected $signature = 'magnetic:preflight';

    protected $description = 'Check this installation is fit to take real bookings';

    /** @var array<int, array{level: string, name: string, detail: string}> */
    private array $findings = [];

    public function handle(): int
    {
        $this->checkSecurity();
        $this->checkDelivery();
        $this->checkCaches();
        $this->checkStorage();
        $this->checkShop();

        $stops = array_filter($this->findings, fn (array $f): bool => $f['level'] === 'stop');
        $warns = array_filter($this->findings, fn (array $f): bool => $f['level'] === 'warn');

        $this->newLine();

        foreach ($this->findings as $finding) {
            $this->line(sprintf(
                '  <fg=%s>%s</> %s',
                $finding['level'] === 'stop' ? 'red' : 'yellow',
                $finding['level'] === 'stop' ? 'STOP' : 'WARN',
                $finding['name'],
            ));
            $this->line('       '.$finding['detail']);
        }

        if ($this->findings === []) {
            $this->info('  Nothing to flag. This box can take real bookings.');
            $this->newLine();

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line(sprintf(
            '  %d must be fixed, %d worth a look.',
            count($stops),
            count($warns),
        ));
        $this->newLine();

        return $stops === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * The ones that get somebody hurt.
     */
    private function checkSecurity(): void
    {
        if (config('app.debug') === true) {
            $this->stop(
                'Debug mode is on',
                'Any error page prints the database password. Set APP_DEBUG=false.',
            );
        }

        if (config('app.env') !== 'production') {
            $this->warn_(
                'APP_ENV is '.config('app.env'),
                'Seeders that refuse to plant known passwords key off this.',
            );
        }

        if (! str_starts_with((string) config('app.url'), 'https://')) {
            $this->stop(
                'APP_URL is not https',
                'Sessions and the booking form go over the wire in clear. '.config('app.url'),
            );
        }
    }

    /**
     * The ones where a feature quietly does nothing.
     */
    private function checkDelivery(): void
    {
        // Not a stop: the routes shut themselves while nothing delivers, so
        // this is a feature that is off rather than a hole that is open.
        if (! app(OtpChannel::class)->delivers()) {
            $this->warn_(
                'Login codes cannot be sent',
                'The code flow is shut (503) until a real SMS or WhatsApp channel '.
                'is bound to magnetic.otp_channel. Clients sign in with a password.',
            );
        }

        if (config('mail.default') === 'log') {
            $this->stop(
                'Mail goes to a log file',
                'Password reset emails will never arrive. Configure a real mailer.',
            );
        }
    }

    /**
     * Caches built before the code they describe.
     *
     * The failure this catches is a bare 500 with nothing in the log, which
     * is the worst kind to debug: a cached route table from before a release
     * has no idea about its new routes, so route() throws deep inside a view
     * and the page dies before the logger gets a look in. Payment::proofUrl()
     * is exactly that shape.
     *
     * Deploys should clear and rebuild in that order — artisan optimize after
     * the code and the assets are in place, never before.
     */
    private function checkCaches(): void
    {
        $sources = [
            base_path('routes/web.php'),
            base_path('routes/api.php'),
            base_path('routes/settings.php'),
        ];

        $newestRoute = max(array_map(
            fn (string $file): int => is_file($file) ? (int) filemtime($file) : 0,
            $sources,
        ));

        foreach (glob(base_path('bootstrap/cache/routes-*.php')) ?: [] as $cache) {
            if (filemtime($cache) < $newestRoute) {
                $this->stop(
                    'The route cache is older than the routes',
                    'Pages die with a bare 500 and nothing in the log. '.
                    'Run php artisan route:cache after deploying, not before.',
                );
            }
        }

        $configCache = base_path('bootstrap/cache/config.php');

        if (is_file($configCache)) {
            $newestConfig = max(array_map(
                fn (string $file): int => (int) filemtime($file),
                glob(config_path('*.php')) ?: [config_path('app.php')],
            ));

            if (filemtime($configCache) < $newestConfig) {
                $this->stop(
                    'The config cache is older than the config',
                    'The app is running on settings that no longer exist. '.
                    'Run php artisan config:cache.',
                );
            }
        }

        // Every Inertia page is served through the one root view, so a
        // missing manifest is every page, not one.
        if (! is_file(public_path('build/manifest.json'))) {
            $this->stop(
                'No Vite manifest',
                'Every page will throw. Run npm run build before serving.',
            );
        }
    }

    private function checkStorage(): void
    {
        if (! is_link(public_path('storage'))) {
            $this->stop(
                'No public storage link',
                'Gallery and team photos will 404. Run php artisan storage:link.',
            );
        }

        // Proofs are private on purpose; a public one is a leak.
        if (Storage::disk('public')->exists('payment-proofs')) {
            $this->stop(
                'Payment proofs on the public disk',
                'Files under storage/app/public/payment-proofs are readable by '.
                'anyone with the URL. Move them to storage/app/payment-proofs.',
            );
        }
    }

    /**
     * The ones where the shop looks unfinished.
     */
    private function checkShop(): void
    {
        if (Branch::query()->where('is_active', true)->doesntExist()) {
            $this->stop('No active branch', 'Every public page needs one.');
        }

        if (Service::query()->where('is_active', true)->doesntExist()) {
            $this->stop('No active services', 'There is nothing to book.');
        }

        if (StaffProfile::query()->where('is_bookable', true)->doesntExist()) {
            $this->stop('No bookable barbers', 'Every slot will come back taken.');
        }

        $styles = Style::query()->where('is_active', true)->count();
        $withPhotos = Style::query()->where('is_active', true)->whereNotNull('image_path')->count();

        if ($styles > 0 && $withPhotos === 0) {
            $this->warn_(
                'The gallery has no photos',
                "All {$styles} looks are published without an image.",
            );
        }

        $team = StaffProfile::query()->count();
        $teamPhotos = StaffProfile::query()->whereNotNull('photo_path')->count();

        if ($team > 0 && $teamPhotos === 0) {
            $this->warn_('No team photos', "All {$team} of the team are without a picture.");
        }

        // Demo bookings in a live database make every report a lie.
        if (app()->isProduction() && Appointment::query()->exists()) {
            $this->warn_(
                'This database already has bookings',
                Appointment::query()->count().' of them. If this is seeded demo data, '.
                'clear it before opening, or every report starts wrong.',
            );
        }
    }

    private function stop(string $name, string $detail): void
    {
        $this->findings[] = ['level' => 'stop', 'name' => $name, 'detail' => $detail];
    }

    private function warn_(string $name, string $detail): void
    {
        $this->findings[] = ['level' => 'warn', 'name' => $name, 'detail' => $detail];
    }
}
