<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Pets\CardDesign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The clinic in context: identity (letterhead, invoices later), logo and the
 * settings a clinic can tune.
 */
class ClinicController extends Controller
{
    /** Settings the clinic may change, with their allowed values. */
    private const EDITABLE_SETTINGS = ['edit_window_hours', 'prevent_overlaps', 'default_appointment_minutes'];

    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->payload($this->clinic($request))]);
    }

    public function update(Request $request): JsonResponse
    {
        $clinic = $this->clinic($request);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'min:3', 'max:150'],
            'legal_name' => ['nullable', 'string', 'max:200'],
            // NIT: 9 digits + verification digit (company) or a person's document.
            'tax_id' => ['nullable', 'string', 'max:20', 'regex:/^\d{5,15}(-\d)?$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:150'],
            'settings' => ['sometimes', 'array:'.implode(',', self::EDITABLE_SETTINGS)],
            // The edit window protects the clinical record: between 12 h and 72 h.
            'settings.edit_window_hours' => ['sometimes', 'integer', Rule::in([12, 24, 48, 72])],
            'settings.prevent_overlaps' => ['sometimes', 'boolean'],
            'settings.default_appointment_minutes' => ['sometimes', 'integer', Rule::in([15, 20, 30, 45, 60])],
        ], [
            'tax_id.regex' => 'Escribe el NIT solo con números y, si aplica, el dígito de verificación: 901234567-8.',
        ], [
            'name' => 'nombre', 'legal_name' => 'razón social', 'tax_id' => 'NIT', 'address' => 'dirección', 'website' => 'sitio web',
            'settings.edit_window_hours' => 'ventana de edición',
            'settings.default_appointment_minutes' => 'duración por defecto',
        ]);

        if (isset($data['settings'])) {
            $data['settings'] = array_merge($clinic->settings ?? [], $data['settings']);
        }

        $clinic->update($data);

        return response()->json(['data' => $this->payload($clinic)]);
    }

    /** Default design of the clinic's pet cards (each pet can still choose its own). */
    public function updatePetCard(Request $request): JsonResponse
    {
        $data = $request->validate(CardDesign::rules(), [], ['color' => 'color']);
        $clinic = $this->clinic($request);
        $clinic->update(['settings' => array_merge($clinic->settings ?? [], ['pet_card' => CardDesign::only($data)])]);

        return response()->json(['data' => CardDesign::forClinic($clinic)]);
    }

    /**
     * Re-encoded to PNG (keeps transparency, works in the PDF letterhead),
     * max 600 px, on the private disk.
     */
    public function storeLogo(Request $request): JsonResponse
    {
        $request->validate(['logo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:4096']], [], ['logo' => 'logo']);
        $clinic = $this->clinic($request);

        $source = @imagecreatefromstring((string) file_get_contents($request->file('logo')->getRealPath()));

        if ($source === false) {
            throw ValidationException::withMessages(['logo' => 'El archivo no es una imagen válida.']);
        }

        [$width, $height] = [imagesx($source), imagesy($source)];
        $scale = min(1, 600 / max($width, $height));
        $image = imagescale($source, max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)), IMG_BICUBIC);
        imagedestroy($source);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        ob_start();
        imagepng($image, null, 8);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        $disk = Storage::disk(config('filesystems.attachments_disk'));
        $path = "clinics/{$clinic->id}/logo/".Str::uuid7().'.png';
        $disk->put($path, $bytes);

        if ($clinic->logo_path !== null) {
            $disk->delete($clinic->logo_path);
        }

        $clinic->update(['logo_path' => $path]);

        return response()->json(['data' => $this->payload($clinic)]);
    }

    public function logo(Request $request): StreamedResponse
    {
        $clinic = $this->clinic($request);
        $disk = Storage::disk(config('filesystems.attachments_disk'));
        abort_if($clinic->logo_path === null || ! $disk->exists($clinic->logo_path), 404);

        return $disk->response($clinic->logo_path, null, ['Cache-Control' => 'private, max-age=31536000, immutable']);
    }

    public function destroyLogo(Request $request): JsonResponse
    {
        $clinic = $this->clinic($request);

        if ($clinic->logo_path !== null) {
            Storage::disk(config('filesystems.attachments_disk'))->delete($clinic->logo_path);
            $clinic->update(['logo_path' => null]);
        }

        return response()->json(['data' => $this->payload($clinic)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Clinic $clinic): array
    {
        return [
            'id' => $clinic->id,
            'name' => $clinic->name,
            'legal_name' => $clinic->legal_name,
            'tax_id' => $clinic->tax_id,
            'email' => $clinic->email,
            'phone' => $clinic->phone,
            'address' => $clinic->address,
            'website' => $clinic->website,
            'logo_url' => self::logoUrl($clinic),
            'timezone' => $clinic->timezone,
            'settings' => collect(self::EDITABLE_SETTINGS)->mapWithKeys(fn ($key) => [$key => $clinic->setting($key)]),
        ];
    }

    /** API path of the logo; the hash changes with every upload so it can be cached. */
    public static function logoUrl(Clinic $clinic): ?string
    {
        return $clinic->logo_path === null ? null : 'clinic/logo?v='.substr(md5($clinic->logo_path), 0, 8);
    }

    private function clinic(Request $request): Clinic
    {
        return $request->attributes->get('clinic');
    }
}
