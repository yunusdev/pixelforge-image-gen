<?php

test('pixelforge settings are read from the environment', function () {
    expect(config('services.pixelforge'))->toBe([
        'api_key' => 'test-key',
        'webhook_secret' => 'test-webhook-secret',
        'base_url' => 'https://pixelforge.test',
    ]);
});
