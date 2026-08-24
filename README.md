# imgk 🖼️

**Transforme une image juste en écrivant une adresse.**

imgk est une photocopieuse d'images branchée sur internet : tu donnes l'adresse d'une image, tu écris tes réglages dans le lien, et l'image transformée ressort instantanément — redimensionnée, convertie, allégée, habillée. Le lien est la recette : réutilisable à l'infini, mis en cache, collable partout. Change un chiffre, toutes les images changent.

```
https://imgk.tfa52.app/?url=https://exemple.com/photo.jpg&w=500&h=500&output=webp&optimized=true
```

App #17/52 du défi [TFA52](https://buymeacoffee.com/theforgeagency) — gratuite, open source, financée par les dons.

## Fonctionnalités

- **Transformation par l'adresse** : `?url=IMAGE&reglages`, ordre libre, zéro compte
- **Taille & cadrage** : `w`, `h`, `fit=cover|contain`, `dpr` (Retina), `max`, `crop=x,y,l,h`
- **Format & poids** : `output=png|jpg|webp|avif|gif|ico|pdf|auto`, `q`, `optimized`, `maxsize=200kb` (qualité ajustée automatiquement), `strip` (EXIF/GPS), HEIC d'iPhone décodé à la volée
- **Couleurs & effets** : `conversion=white-black|sepia|invert|duotone|scan`, `blur`, `sharpen`, `brightness`, `contrast`, `saturation`
- **Habillage** : `radius` (jusqu'au cercle `radius=max`), `border`, `bg`, `pad`, `rotate`, `flip`, `trim`
- **Incrustations** : `watermark` (+ `wm_pos`, `wm_opacity`, `wm_size`), `text` (+ `text_color`, `text_size`, `text_pos`)
- **Presets & packs** : `preset=avatar|produit|scan|social|compress|thumbnail`, `pack=favicon` (zip complet), `page=` (PDF), `frame=` (GIF)
- **Cache déterministe** : paramètres canonisés → même adresse, même image, servie sans recalcul (`Cache-Control: immutable`, ETag, `output=auto` négocié via `Accept` + `Vary`)
- **Studio clic** (`/app`) : glisser-déposer / coller, sliders, aperçu avant/après, poids en direct, presets, lot en zip côté client — et l'adresse générée en clair
- **Docs** (`/docs`) : la référence complète des réglages

## Sécurité du proxy

- Garde SSRF stricte : IP privées, loopback, link-local, métadonnées cloud (IPv4 + IPv6) bloquées, DNS résolu puis épinglé (anti-rebinding), redirections re-validées, ports 80/443 uniquement
- Bornes d'entrée : 30 Mo, 50 mégapixels (rejet des décompressions-bombes avant décodage), timeouts, types MIME vérifiés sur les magic bytes
- Fair use par IP (120 transformations/min, 20 uploads/min), erreurs JSON qui ne fuitent jamais une IP interne
- Allowlist optionnelle de domaines sources : `IMGK_ALLOWED_HOSTS=exemple.com,autre.com`

## Stack

Laravel + Imagick (aucune dépendance PHP supplémentaire), Alpine.js + Tailwind CSS v4, fflate pour le zip client. Pas de base de données requise pour la transformation : cache et uploads sur disque (`storage/app`).

## Installation

```bash
composer run setup   # install, .env, clé, migrations, npm, build
composer run dev     # serveur + vite + logs
```

Prérequis : PHP ≥ 8.3 avec les extensions **imagick** (compilée avec webp, avif/heif, ghostscript pour le PDF), **zip**, **exif**, **fileinfo**.

## Tests

```bash
php artisan test
```

## Déploiement Laravel Forge

1. Site PHP 8.3+ pointé sur `ImgkApp`, racine web `public/`
2. Vérifier les extensions : `php -m | grep -E 'imagick|zip|exif'` et `php -r "print_r(Imagick::queryFormats());"` (WEBP, AVIF/HEIC, PDF)
3. `.env` : `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://imgk.tfa52.app`
4. Script de déploiement :

```bash
cd /home/forge/imgk.tfa52.app
git pull origin $FORGE_SITE_BRANCH
$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader
npm ci && npm run build
$FORGE_PHP artisan migrate --force
$FORGE_PHP artisan config:cache && $FORGE_PHP artisan route:cache && $FORGE_PHP artisan view:cache
( flock -w 10 9 || exit 1; echo 'Restarting FPM...'; sudo -S service $FORGE_PHP_FPM reload ) 9>/tmp/fpmlock
```

5. Activer le **scheduler** Forge (purge quotidienne du cache : `imgk:prune`)
6. Nginx : monter `client_max_body_size 40m;` pour les uploads du studio

## Self-host

Le même code tourne n'importe où : clone, `composer run setup`, et ta propre instance transforme tes images avec un contrôle total (allowlist, quotas, rétention du cache — tout est dans `config/imgk.php` et les variables `IMGK_*`).

## Licence

MIT.
