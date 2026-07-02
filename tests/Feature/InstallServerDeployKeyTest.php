<?php

use App\Services\Forge\Data\ForgeServerData;
use App\Services\Forge\ForgeService;
use App\Services\Forge\ForgeSetting;
use App\Services\Forge\Pipeline\InstallServerDeployKey;
use App\Services\Github\GithubService;

function makeServiceForServerKey(?string $publicKey): ForgeService
{
    $setting = Mockery::mock(ForgeSetting::class);
    $setting->githubCreateDeployKey = true;

    $service = mock(ForgeService::class)->makePartial();
    $service->setting = $setting;
    $service->site = null;
    $service->server = new ForgeServerData(
        id: 1,
        name: 'preview-server',
        ipAddress: '127.0.0.1',
        localPublicKey: $publicKey,
    );

    return $service;
}

test('it installs the server public key on the github repository', function () {
    $service = makeServiceForServerKey('ssh-ed25519 AAAAserverkey forge@server');

    $githubService = mock(GithubService::class);
    $githubService->shouldReceive('getDeployKeys')->once()->andReturn([
        ['id' => 1, 'title' => 'other', 'key' => 'ssh-ed25519 AAAAotherkey'],
    ]);
    $githubService->shouldReceive('createDeployKey')
        ->once()
        ->with('Forge server preview-server', 'ssh-ed25519 AAAAserverkey forge@server')
        ->andReturn([]);

    $pipe = new InstallServerDeployKey($githubService);

    expect($pipe($service, fn ($service) => $service))->toBe($service);
});

test('it skips installation when the key is already on the repository', function () {
    $service = makeServiceForServerKey('ssh-ed25519 AAAAserverkey forge@server');

    $githubService = mock(GithubService::class);
    $githubService->shouldReceive('getDeployKeys')->once()->andReturn([
        ['id' => 1, 'title' => 'Forge server preview-server', 'key' => 'ssh-ed25519 AAAAserverkey'],
    ]);
    $githubService->shouldNotReceive('createDeployKey');

    $pipe = new InstallServerDeployKey($githubService);

    expect($pipe($service, fn ($service) => $service))->toBe($service);
});

test('it warns and continues when the server has no public key', function () {
    $service = makeServiceForServerKey(null);

    $githubService = mock(GithubService::class);
    $githubService->shouldNotReceive('getDeployKeys');
    $githubService->shouldNotReceive('createDeployKey');

    $pipe = new InstallServerDeployKey($githubService);

    expect($pipe($service, fn ($service) => $service))->toBe($service);
});

test('it does nothing when deploy keys are disabled', function () {
    $setting = Mockery::mock(ForgeSetting::class);
    $setting->githubCreateDeployKey = false;

    $service = mock(ForgeService::class)->makePartial();
    $service->setting = $setting;
    $service->site = null;

    $githubService = mock(GithubService::class);
    $githubService->shouldNotReceive('createDeployKey');

    $pipe = new InstallServerDeployKey($githubService);

    expect($pipe($service, fn ($service) => $service))->toBe($service);
});
