<?php

namespace Tests\Support;

use App\Services\ImageFetcher;
use App\Services\RemoteImageFetcher;
use App\Support\ImgkException;

class FakeFetcher implements ImageFetcher
{
    /**
     * @param  array<string, string>  $urls  url => bytes
     */
    public function __construct(private array $urls) {}

    public function fetch(string $url): array
    {
        if (! isset($this->urls[$url])) {
            throw new ImgkException('Impossible de récupérer l\'image source.', 422);
        }

        // Même validation de contenu que le fetcher réel.
        return RemoteImageFetcher::validated($this->urls[$url], null);
    }
}
