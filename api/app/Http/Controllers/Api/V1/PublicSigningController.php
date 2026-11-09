<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Documents\RemoteSigning;
use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\Documents\PetDocument;
use App\Models\Documents\PetDocumentRequest;
use App\Support\ClinicTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The page the owner opens from the link to sign remotely (D3). No login:
 * the link (one use, 48 h) plus the last 4 digits of the ID document.
 */
class PublicSigningController extends Controller
{
    public function show(string $signToken, RemoteSigning $remote): JsonResponse
    {
        return $remote->withToken($signToken, function (PetDocumentRequest $req, PetDocument $doc) {
            $clinic = Clinic::findOrFail($doc->clinic_id);
            $s = $doc->snapshot;

            // The text names the owner's ID number, and its last 4 digits are what proves who signs:
            // whoever has the link must not read them here. Masked on this page only (the PDF keeps it).
            $number = (string) ($s['owner']['document_number'] ?? '');
            $mask = fn (string $text) => $number === '' ? $text : str_replace($number, str_repeat('•', strlen($number)), $text);
            $s['blocks'] = array_map(fn ($b) => $b['type'] === 'list' ? ['type' => 'list', 'items' => array_map($mask, $b['items'])] : ['type' => 'p', 'text' => $mask($b['text'])], $s['blocks']);

            return response()->json(['data' => [
                'title' => $s['title'],
                'date' => $s['date'],
                'clinic' => ['name' => $s['clinic']['name'], 'tax_id' => $s['clinic']['tax_id'], 'address' => $s['clinic']['address'], 'phone' => $s['clinic']['phone']],
                'owner_first_name' => (string) Str::of($s['owner']['name'])->trim()->explode(' ')->first(),
                'owner_name' => $s['owner']['name'],
                'pet' => $s['pet'],
                'vet' => ['name' => $s['vet']['name'], 'license' => $s['vet']['license']],
                'blocks' => $s['blocks'],
                'expires_at' => ClinicTime::iso($req->expires_at, $clinic),
                'attempts_left' => PetDocumentRequest::MAX_ATTEMPTS - $req->attempts,
            ]]);
        });
    }

    public function sign(Request $request, string $signToken, RemoteSigning $remote): JsonResponse
    {
        $data = $request->validate([
            'document_last4' => ['required', 'digits:4'],
            'image' => ['required', 'string', 'max:1000000'],
            'accepted' => ['accepted'],
        ], [
            'accepted.accepted' => 'Confirma que leíste y aceptas el documento.',
            'document_last4.digits' => 'Escribe los últimos 4 dígitos de tu documento.',
        ], ['image' => 'firma', 'document_last4' => 'dígitos del documento']);

        $doc = $remote->signWithToken($signToken, $data['document_last4'], $data['image'], $request);

        return response()->json(['data' => ['status' => $doc->status, 'signed' => true]]);
    }
}
