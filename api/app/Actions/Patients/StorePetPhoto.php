<?php

namespace App\Actions\Patients;

use App\Models\Pet;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Re-encodes the uploaded photo (max 800 px, WebP) and stores it on the private
 * disk under the clinic's folder. Re-encoding drops EXIF (GPS of the owner's
 * home) and anything that is not really an image.
 */
class StorePetPhoto
{
    private const MAX_SIDE = 800;

    public function __invoke(Pet $pet, UploadedFile $file): Pet
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if ($source === false) {
            throw ValidationException::withMessages(['photo' => 'El archivo no es una imagen válida.']);
        }

        $source = $this->orient($source, $file);
        [$width, $height] = [imagesx($source), imagesy($source)];
        $scale = min(1, self::MAX_SIDE / max($width, $height));
        $image = imagescale($source, max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)), IMG_BICUBIC);
        imagedestroy($source);

        ob_start();
        imagewebp($image, null, 82);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        $disk = Storage::disk(config('filesystems.attachments_disk'));
        $path = "clinics/{$pet->clinic_id}/pets/{$pet->id}/".Str::uuid7().'.webp';
        $disk->put($path, $bytes);

        if ($pet->photo_path !== null) {
            $disk->delete($pet->photo_path);
        }

        $pet->forceFill(['photo_path' => $path])->save();

        return $pet;
    }

    /** Phone photos are often stored sideways with an EXIF rotation flag. */
    private function orient(\GdImage $image, UploadedFile $file): \GdImage
    {
        if (! function_exists('exif_read_data') || ! in_array($file->getMimeType(), ['image/jpeg'], true)) {
            return $image;
        }

        $orientation = @exif_read_data($file->getRealPath())['Orientation'] ?? 1;
        $angle = match ((int) $orientation) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        return $angle === 0 ? $image : (imagerotate($image, $angle, 0) ?: $image);
    }
}
