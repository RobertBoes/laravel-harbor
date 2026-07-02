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
use phpseclib3\Crypt\EC;

class GenerateDeployKey
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

        $this->information('Generating a deploy key pair.');

        $key = EC::createKey('Ed25519');

        $service->setDeployKeyPair(
            publicKey: trim($key->getPublicKey()->toString('OpenSSH')),
            privateKey: $key->toString('OpenSSH'),
        );

        $this->information('---> Removing existing deploy keys on the GitHub repository.');

        $this->githubService->deleteAllKeys($service->getDeployKeyTitle());

        $this->information('---> Installing the deploy key on the GitHub repository.');

        $this->githubService->createDeployKey($service->getDeployKeyTitle(), $service->publicDeployKey);

        return $next($service);
    }
}
