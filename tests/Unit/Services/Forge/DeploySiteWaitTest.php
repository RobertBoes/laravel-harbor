<?php

use App\Services\Forge\Api\ForgeClient;
use App\Services\Forge\Data\ForgeDeploymentData;
use App\Services\Forge\Data\ForgeDomainData;
use App\Services\Forge\Data\ForgeSiteData;
use App\Services\Forge\ForgeService;
use App\Services\Forge\ForgeSetting;

function makeDeployWaitService(ForgeClient $client): ForgeService
{
    $setting = Mockery::mock(ForgeSetting::class);
    $setting->server = '1';
    $setting->timeoutSeconds = 300;

    $service = new class($setting, $client) extends ForgeService
    {
        protected function retryDelaySeconds(): int
        {
            return 0;
        }
    };
    $site = Mockery::mock(ForgeSiteData::class);
    $site->id = 10;
    $service->setSite($site);

    return $service;
}

test('it waits until the deployment finishes', function () {
    $client = Mockery::mock(ForgeClient::class);
    $client->shouldReceive('deploySite')->once()
        ->andReturn(new ForgeDeploymentData(id: 5, status: 'queued'));
    $client->shouldReceive('getDeployment')->twice()
        ->andReturn(
            new ForgeDeploymentData(id: 5, status: 'deploying'),
            new ForgeDeploymentData(id: 5, status: 'finished'),
        );

    makeDeployWaitService($client)->deploySite(waitOnDeploy: true);
});

test('it fails with the log tail when the deployment fails', function () {
    $client = Mockery::mock(ForgeClient::class);
    $client->shouldReceive('deploySite')->once()
        ->andReturn(new ForgeDeploymentData(id: 5, status: 'deploying'));
    $client->shouldReceive('getDeployment')->once()
        ->andReturn(new ForgeDeploymentData(id: 5, status: 'failed'));
    $client->shouldReceive('getDeploymentLog')->once()
        ->andReturn("npm ci\nFATAL ERROR: JavaScript heap out of memory");

    makeDeployWaitService($client)->deploySite(waitOnDeploy: true);
})->throws(RuntimeException::class, 'heap out of memory');

test('it does not poll when waiting is disabled', function () {
    $client = Mockery::mock(ForgeClient::class);
    $client->shouldReceive('deploySite')->once()
        ->andReturn(new ForgeDeploymentData(id: 5, status: 'queued'));
    $client->shouldNotReceive('getDeployment');

    makeDeployWaitService($client)->deploySite(waitOnDeploy: false);
});

test('it waits for the certificate and retries a failed issuance once', function () {
    $client = Mockery::mock(ForgeClient::class);
    $domain = Mockery::mock(ForgeDomainData::class);
    $domain->id = 7;
    $domain->name = 'pr-9.example.com';

    $client->shouldReceive('listDomains')->once()->andReturn([$domain]);
    $client->shouldReceive('enableLetsEncrypt')->twice();
    $client->shouldReceive('getActiveCertificate')->times(3)->andReturn(
        ['active' => false, 'status' => 'installing', 'request_status' => 'verifying'],
        ['active' => false, 'status' => 'failed', 'request_status' => 'created'],
        ['active' => true, 'status' => 'installed', 'request_status' => 'created'],
    );

    makeDeployWaitService($client)->obtainLetsEncryptCertificate(['pr-9.example.com'], waitUntilActive: true);
});

test('it throws when issuance fails twice', function () {
    $client = Mockery::mock(ForgeClient::class);
    $domain = Mockery::mock(ForgeDomainData::class);
    $domain->id = 7;
    $domain->name = 'pr-9.example.com';

    $client->shouldReceive('listDomains')->once()->andReturn([$domain]);
    $client->shouldReceive('enableLetsEncrypt')->twice();
    $client->shouldReceive('getActiveCertificate')->twice()->andReturn(
        ['active' => false, 'status' => 'failed', 'request_status' => 'created'],
        ['active' => false, 'status' => 'failed', 'request_status' => 'created'],
    );

    makeDeployWaitService($client)->obtainLetsEncryptCertificate(['pr-9.example.com'], waitUntilActive: true);
})->throws(RuntimeException::class, 'failed twice');
