<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Redimensionne et compresse les photos produit à l'upload, pour éviter que
 * des photos de 4 Mo prises au téléphone ne ralentissent le catalogue.
 * Les fichiers sont convertis en WebP (bon compromis poids/qualité,
 * largement supporté par les navigateurs actuels).
 */
class ImageOptimizer
{
    private const MAX_WIDTH = 1600;
    private const QUALITY = 78;

    public function __construct(private readonly ImageManager $manager = new ImageManager(new Driver()))
    {
    }

    /**
     * Optimise puis stocke l'image sur le disque indiqué.
     * Retourne le chemin du fichier stocké (à enregistrer en base).
     */
    public function storeOptimized(UploadedFile $file, string $directory, string $disk = 'public'): string
    {
        $image = $this->manager->read($file->getRealPath());

        // On ne réduit que les images plus larges que MAX_WIDTH ; scaleDown()
        // ne les agrandit jamais, ce qui évite de dégrader une petite image.
        $image->scaleDown(width: self::MAX_WIDTH);

        $encoded = $image->toWebp(self::QUALITY);

        $path = trim($directory, '/') . '/' . Str::uuid() . '.webp';

        Storage::disk($disk)->put($path, (string) $encoded);

        return $path;
    }
}
