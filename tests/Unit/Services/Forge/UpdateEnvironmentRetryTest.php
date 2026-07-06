<?php

use App\Services\Forge\Api\Exceptions\ForgeApiException;
use App\Services\Forge\Api\Exceptions\ForgeValidationException;
use App\Services\Forge\Api\ForgeClient;
use App\Services\Forge\Data\ForgeSiteData;
use App\Services\Forge\ForgeService;
use App\Services\Forge\ForgeSetting;

function makeEnvRetryService(ForgeClient $client): ForgeService
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

test('it retries the environment update while a deployment is in progress', function () {
    $client = Mockery::mock(ForgeClient::class);
    $client->shouldReceive('updateSiteEnvironment')
        ->times(2)
        ->andReturnUsing(function (): void {
            static $calls = 0;

            if (++$calls === 1) {
                throw new ForgeValidationException(
                    'The .env file could not be updated. If the site was recently created, please wait at least 60 seconds and try again.',
                    422
                );
            }
        });

    makeEnvRetryService($client)->updateSiteEnvironmentFile('APP_ENV=production');
});

test('it does not retry genuine validation errors', function () {
    $client = Mockery::mock(ForgeClient::class);
    $client->shouldReceive('updateSiteEnvironment')
        ->once()
        ->andThrow(new ForgeValidationException('content: The content field is required.', 422));

    makeEnvRetryService($client)->updateSiteEnvironmentFile('');
})->throws(ForgeValidationException::class);

test('it retries conflict status codes regardless of message', function () {
    $client = Mockery::mock(ForgeClient::class);
    $client->shouldReceive('updateSiteEnvironment')
        ->times(2)
        ->andReturnUsing(function (): void {
            static $calls = 0;

            if (++$calls === 1) {
                throw new ForgeApiException('Conflict.', 409);
            }
        });

    makeEnvRetryService($client)->updateSiteEnvironmentFile('APP_ENV=production');
});
