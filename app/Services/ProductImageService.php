<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Format;
use Intervention\Image\Alignment;

class ProductImageService
{
  // Lado del lienzo cuadrado final — todas las imágenes guardadas miden exactamente esto
  private const CANVAS_SIZE = 500;

  // Margen interno en píxeles: evita que la foto toque los bordes del cuadro,
  // da aire visual consistente sin importar la composición original
  private const PADDING = 32;

  // Fondo del lienzo — el mismo tono "paper" de la paleta de la app, para que
  // las fotos con fondo transparente o blanco se integren sin verse "pegadas"
  private const BACKGROUND_COLOR = '#FAF7F2';

  private const WEBP_QUALITY = 85;

  private ImageManager $manager;

  public function __construct()
  {
    $this->manager = new ImageManager(new Driver());
  }

  public function store(UploadedFile $file): string
  {
    $image = $this->manager->decode($file);

    // Reduce la foto para que quepa dentro del área útil (lienzo menos el margen),
    // manteniendo su proporción original — nunca la deforma ni la agranda
    $maxContentSize = self::CANVAS_SIZE - (self::PADDING * 2);
    $image->scaleDown(width: $maxContentSize, height: $maxContentSize);

    // Crea el lienzo cuadrado fijo con el fondo sólido de marca
    $canvas = $this->manager->createImage(self::CANVAS_SIZE, self::CANVAS_SIZE)
    ->fill(self::BACKGROUND_COLOR);

    // Centra la foto ya redimensionada sobre el lienzo — este paso es el que
    // resuelve la inconsistencia visual: el resultado SIEMPRE es un cuadro
    // perfecto, con el mismo margen, sin importar la foto original
    $canvas->insert($image, alignment: Alignment::CENTER);

    $filename = 'products/' . Str::uuid() . '.webp';

    $encoded = $canvas->encodeUsingFormat(Format::WEBP, quality: self::WEBP_QUALITY);

    Storage::disk('public')->put($filename, (string) $encoded);

    return $filename;
  }

  public function replace(?string $oldPath, UploadedFile $newFile): string
  {
    $newPath = $this->store($newFile);

    if ($oldPath) {
      Storage::disk('public')->delete($oldPath);
    }

    return $newPath;
  }

  public function delete(?string $path): void
  {
    if ($path) {
      Storage::disk('public')->delete($path);
    }
  }
}
