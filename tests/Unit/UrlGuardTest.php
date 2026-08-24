<?php

use App\Support\ImgkException;
use App\Support\UrlGuard;

test('les adresses internes et dangereuses sont bloquées', function (string $url) {
    UrlGuard::validate($url);
})->with([
    'loopback' => 'http://127.0.0.1/image.png',
    'loopback bis' => 'http://127.8.4.2/image.png',
    'privée 10.x' => 'http://10.0.0.5/image.png',
    'privée 192.168' => 'https://192.168.1.1/cam.jpg',
    'privée 172.16' => 'http://172.16.0.1/a.png',
    'métadonnées cloud' => 'http://169.254.169.254/latest/meta-data/',
    'CGN' => 'http://100.64.0.1/a.png',
    'zéro' => 'http://0.0.0.0/a.png',
    'IPv6 loopback' => 'http://[::1]/a.png',
    'IPv6 ULA' => 'http://[fd00::1]/a.png',
    'IPv6 mapped v4 privée' => 'http://[::ffff:192.168.1.1]/a.png',
    'IP décimale' => 'http://2130706433/a.png',
    'schéma ftp' => 'ftp://exemple.com/a.png',
    'schéma file' => 'file:///etc/passwd',
    'port exotique' => 'http://exemple.com:8080/a.png',
    'identifiants' => 'http://admin:pass@exemple.com/a.png',
    'pas une url' => 'pouet',
])->throws(ImgkException::class);

test('une IP publique directe est acceptée', function () {
    $result = UrlGuard::validate('https://93.184.216.34/image.png');

    expect($result['ip'])->toBe('93.184.216.34');
});

test('le message d\'erreur ne fuit jamais l\'adresse résolue', function () {
    try {
        UrlGuard::validate('http://169.254.169.254/latest/');
        $this->fail('aurait dû bloquer');
    } catch (ImgkException $e) {
        expect($e->getMessage())->not->toContain('169.254');
    }
});

test('l\'allowlist de domaines restreint les sources quand elle est définie', function () {
    config(['imgk.allowed_hosts' => ['exemple.com']]);

    expect(fn () => UrlGuard::validate('https://93.184.216.34/a.png'))
        ->toThrow(ImgkException::class, 'liste autorisée');
});
