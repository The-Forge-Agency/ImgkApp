<?php

namespace App\Support;

/**
 * Protection anti-SSRF du proxy : imgk va chercher des URLs arbitraires,
 * il ne doit JAMAIS pouvoir atteindre le réseau interne, localhost ou les
 * métadonnées cloud. La résolution DNS est faite ici et l'IP validée est
 * épinglée pour le fetch (anti DNS-rebinding).
 */
class UrlGuard
{
    /**
     * Valide une URL source et retourne [url, host, ip épinglée].
     *
     * @return array{url: string, host: string, ip: string}
     */
    public static function validate(string $url): array
    {
        if (mb_strlen($url) > 4096) {
            throw new ImgkException('url trop longue (4096 caractères max).');
        }

        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            throw new ImgkException('url invalide : il faut une adresse complète en http(s)://');
        }

        $scheme = strtolower($parts['scheme']);

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new ImgkException('Seuls http:// et https:// sont acceptés.');
        }

        if (isset($parts['port']) && ! in_array($parts['port'], [80, 443], true)) {
            throw new ImgkException('Seuls les ports 80 et 443 sont acceptés.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new ImgkException('Les identifiants dans l\'url ne sont pas acceptés.');
        }

        $host = strtolower(rtrim($parts['host'], '.'));

        $allowed = config('imgk.allowed_hosts', []);

        if ($allowed !== [] && ! self::hostAllowed($host, $allowed)) {
            throw new ImgkException('Ce domaine source n\'est pas dans la liste autorisée.', 403);
        }

        $ip = self::resolvePublicIp($host);

        return ['url' => $url, 'host' => $host, 'ip' => $ip];
    }

    /**
     * @param  list<string>  $allowed
     */
    private static function hostAllowed(string $host, array $allowed): bool
    {
        foreach ($allowed as $pattern) {
            $pattern = strtolower(trim($pattern));

            if ($pattern === $host || str_ends_with($host, '.'.$pattern)) {
                return true;
            }
        }

        return false;
    }

    private static function resolvePublicIp(string $host): string
    {
        // Hôte donné directement en IP (y compris formes décimales/hex piégeuses).
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return self::assertPublicIp($host);
        }

        $stripped = trim($host, '[]');

        if (filter_var($stripped, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            return self::assertPublicIp($stripped);
        }

        if (preg_match('/^[0-9.]+$/', $host) || preg_match('/^0x[0-9a-f.]+$/i', $host)) {
            throw new ImgkException('Adresse IP au format non standard refusée.', 403);
        }

        $records = @dns_get_record($host, DNS_A + DNS_AAAA) ?: [];
        $ips = [];

        foreach ($records as $record) {
            $ips[] = $record['ip'] ?? $record['ipv6'] ?? null;
        }

        $ips = array_values(array_filter($ips));

        if ($ips === []) {
            throw new ImgkException("Impossible de résoudre le domaine {$host}.", 422);
        }

        // TOUTES les IPs doivent être publiques, sinon un attaquant mixe
        // une IP publique et une privée derrière le même nom.
        foreach ($ips as $ip) {
            self::assertPublicIp($ip);
        }

        return $ips[0];
    }

    private static function assertPublicIp(string $ip): string
    {
        $public = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE | FILTER_FLAG_GLOBAL_RANGE,
        );

        if ($public === false || self::isBlockedRange($ip)) {
            // Message volontairement muet sur l'IP résolue : ne rien fuiter du réseau interne.
            throw new ImgkException('Cette adresse pointe vers une ressource non accessible.', 403);
        }

        return $ip;
    }

    private static function isBlockedRange(string $ip): bool
    {
        // Plages que les flags PHP ne couvrent pas toutes : métadonnées cloud,
        // CGN, benchmark, multicast, mapped IPv6…
        $blocked4 = [
            ['0.0.0.0', 8], ['10.0.0.0', 8], ['100.64.0.0', 10], ['127.0.0.0', 8],
            ['169.254.0.0', 16], ['172.16.0.0', 12], ['192.0.0.0', 24], ['192.0.2.0', 24],
            ['192.168.0.0', 16], ['198.18.0.0', 15], ['198.51.100.0', 24], ['203.0.113.0', 24],
            ['224.0.0.0', 3],
        ];

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            $long = ip2long($ip);

            foreach ($blocked4 as [$base, $bits]) {
                if (($long >> (32 - $bits)) === (ip2long($base) >> (32 - $bits))) {
                    return true;
                }
            }

            return false;
        }

        $bin = inet_pton($ip);

        if ($bin === false) {
            return true;
        }

        // IPv6 : loopback, link-local, ULA, mapped/embedded IPv4, documentation.
        $blocked6 = [
            '::1/128', '::/128', 'fe80::/10', 'fc00::/7',
            '::ffff:0:0/96', '64:ff9b::/96', '2001:db8::/32', 'ff00::/8',
        ];

        foreach ($blocked6 as $cidr) {
            [$base, $prefixBits] = explode('/', $cidr);
            $baseBin = inet_pton($base);
            $prefixBits = (int) $prefixBits;
            $fullBytes = intdiv($prefixBits, 8);
            $remainder = $prefixBits % 8;

            if (substr($bin, 0, $fullBytes) !== substr($baseBin, 0, $fullBytes)) {
                continue;
            }

            if ($remainder === 0) {
                return true;
            }

            $mask = 0xFF << (8 - $remainder) & 0xFF;

            if ((ord($bin[$fullBytes]) & $mask) === (ord($baseBin[$fullBytes]) & $mask)) {
                return true;
            }
        }

        // IPv4 mappée en IPv6 : revalider l'IPv4 embarquée.
        if (str_starts_with($ip, '::ffff:') && filter_var(substr($ip, 7), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return self::isBlockedRange(substr($ip, 7));
        }

        return false;
    }
}
