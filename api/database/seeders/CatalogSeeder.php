<?php

namespace Database\Seeders;

use App\Models\Catalogs\Breed;
use App\Models\Catalogs\ClinicalAlert;
use App\Models\Catalogs\ProcedureType;
use App\Models\Catalogs\Species;
use App\Models\Catalogs\Vaccine;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Global catalogs (clinic_id NULL). Idempotent: safe on every deploy;
 * adds missing rows and never deletes what clinics may be using.
 * Vaccines are listed by type (not brand): the brand/manufacturer and
 * lot are recorded on each vaccination.
 */
class CatalogSeeder extends Seeder
{
    private const SPECIES = [
        'canine' => ['Canino', 1],
        'feline' => ['Felino', 2],
        'rabbit' => ['Conejo', 3],
        'rodent' => ['Roedor', 4],
        'bird' => ['Ave', 5],
        'reptile' => ['Reptil', 6],
        'ferret' => ['Hurón', 7],
        'equine' => ['Equino', 8],
        'bovine' => ['Bovino', 9],
        'porcine' => ['Porcino', 10],
        'caprine_ovine' => ['Caprino / Ovino', 11],
        'other' => ['Otro', 99],
    ];

    private const BREEDS = [
        'canine' => [
            'Criollo', 'Labrador Retriever', 'Golden Retriever', 'French Poodle', 'Pinscher Miniatura', 'Shih Tzu',
            'Yorkshire Terrier', 'Schnauzer Miniatura', 'Beagle', 'Pug', 'Bulldog Francés', 'Bulldog Inglés',
            'Chihuahua', 'Pastor Alemán', 'Pastor Belga Malinois', 'Rottweiler', 'Husky Siberiano', 'Border Collie',
            'Cocker Spaniel', 'Dachshund', 'Maltés', 'Pomerania', 'Boxer', 'Doberman', 'Pitbull Terrier',
            'American Bully', 'Bull Terrier', 'Jack Russell Terrier', 'Shar Pei', 'Chow Chow', 'Gran Danés',
            'San Bernardo', 'Akita', 'Samoyedo', 'Weimaraner', 'Dálmata', 'Basset Hound', 'Cavalier King Charles',
            'Bichón Frisé', 'Westie (West Highland)', 'Corgi', 'Mestizo',
        ],
        'feline' => [
            'Criollo (Doméstico pelo corto)', 'Doméstico pelo largo', 'Persa', 'Siamés', 'Angora Turco', 'Maine Coon',
            'Bengalí', 'Ragdoll', 'British Shorthair', 'Azul Ruso', 'Esfinge', 'Himalayo', 'Scottish Fold', 'Mestizo',
        ],
        'rabbit' => ['Mini Lop', 'Cabeza de León', 'Holland Lop', 'Rex', 'Enano Holandés', 'Mestizo'],
        'rodent' => ['Hámster', 'Cuy (Cobaya)', 'Chinchilla', 'Rata doméstica', 'Ratón', 'Jerbo'],
        'bird' => ['Canario', 'Periquito australiano', 'Cacatúa', 'Loro', 'Agapornis', 'Gallina', 'Pato'],
        'reptile' => ['Tortuga', 'Iguana', 'Gecko', 'Serpiente'],
    ];

    /** [name, species codes, protects against, booster days] */
    private const VACCINES = [
        ['Rabia', ['canine', 'feline', 'ferret', 'equine', 'bovine'], 'Virus de la rabia', 365],
        ['Moquillo y Parvovirus (Puppy)', ['canine'], 'Distemper canino, parvovirus canino', 21],
        ['Polivalente canina (DHPPi)', ['canine'], 'Distemper, hepatitis, parvovirus, parainfluenza', 365],
        ['Polivalente canina con Leptospira (DHPPi+L)', ['canine'], 'Distemper, hepatitis, parvovirus, parainfluenza, leptospirosis', 365],
        ['Leptospirosis', ['canine'], 'Leptospira spp.', 365],
        ['Tos de las perreras (Bordetella / Parainfluenza)', ['canine'], 'Bordetella bronchiseptica, parainfluenza', 365],
        ['Coronavirus canino', ['canine'], 'Coronavirus canino', 365],
        ['Triple felina (FVRCP)', ['feline'], 'Rinotraqueítis, calicivirus, panleucopenia', 365],
        ['Leucemia felina (FeLV)', ['feline'], 'Virus de la leucemia felina', 365],
        ['Mixomatosis y enfermedad hemorrágica (RHD)', ['rabbit'], 'Mixomatosis, enfermedad hemorrágica viral', 365],
    ];

    /** [name, category] */
    private const PROCEDURES = [
        ['Esterilización hembra (ovariohisterectomía)', 'surgery'],
        ['Castración macho (orquiectomía)', 'surgery'],
        ['Cesárea', 'surgery'],
        ['Cirugía de tejidos blandos', 'surgery'],
        ['Extirpación de masa / tumor', 'surgery'],
        ['Cistotomía (cálculos urinarios)', 'surgery'],
        ['Enucleación', 'surgery'],
        ['Amputación', 'surgery'],
        ['Osteosíntesis (fractura)', 'surgery'],
        ['Hernioplastia', 'surgery'],
        ['Profilaxis dental', 'procedure'],
        ['Extracción dental', 'procedure'],
        ['Sutura de herida', 'procedure'],
        ['Drenaje de absceso', 'procedure'],
        ['Curación', 'procedure'],
        ['Limpieza de oídos', 'procedure'],
        ['Corte de uñas', 'procedure'],
        ['Vaciado de glándulas perianales', 'procedure'],
        ['Fluidoterapia', 'procedure'],
        ['Sedación', 'procedure'],
        ['Implante de microchip', 'procedure'],
        ['Eutanasia', 'procedure'],
        ['Ecografía', 'diagnostic'],
        ['Radiografía', 'diagnostic'],
        ['Electrocardiograma', 'diagnostic'],
        ['Citología', 'diagnostic'],
        ['Biopsia', 'diagnostic'],
        ['Toma de muestra de sangre', 'diagnostic'],
        ['Raspado de piel', 'diagnostic'],
        ['Test rápido (parvovirus, moquillo, FIV/FeLV, ehrlichia…)', 'diagnostic'],
    ];

    /** [name, type, species codes ([] = any)] */
    private const ALERTS = [
        ['Ehrlichiosis', 'infectious_disease', ['canine']],
        ['Anaplasmosis', 'infectious_disease', ['canine']],
        ['Babesiosis', 'infectious_disease', ['canine']],
        ['Leishmaniosis', 'infectious_disease', ['canine']],
        ['Dirofilariosis', 'infectious_disease', ['canine', 'feline']],
        ['Brucelosis canina', 'infectious_disease', ['canine']],
        ['Parvovirus', 'infectious_disease', ['canine']],
        ['Moquillo (distemper)', 'infectious_disease', ['canine']],
        ['Leptospirosis', 'infectious_disease', ['canine']],
        ['Inmunodeficiencia felina (FIV)', 'infectious_disease', ['feline']],
        ['Leucemia felina (FeLV)', 'infectious_disease', ['feline']],
        ['Peritonitis infecciosa felina (PIF)', 'infectious_disease', ['feline']],
        ['Rabia', 'infectious_disease', []],
        ['Diabetes mellitus', 'chronic_condition', []],
        ['Epilepsia', 'chronic_condition', []],
        ['Enfermedad renal crónica', 'chronic_condition', []],
        ['Cardiopatía', 'chronic_condition', []],
        ['Hipotiroidismo', 'chronic_condition', ['canine']],
        ['Hipertiroidismo', 'chronic_condition', ['feline']],
        ['Hiperadrenocorticismo (Cushing)', 'chronic_condition', ['canine']],
        ['Dermatitis atópica', 'chronic_condition', []],
        ['Alergia a medicamentos', 'allergy', []],
        ['Alergia alimentaria', 'allergy', []],
        ['Agresivo a la manipulación', 'behavior', []],
        ['Requiere bozal', 'behavior', ['canine']],
    ];

    public function run(TenantContext $tenant): void
    {
        $tenant->withoutTenancy('seeding global catalogs', function () {
            $species = $this->seedSpecies();
            $ids = fn (array $codes) => array_values(array_map(fn (string $code) => $species[$code], $codes));

            foreach (self::BREEDS as $code => $names) {
                foreach ($names as $name) {
                    Breed::query()->firstOrCreate(['clinic_id' => null, 'species_id' => $species[$code], 'name' => $name]);
                }
            }

            foreach (self::VACCINES as [$name, $codes, $against, $booster]) {
                Vaccine::query()->firstOrCreate(
                    ['clinic_id' => null, 'name' => $name],
                    ['species_ids' => $ids($codes), 'protects_against' => $against, 'default_booster_days' => $booster],
                );
            }

            foreach (self::PROCEDURES as [$name, $category]) {
                ProcedureType::query()->firstOrCreate(['clinic_id' => null, 'name' => $name], ['category' => $category]);
            }

            foreach (self::ALERTS as [$name, $type, $codes]) {
                ClinicalAlert::query()->firstOrCreate(
                    ['clinic_id' => null, 'name' => $name],
                    ['type' => $type, 'species_ids' => $ids($codes)],
                );
            }
        });
    }

    /**
     * @return array<string, string> code => id
     */
    private function seedSpecies(): array
    {
        $ids = [];

        foreach (self::SPECIES as $code => [$name, $sort]) {
            $ids[$code] = Species::query()->firstOrCreate(
                ['clinic_id' => null, 'code' => $code],
                ['name' => $name, 'sort' => $sort],
            )->id;
        }

        return $ids;
    }
}
