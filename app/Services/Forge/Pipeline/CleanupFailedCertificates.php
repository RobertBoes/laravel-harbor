<?php

declare(strict_types=1);

/**
 * This file is part of Laravel Harbor.
 *
 * (c) Mehran Rasulian <mehran.rasulian@gmail.com>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */

namespace App\Services\Forge\Pipeline;

use App\Services\Forge\ForgeService;
use App\Traits\Outputifier;
use Closure;
use Throwable;

/**
 * A failed certificate anywhere on the server leaves nginx config referencing
 * certificate files that don't exist, which breaks `nginx -t` and thereby every
 * reload server-wide — new sites stop loading and later certificates can never
 * be served. Preview servers host many short-lived sites, so one broken site
 * poisons all of them: sweep the whole server before provisioning.
 */
class CleanupFailedCertificates
{
    use Outputifier;

    public function __invoke(ForgeService $service, Closure $next)
    {
        if (! $service->setting->sslRequired) {
            return $next($service);
        }

        try {
            $this->sweep($service);
        } catch (Throwable $e) {
            // Best effort: the sweep protects other sites; this site's own certificate
            // handling still has its own failure path.
            $this->warning(sprintf('---> Could not sweep failed certificates: %s', $e->getMessage()));
        }

        return $next($service);
    }

    protected function sweep(ForgeService $service): void
    {
        foreach ($service->client->listSites($service->setting->server) as $site) {
            foreach ($service->client->listDomains($service->setting->server, $site->id) as $domain) {
                $certificate = $service->client->getActiveCertificate($service->setting->server, $site->id, $domain->id);

                if (($certificate['status'] ?? null) !== 'failed') {
                    continue;
                }

                $this->warning(sprintf(
                    '---> Removing failed certificate for %s (site %s) — its nginx config breaks reloads server-wide.',
                    $domain->name,
                    $site->name
                ));

                $service->client->deleteCertificate($service->setting->server, $site->id, $domain->id, $certificate['id']);
            }
        }
    }
}
