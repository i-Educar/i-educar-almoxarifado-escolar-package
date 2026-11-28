<?php

namespace iEducar\Packages\BuritiAlmoxarifadoEscolar\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Foto do item do catálogo — disco public, caminho relativo no payload.
 */
final class ItemFoto
{
    public const string DISCO = 'public';

    public const string PASTA = 'almoxarifado/itens';

    /** @var list<string> */
    public const array MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    public static function salvar(UploadedFile $arquivo, string $sku): string
    {
        $sku = Str::slug(strtoupper(trim($sku)), '-');
        if ($sku === '') {
            $sku = 'item';
        }

        $mime = (string) ($arquivo->getMimeType() ?: '');
        if (! in_array($mime, self::MIMES, true)) {
            throw new RuntimeException('Envie uma imagem JPG, PNG, WEBP ou GIF.');
        }
        if ($arquivo->getSize() > 3 * 1024 * 1024) {
            throw new RuntimeException('A foto deve ter no máximo 3 MB.');
        }

        $ext = match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'jpg',
        };

        $nome = $sku.'-'.Str::lower(Str::random(8)).'.'.$ext;
        $path = $arquivo->storeAs(self::PASTA, $nome, self::DISCO);
        if (! is_string($path) || $path === '') {
            throw new RuntimeException('Não foi possível gravar a foto do item.');
        }

        return $path;
    }

    public static function url(?string $path): ?string
    {
        $path = self::normalizar($path);
        if ($path === null) {
            return null;
        }

        return url('/almoxarifado-escolar/itens/foto?path='.rawurlencode($path));
    }

    public static function existe(?string $path): bool
    {
        $path = self::normalizar($path);
        if ($path === null) {
            return false;
        }

        try {
            return Storage::disk(self::DISCO)->exists($path);
        } catch (Throwable) {
            return false;
        }
    }

    public static function remover(?string $path): void
    {
        $path = self::normalizar($path);
        if ($path === null) {
            return;
        }
        try {
            if (Storage::disk(self::DISCO)->exists($path)) {
                Storage::disk(self::DISCO)->delete($path);
            }
        } catch (Throwable) {
            //
        }
    }

    /** Conteúdo binário + mime para streaming. */
    /** @return array{bin:string,mime:string}|null */
    public static function ler(?string $path): ?array
    {
        $path = self::normalizar($path);
        if ($path === null || ! self::existe($path)) {
            return null;
        }
        try {
            $bin = Storage::disk(self::DISCO)->get($path);
            if (! is_string($bin) || $bin === '') {
                return null;
            }
            $mime = Storage::disk(self::DISCO)->mimeType($path) ?: 'image/jpeg';

            return ['bin' => $bin, 'mime' => $mime];
        } catch (Throwable) {
            return null;
        }
    }

    private static function normalizar(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }
        $path = str_replace('\\', '/', $path);
        $path = ltrim($path, '/');
        if (str_contains($path, '..') || ! str_starts_with($path, self::PASTA.'/')) {
            return null;
        }

        return $path;
    }
}
