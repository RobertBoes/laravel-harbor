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
use App\Services\Github\GithubService;
use App\Traits\Outputifier;
use Closure;
use Illuminate\Support\Arr;

class InstallServerDeployKey
{
    use Outputifier;

    public function __construct(public GithubService $githubService)
    {
        //
    }

    public function __invoke(ForgeService $service, Closure $next)
    {
        if (! $service->setting->githubCreateDeployKey || ! is_null($service->site)) {
            return $next($service);
        }

        $publicKey = trim((string) $service->server->localPublicKey);

        if ($publicKey === '') {
            $this->warning('---> The Forge API returned no public key for the server; skipping deploy key installation.');

            return $next($service);
        }

        if ($this->keyAlreadyInstalled($publicKey)) {
            $this->information("---> The server's public key is already installed on the GitHub repository.");

            return $next($service);
        }

        $this->information("---> Installing the server's public key on the GitHub repository.");

        $this->githubService->createDeployKey(
            sprintf('Forge server %s', $service->server->name ?? $service->server->id),
            $publicKey
        );

        return $next($service);
    }

    private function keyAlreadyInstalled(string $publicKey): bool
    {
        $blob = $this->keyBlob($publicKey);

        foreach ($this->githubService->getDeployKeys() as $key) {
            if ($this->keyBlob((string) Arr::get($key, 'key')) === $blob) {
                return true;
            }
        }

        return false;
    }

    private function keyBlob(string $key): string
    {
        $parts = preg_split('/\s+/', trim($key)) ?: [];

        return implode(' ', array_slice($parts, 0, 2));
    }
}
