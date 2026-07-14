<?php

declare(strict_types=1);

it('loads package config', function (): void {
    expect(config('google-connect.proxy_base_url'))->toBe('https://proxy.test')
        ->and(config('google-connect.scopes'))->toContain('openid');
});
