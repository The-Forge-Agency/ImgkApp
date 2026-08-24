<?php

namespace App\Http\Controllers;

use App\Services\FaviconPackGenerator;
use App\Services\ImageFetcher;
use App\Services\ImageTransformer;
use App\Support\ImgkParams;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class TransformController extends Controller
{
    public function __construct(
        private readonly ImageFetcher $fetcher,
        private readonly ImageTransformer $transformer,
        private readonly FaviconPackGenerator $faviconPack,
    ) {}

    /**
     * Le cœur d'imgk : /?url=IMAGE&reglages → l'image transformée.
     * Même adresse = même image, servie depuis le cache.
     */
    public function __invoke(Request $request): Response
    {
        $params = ImgkParams::fromQuery($request->query());
        $resolvedOutput = $this->resolveOutput($params, $request);

        $cacheKey = $params->cacheKey($resolvedOutput ?? 'source');

        if (($cached = $this->fromCache($cacheKey)) !== null) {
            return $this->respond($request, $params, $cacheKey, $cached, hit: true);
        }

        $source = $this->fetcher->fetch($params->url);

        if ($params->pack === 'favicon') {
            $result = $this->faviconPack->generate($params, $source['bytes'], $source['mime']);
        } else {
            $output = $resolvedOutput ?? $this->defaultOutputFor($source['mime'], $params);
            $result = $this->transformer->transform($params, $source['bytes'], $source['mime'], $output);
        }

        $result['source_bytes'] = strlen($source['bytes']);
        $this->store($cacheKey, $result);

        return $this->respond($request, $params, $cacheKey, $result, hit: false);
    }

    private function resolveOutput(ImgkParams $params, Request $request): ?string
    {
        if ($params->pack === 'favicon') {
            return 'zip';
        }

        if ($params->output === 'auto') {
            $accept = (string) $request->header('Accept', '');

            return match (true) {
                str_contains($accept, 'image/avif') => 'avif',
                str_contains($accept, 'image/webp') => 'webp',
                default => 'jpg',
            };
        }

        return $params->output;
    }

    private function defaultOutputFor(string $sourceMime, ImgkParams $params): string
    {
        $output = match ($sourceMime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/avif' => 'avif',
            'image/gif' => 'gif',
            'image/x-icon', 'image/vnd.microsoft.icon', 'image/bmp' => 'png',
            default => 'jpg', // heic, tiff, pdf → un format que tous les navigateurs affichent
        };

        // Un habillage qui crée de la transparence sur une source opaque bascule en png.
        if (in_array($output, ['jpg', 'gif'], true) && $params->hasTransparencyDressing() && $params->bg === null) {
            return 'png';
        }

        return $output;
    }

    /**
     * @return array{bytes: string, mime: string, ext: string, source_bytes?: int}|null
     */
    private function fromCache(string $key): ?array
    {
        $disk = Storage::disk('local');
        $base = config('imgk.cache_dir').'/'.substr($key, 0, 2).'/'.$key;

        if (! $disk->exists("{$base}.json")) {
            return null;
        }

        $meta = json_decode($disk->get("{$base}.json"), true);

        if (! is_array($meta) || ! $disk->exists("{$base}.bin")) {
            return null;
        }

        $meta['bytes'] = $disk->get("{$base}.bin");

        return $meta;
    }

    /**
     * @param  array{bytes: string, mime: string, ext: string, source_bytes: int}  $result
     */
    private function store(string $key, array $result): void
    {
        $disk = Storage::disk('local');
        $base = config('imgk.cache_dir').'/'.substr($key, 0, 2).'/'.$key;

        $disk->put("{$base}.bin", $result['bytes']);
        $disk->put("{$base}.json", json_encode([
            'mime' => $result['mime'],
            'ext' => $result['ext'],
            'source_bytes' => $result['source_bytes'],
        ]));
    }

    /**
     * @param  array{bytes: string, mime: string, ext: string, source_bytes?: int}  $result
     */
    private function respond(Request $request, ImgkParams $params, string $cacheKey, array $result, bool $hit): Response
    {
        $etag = '"'.substr($cacheKey, 0, 32).'"';

        if ($request->header('If-None-Match') === $etag) {
            return response('', 304)->withHeaders([
                'ETag' => $etag,
                'Cache-Control' => 'public, max-age=31536000, immutable',
            ]);
        }

        $disposition = $result['ext'] === 'zip'
            ? 'attachment; filename="imgk-favicon-pack.zip"'
            : 'inline; filename="imgk.'.$result['ext'].'"';

        $headers = [
            'Content-Type' => $result['mime'],
            'Content-Length' => (string) strlen($result['bytes']),
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'ETag' => $etag,
            'Content-Disposition' => $disposition,
            'X-Imgk-Cache' => $hit ? 'HIT' : 'MISS',
            'X-Imgk-Bytes' => (string) strlen($result['bytes']),
            'Access-Control-Allow-Origin' => '*',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if (isset($result['source_bytes'])) {
            $headers['X-Imgk-Source-Bytes'] = (string) $result['source_bytes'];
        }

        if ($params->output === 'auto') {
            $headers['Vary'] = 'Accept';
        }

        return response($result['bytes'], 200, $headers);
    }
}
