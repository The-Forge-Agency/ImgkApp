<?php

namespace App\Services;

use App\Support\ImgkException;
use App\Support\UrlGuard;
use Illuminate\Support\Facades\Storage;

class RemoteImageFetcher implements ImageFetcher
{
    public function fetch(string $url): array
    {
        // Les uploads faits sur imgk même sont lus sur disque : pas de boucle
        // HTTP vers soi-même, et pas de garde SSRF à contourner pour eux.
        if (($local = $this->localUploadPath($url)) !== null) {
            return $local;
        }

        $maxBytes = (int) config('imgk.max_source_bytes');
        $redirectsLeft = (int) config('imgk.max_redirects');
        $current = $url;

        while (true) {
            $target = UrlGuard::validate($current);
            $response = $this->request($target['url'], $target['host'], $target['ip'], $maxBytes);

            if (in_array($response['status'], [301, 302, 303, 307, 308], true)) {
                if ($response['location'] === null || $redirectsLeft-- <= 0) {
                    throw new ImgkException('Trop de redirections sur l\'image source.', 422);
                }

                $current = $this->absoluteUrl($response['location'], $target['url']);

                continue;
            }

            if ($response['status'] !== 200) {
                throw new ImgkException("L'image source répond avec le statut {$response['status']}.", 422);
            }

            return self::validated($response['body'], $response['contentType']);
        }
    }

    /**
     * @return array{bytes: string, mime: string}|null
     */
    private function localUploadPath(string $url): ?array
    {
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $parts = parse_url($url);

        if (! is_array($parts) || ($parts['host'] ?? null) !== $appHost || $appHost === null) {
            return null;
        }

        $path = $parts['path'] ?? '';

        // Les images de démo embarquées sont aussi lues sur disque.
        if (preg_match('#^/demo/([a-z0-9-]+\.(?:jpg|png|webp))$#', $path, $demo)) {
            $file = public_path('demo/'.$demo[1]);

            if (! is_file($file)) {
                throw new ImgkException('Cette image de démo n\'existe pas.', 404);
            }

            return self::validated((string) file_get_contents($file), null);
        }

        if (! preg_match('#^/u/([0-9A-Za-z]{26})(?:\.[a-z0-9]+)?$#', $path, $m)) {
            return null;
        }

        $disk = Storage::disk('local');
        $dir = config('imgk.uploads_dir');
        $matches = $disk->files($dir);

        foreach ($matches as $file) {
            if (str_starts_with(basename($file), $m[1])) {
                $bytes = $disk->get($file);

                return self::validated($bytes, null);
            }
        }

        throw new ImgkException('Cet upload imgk n\'existe pas ou plus.', 404);
    }

    /**
     * @return array{status: int, body: string, contentType: ?string, location: ?string}
     */
    private function request(string $url, string $host, string $ip, int $maxBytes): array
    {
        $port = str_starts_with($url, 'https') ? 443 : 80;
        $body = '';
        $aborted = false;

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => (int) config('imgk.fetch_timeout'),
            CURLOPT_USERAGENT => 'imgk/1.0 (+https://imgk.tfa52.app)',
            CURLOPT_HTTPHEADER => ['Accept: image/*,application/pdf;q=0.9,*/*;q=0.5'],
            // Épinglage de l'IP validée : le fetch part vers l'IP contrôlée,
            // pas vers ce qu'une seconde résolution DNS déciderait (rebinding).
            CURLOPT_RESOLVE => ["{$host}:{$port}:{$ip}"],
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_WRITEFUNCTION => function ($ch, string $chunk) use (&$body, &$aborted, $maxBytes): int {
                $body .= $chunk;

                if (strlen($body) > $maxBytes) {
                    $aborted = true;

                    return 0;
                }

                return strlen($chunk);
            },
        ]);

        curl_exec($ch);

        if ($aborted) {
            curl_close($ch);

            throw new ImgkException('Image source trop lourde : '.round(config('imgk.max_source_bytes') / 1024 / 1024).' Mo maximum.', 413);
        }

        $errno = curl_errno($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: null;
        $location = curl_getinfo($ch, CURLINFO_REDIRECT_URL) ?: null;
        curl_close($ch);

        if ($errno === CURLE_OPERATION_TIMEDOUT) {
            throw new ImgkException('L\'image source met trop de temps à répondre.', 504);
        }

        if ($errno !== 0) {
            throw new ImgkException('Impossible de récupérer l\'image source.', 422);
        }

        return ['status' => $status, 'body' => $body, 'contentType' => $contentType, 'location' => $location];
    }

    private function absoluteUrl(string $location, string $base): string
    {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }

        $parts = parse_url($base);
        $origin = $parts['scheme'].'://'.$parts['host'];

        if (str_starts_with($location, '/')) {
            return $origin.$location;
        }

        $dir = rtrim(dirname($parts['path'] ?? '/'), '/');

        return $origin.$dir.'/'.$location;
    }

    /**
     * @return array{bytes: string, mime: string}
     */
    public static function validated(string $bytes, ?string $declaredType): array
    {
        if ($bytes === '') {
            throw new ImgkException('L\'image source est vide.', 422);
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($bytes) ?: 'application/octet-stream';

        $accepted = config('imgk.accepted_mimes');

        // Le type réel (magic bytes) fait foi, pas l'en-tête du serveur distant.
        if (! in_array($mime, $accepted, true) || $mime === 'application/octet-stream') {
            $label = $declaredType ?? $mime;

            throw new ImgkException("Ce contenu n'est pas une image exploitable ({$label}).", 415);
        }

        return ['bytes' => $bytes, 'mime' => $mime];
    }
}
