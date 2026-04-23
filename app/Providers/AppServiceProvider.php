<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Livewire\Blaze\Blaze;
use Override;

final class AppServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Blaze::optimize()->in(resource_path('views/components'));

        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->id ?: $request->ip())
        );

        RateLimiter::for('downloads', fn (Request $request) => Limit::perMinute(30)
            ->by($request->user()?->id ?: $request->ip())
        );
    }
}
