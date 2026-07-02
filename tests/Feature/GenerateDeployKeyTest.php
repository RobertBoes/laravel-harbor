<?php

use App\Services\Forge\Data\ForgeSiteData;
use App\Services\Forge\ForgeService;
use App\Services\Forge\ForgeSetting;
use App\Services\Forge\Pipeline\GenerateDeployKey;
use App\Services\Github\GithubService;

test('it generates a key pair and installs it on the github repository', function () {
    $setting = Mockery::mock(ForgeSetting::class);
    $setting->githubCreateDeployKey = true;

    $service = mock(ForgeService::class)->makePartial();
    $service->setting = $setting;
    $service->site = null;

    $service->shouldReceive('getDeployKeyTitle')
        ->andReturn('Preview deploy key pr-1.example.com');

    $githubService = mock(GithubService::class);

    $githubService->shouldReceive('deleteAllKeys')
        ->once()
        ->with('Preview deploy key pr-1.example.com');

    $githubService->shouldReceive('createDeployKey')
        ->once()
        ->withArgs(function (string $title, string $key) {
            return $title === 'Preview deploy key pr-1.example.com'
                && str_starts_with($key, 'ssh-ed25519 ');
        })
        ->andReturn([]);

    $pipe = new GenerateDeployKey($githubService);

    expect($pipe($service, fn ($service) => $service))->toBe($service)
        ->and($service->publicDeployKey)->toStartWith('ssh-ed25519 ')
        ->and($service->privateDeployKey)->toContain('BEGIN OPENSSH PRIVATE KEY');
});

test('it does nothing when deploy keys are disabled', function () {
    $setting = Mockery::mock(ForgeSetting::class);
    $setting->githubCreateDeployKey = false;

    $service = mock(ForgeService::class)->makePartial();
    $service->setting = $setting;
    $service->site = null;

    $githubService = mock(GithubService::class);
    $githubService->shouldNotReceive('createDeployKey');

    $pipe = new GenerateDeployKey($githubService);

    expect($pipe($service, fn ($service) => $service))->toBe($service)
        ->and($service->publicDeployKey)->toBeNull();
});

test('it skips key generation when the site already exists', function () {
    $setting = Mockery::mock(ForgeSetting::class);
    $setting->githubCreateDeployKey = true;

    $service = mock(ForgeService::class)->makePartial();
    $service->setting = $setting;
    $service->site = Mockery::mock(ForgeSiteData::class);

    $githubService = mock(GithubService::class);
    $githubService->shouldNotReceive('createDeployKey');
    $githubService->shouldNotReceive('deleteAllKeys');

    $pipe = new GenerateDeployKey($githubService);

    expect($pipe($service, fn ($service) => $service))->toBe($service)
        ->and($service->publicDeployKey)->toBeNull();
});
