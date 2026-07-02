<?php

use App\Services\Forge\Api\Exceptions\ForgeValidationException as ValidationException;
use App\Services\Forge\Data\ForgeSiteData;
use App\Services\Forge\ForgeService;
use App\Services\Forge\ForgeSetting;
use App\Services\Forge\Pipeline\OrCreateNewSite;

test('it fails on incorrect payload', function ($site, $expectedErrors) {
    $service = mock(ForgeService::class);
    $setting = Mockery::mock(ForgeSetting::class);
    $setting->server = '111111';
    $setting->projectType = 'php';
    $setting->phpVersion = 'php82';
    $setting->gitProvider = 'github';
    $setting->repository = 'acme/example';
    $setting->repositoryUrl = null;
    $setting->branch = 'main';
    $setting->quickDeploy = false;
    $setting->githubCreateDeployKey = false;
    $setting->nginxTemplate = null;
    $setting->siteIsolationRequired = false;
    $service->setting = $setting;

    $service->shouldReceive('getFormattedDomainName')
        ->once()
        ->andReturn($site['name']);

    $service->shouldReceive('createSite')
        ->once()
        ->with('111111', [
            'type' => 'php',
            'domain_mode' => 'custom',
            'name' => $site['name'],
            'www_redirect_type' => 'none',
            'allow_wildcard_subdomains' => false,
            'php_version' => 'php82',
            'web_directory' => '/public',
            'source_control_provider' => 'github',
            'repository' => 'acme/example',
            'branch' => 'main',
            'push_to_deploy' => false,
            'generate_deploy_key' => false,
        ])
        ->andThrow(new ValidationException(
            message: implode(PHP_EOL, collect($expectedErrors)->flatten()->all()),
            statusCode: 422,
            errors: collect($expectedErrors)->flatten()->all(),
        ));

    expect(
        app(OrCreateNewSite::class)($service, fn ($service) => $service)
    )
        ->toBe($service);
})
    ->with('site', [
        'expected_errors' => [['First Error', 'Second Error']],
    ])
    ->throws(ValidationException::class);

test('it sends the generated deploy key pair instead of generate_deploy_key', function () {
    $service = mock(ForgeService::class)->makePartial();
    $setting = Mockery::mock(ForgeSetting::class);
    $setting->server = '111111';
    $setting->projectType = 'php';
    $setting->phpVersion = 'php82';
    $setting->gitProvider = 'custom';
    $setting->repository = 'acme/example';
    $setting->repositoryUrl = 'git@github.com:acme/example.git';
    $setting->branch = 'main';
    $setting->quickDeploy = false;
    $setting->githubCreateDeployKey = true;
    $setting->nginxTemplate = null;
    $setting->siteIsolationRequired = false;
    $service->setting = $setting;
    $service->site = null;
    $service->setDeployKeyPair('ssh-ed25519 AAAApublic', '-----BEGIN OPENSSH PRIVATE KEY-----');

    $service->shouldReceive('getFormattedDomainName')
        ->andReturn('pr-1.example.com');

    $site = Mockery::mock(ForgeSiteData::class);

    $service->shouldReceive('createSite')
        ->once()
        ->with('111111', [
            'type' => 'php',
            'domain_mode' => 'custom',
            'name' => 'pr-1.example.com',
            'www_redirect_type' => 'none',
            'allow_wildcard_subdomains' => false,
            'php_version' => 'php82',
            'web_directory' => '/public',
            'source_control_provider' => 'custom',
            'repository' => 'git@github.com:acme/example.git',
            'branch' => 'main',
            'push_to_deploy' => false,
            'public_deploy_key' => 'ssh-ed25519 AAAApublic',
            'private_deploy_key' => '-----BEGIN OPENSSH PRIVATE KEY-----',
        ])
        ->andReturn($site);

    $service->shouldReceive('setSite')->once()->with($site);
    $service->shouldReceive('getFormattedAliases')->andReturn([]);

    expect(
        app(OrCreateNewSite::class)($service, fn ($service) => $service)
    )->toBe($service);
});
