<?php

namespace App\Services;

interface ImageFetcher
{
    /**
     * Récupère l'image source de façon sûre.
     *
     * @return array{bytes: string, mime: string}
     */
    public function fetch(string $url): array;
}
