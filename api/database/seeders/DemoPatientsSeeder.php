<?php

namespace Database\Seeders;

use App\Actions\Patients\RecordConsents;
use App\Models\Catalogs\Breed;
use App\Models\Catalogs\Species;
use App\Models\Clinic;
use App\Models\Owner;
use App\Models\Pet;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Demo tutors and pets for Veterinaria Anipet (local only). Idempotent.
 * All data is fictitious.
 */
class DemoPatientsSeeder extends Seeder
{
    private const OWNERS = [
        ['1098745312', 'Carolina Restrepo Gómez', '+573157482096', 'Carrera 33 # 45-12', [
            ['Luna', 'canine', 'Labrador Retriever', 'female', '2022-05-14', 'neutered', 'large', 'docile', 27.4],
        ]],
        ['91345678', 'Andrés Felipe Mejía', '+573004561234', 'Calle 52 # 31-40', [
            ['Tobías', 'canine', 'Criollo', 'male', '2019-11-02', 'neutered', 'medium', 'reactive', 18.2],
            ['Michi', 'feline', 'Criollo (Doméstico pelo corto)', 'female', '2021-03-20', 'neutered', 'small', 'reserved', 4.1],
        ]],
        ['1095812233', 'Paola Andrea Díaz', '+573186665544', 'Diagonal 15 # 60-25', [
            ['Rocky', 'canine', 'Bulldog Francés', 'male', '2023-08-01', 'intact', 'small', 'very_docile', 11.8],
        ]],
        ['63512987', 'Luz Marina Pérez', '+573123334455', 'Calle 36 # 27-18', [
            ['Kira', 'canine', 'Schnauzer Miniatura', 'female', '2016-01-10', 'neutered', 'small', 'docile', 8.3],
            ['Canela', 'rabbit', 'Mini Lop', 'female', '2024-02-15', 'unknown', 'toy', 'docile', 1.6],
        ]],
    ];

    public function run(TenantContext $tenant, RecordConsents $recordConsents): void
    {
        // Demo data has known logins ("password"): never in production, even if run by hand.
        if (! app()->environment('local', 'testing')) {
            throw new \RuntimeException('Datos de demostración: solo en local.');
        }

        $clinic = Clinic::where('slug', 'anipet')->first();

        if ($clinic === null) {
            return;
        }

        $tenant->runAs($clinic->id, function () use ($recordConsents) {
            foreach (self::OWNERS as [$document, $name, $phone, $address, $pets]) {
                if (Owner::where('document_number', $document)->exists()) {
                    continue;
                }

                $owner = Owner::create([
                    'document_type' => 'CC', 'document_number' => $document, 'full_name' => $name,
                    'mobile_phone' => $phone, 'address' => $address, 'municipality_code' => '68001',
                ]);
                $recordConsents($owner, ['data_processing' => true, 'whatsapp' => true], 'imported', null);

                foreach ($pets as [$petName, $species, $breed, $sex, $birth, $repro, $size, $temperament, $weight]) {
                    $speciesId = Species::where('code', $species)->value('id');

                    Pet::create([
                        'owner_id' => $owner->id, 'name' => $petName, 'species_id' => $speciesId,
                        'breed_id' => Breed::where('species_id', $speciesId)->where('name', $breed)->value('id'),
                        'sex' => $sex, 'birth_date' => $birth, 'reproductive_status' => $repro,
                        'size' => $size, 'temperament' => $temperament, 'weight_kg' => $weight,
                    ]);
                }
            }
        });
    }
}
