<?php

namespace App\Providers;

use App\Services\ImageFetcher;
use App\Services\RemoteImageFetcher;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ImageFetcher::class, RemoteImageFetcher::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Fair use de l'offre ouverte : par IP, généreux pour un site normal
        // (le CDN et le cache local absorbent les liens réutilisés).
        RateLimiter::for('transform', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));

        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));
    }
}
