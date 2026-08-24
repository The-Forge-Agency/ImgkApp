<?php

namespace App\Http\Controllers;

use App\Support\ImgkException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uploads de l'UI clic : le fichier déposé reçoit une URL publique stable,
 * que l'adresse imgk peut ensuite transformer comme n'importe quelle source.
 */
class UploadController extends Controller
{
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
        'image/gif' => 'gif',
        'image/heic' => 'heic',
        'image/heif' => 'heif',
        'image/bmp' => 'bmp',
        'image/tiff' => 'tiff',
        'application/pdf' => 'pdf',
    ];

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:'.config('imgk.max_upload_kb')],
        ]);

        $file = $request->file('file');
        $mime = $file->getMimeType();

        if (! isset(self::EXTENSIONS[$mime])) {
            throw new ImgkException("Format non accepté ({$mime}). Images ou PDF uniquement.", 415);
        }

        $name = (string) Str::ulid();
        $ext = self::EXTENSIONS[$mime];

        Storage::disk('local')->putFileAs(config('imgk.uploads_dir'), $file, "{$name}.{$ext}");

        return response()->json([
            'url' => url("/u/{$name}.{$ext}"),
            'bytes' => $file->getSize(),
            'mime' => $mime,
        ], 201);
    }

    public function show(string $file): Response
    {
        if (! preg_match('/^([0-9A-Za-z]{26})\.([a-z]+)$/', $file, $m)) {
            abort(404);
        }

        $path = config('imgk.uploads_dir')."/{$file}";
        $disk = Storage::disk('local');

        if (! $disk->exists($path)) {
            abort(404);
        }

        $mime = array_search($m[2], self::EXTENSIONS, true) ?: 'application/octet-stream';

        return response($disk->get($path), 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'Content-Disposition' => 'inline; filename="'.$file.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }
}
