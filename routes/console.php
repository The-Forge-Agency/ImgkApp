<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('imgk:prune', function () {
    $disk = Storage::disk('local');
    $maxAge = now()->subDays((int) config('imgk.cache_max_age_days'))->getTimestamp();
    $deleted = 0;

    foreach ($disk->allFiles(config('imgk.cache_dir')) as $file) {
        if ($disk->lastModified($file) < $maxAge) {
            $disk->delete($file);
            $deleted++;
        }
    }

    $this->info("Cache imgk : {$deleted} fichier(s) supprimé(s).");
})->purpose('Purge les résultats de transformation plus vieux que la rétention configurée');

Schedule::command('imgk:prune')->daily();
