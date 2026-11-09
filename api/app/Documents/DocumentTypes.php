<?php

namespace App\Documents;

/**
 * Consent and authorization templates (Documentos y firmas, D1).
 *
 * Each type defines the case fields the vet fills in, who signs, and the text.
 * The text uses {{placeholders}} filled from the clinic, owner, pet and vet at
 * the moment the document is created ({{campo.x}} = a case field).
 *
 * LEGAL: these are reasonable base texts, NOT reviewed by a lawyer yet. They
 * must be reviewed before real clinics use them (docs/documentos.md). Change
 * the text here and bump `version`: signed documents keep the text they were
 * signed with (it is frozen in the document), so old signatures are never
 * affected.
 */
final class DocumentTypes
{
    /** Body cremation / disposal options (euthanasia). */
    private const DISPOSITION = [
        'cremation_individual' => 'Cremación individual, con entrega de las cenizas al tutor',
        'cremation_collective' => 'Cremación colectiva, sin entrega de cenizas',
        'owner_takes' => 'El tutor retira el cuerpo para disponer de él por su cuenta',
        'clinic_handles' => 'Disposición sanitaria del cuerpo a cargo de la clínica',
    ];

    private const ANESTHESIA = [
        'general_inhaled' => 'Anestesia general inhalada',
        'general_injectable' => 'Anestesia general inyectable',
        'sedation' => 'Sedación',
        'local' => 'Anestesia local o regional',
    ];

    /**
     * @return array<string, array{
     *   name: string, short: string, description: string, version: int,
     *   signers: array<string, array{label: string, required: bool}>,
     *   fields: list<array{key: string, label: string, type: string, required?: bool, options?: array<string, string>, help?: string, placeholder?: string}>,
     *   body: list<array{text?: string, list?: list<string>, when?: string}>
     * }>
     */
    public static function all(): array
    {
        return [
            'euthanasia' => [
                'name' => 'Consentimiento informado para eutanasia',
                'short' => 'Eutanasia',
                'description' => 'Autorización del tutor para la eutanasia humanitaria y el destino del cuerpo.',
                'version' => 1,
                'signers' => [
                    'owner' => ['label' => 'Tutor o responsable', 'required' => true],
                    'vet' => ['label' => 'Médico veterinario', 'required' => true],
                    'witness' => ['label' => 'Testigo', 'required' => false],
                ],
                'fields' => [
                    ['key' => 'diagnosis', 'label' => 'Diagnóstico y estado actual', 'type' => 'textarea', 'required' => true, 'placeholder' => 'Ej.: Insuficiencia renal crónica en estadio terminal, sin respuesta al tratamiento.'],
                    ['key' => 'reason', 'label' => 'Motivo de la eutanasia', 'type' => 'textarea', 'required' => true, 'placeholder' => 'Ej.: Evitar sufrimiento: dolor no controlable y mala calidad de vida.'],
                    ['key' => 'disposition', 'label' => 'Destino del cuerpo', 'type' => 'select', 'required' => true, 'options' => self::DISPOSITION],
                    ['key' => 'owner_present', 'label' => 'El tutor desea estar presente durante el procedimiento', 'type' => 'checkbox'],
                    ['key' => 'cost', 'label' => 'Valor del procedimiento', 'type' => 'money', 'help' => 'Opcional. Incluye la disposición del cuerpo si aplica.'],
                ],
                'body' => [
                    ['text' => 'Yo, {{tutor}}, identificado(a) con {{tutor_doc}}, en calidad de tutor(a) o responsable de la mascota {{mascota}} ({{especie}}, {{raza}}, {{sexo}}, {{edad}}, {{peso}}), declaro que:'],
                    ['list' => [
                        'El médico veterinario {{veterinario}} me explicó de forma clara el diagnóstico y estado actual de {{mascota}}: {{campo.diagnosis}}',
                        'Entiendo el motivo por el cual se considera la eutanasia: {{campo.reason}}',
                        'Me informaron las alternativas disponibles, incluido continuar con tratamiento o cuidados paliativos, y tuve la oportunidad de hacer todas las preguntas que consideré necesarias.',
                        'Entiendo que la eutanasia es un procedimiento irreversible que produce la muerte de la mascota, y que se realiza con sedación previa para evitarle dolor y angustia.',
                        'Tomo esta decisión de forma libre y voluntaria, sin presión de ninguna persona, y soy la persona facultada para autorizarla.',
                    ]],
                    ['text' => 'En consecuencia, AUTORIZO a {{clinica}} y al médico veterinario {{veterinario}} para practicar la eutanasia humanitaria de {{mascota}}.'],
                    ['text' => 'Destino del cuerpo: {{campo.disposition}}.'],
                    ['text' => 'Deseo estar presente durante el procedimiento.', 'when' => 'owner_present'],
                    ['text' => 'Valor del procedimiento: {{campo.cost}}, que me comprometo a pagar.', 'when' => 'cost'],
                    ['text' => 'Declaro que la información que suministré sobre la mascota y sobre mí es verdadera.'],
                ],
            ],

            'surgery' => [
                'name' => 'Consentimiento informado para cirugía y anestesia',
                'short' => 'Cirugía y anestesia',
                'description' => 'Autorización del procedimiento quirúrgico, la anestesia y sus riesgos.',
                'version' => 1,
                'signers' => [
                    'owner' => ['label' => 'Tutor o responsable', 'required' => true],
                    'vet' => ['label' => 'Médico veterinario', 'required' => true],
                ],
                'fields' => [
                    ['key' => 'procedure', 'label' => 'Procedimiento', 'type' => 'text', 'required' => true, 'placeholder' => 'Ej.: Ovariohisterectomía (esterilización)'],
                    ['key' => 'anesthesia', 'label' => 'Tipo de anestesia', 'type' => 'select', 'required' => true, 'options' => self::ANESTHESIA],
                    ['key' => 'specific_risks', 'label' => 'Riesgos particulares de este paciente', 'type' => 'textarea', 'help' => 'Opcional: edad, enfermedades previas, resultados de laboratorio…'],
                    ['key' => 'fasting', 'label' => 'Indicaciones previas (ayuno, medicamentos)', 'type' => 'textarea', 'placeholder' => 'Ej.: Ayuno de comida de 8 horas y de agua de 2 horas.'],
                    ['key' => 'cost', 'label' => 'Costo estimado', 'type' => 'money'],
                    ['key' => 'extra_amount', 'label' => 'Monto adicional autorizado ante complicaciones', 'type' => 'money', 'help' => 'Hasta cuánto puede gastar la clínica sin volver a consultar al tutor.'],
                ],
                'body' => [
                    ['text' => 'Yo, {{tutor}}, identificado(a) con {{tutor_doc}}, en calidad de tutor(a) o responsable de la mascota {{mascota}} ({{especie}}, {{raza}}, {{sexo}}, {{edad}}, {{peso}}), AUTORIZO a {{clinica}} y al médico veterinario {{veterinario}} para realizar el procedimiento: {{campo.procedure}}, con {{campo.anesthesia}}.'],
                    ['text' => 'Declaro que me explicaron en qué consiste el procedimiento, sus beneficios, las alternativas y los riesgos, entre ellos:'],
                    ['list' => [
                        'Reacciones adversas a la anestesia o a los medicamentos, que en casos poco frecuentes pueden causar la muerte aun en pacientes sanos.',
                        'Hemorragia, infección, dehiscencia (apertura) de la herida u otras complicaciones durante la recuperación.',
                        'La posible necesidad de procedimientos adicionales no previstos.',
                    ]],
                    ['text' => 'Riesgos particulares de {{mascota}}: {{campo.specific_risks}}', 'when' => 'specific_risks'],
                    ['text' => 'Indicaciones previas: {{campo.fasting}}', 'when' => 'fasting'],
                    ['text' => 'Si durante el procedimiento se presenta una complicación o un hallazgo que requiera atención inmediata, autorizo al equipo a actuar según su criterio profesional para proteger la vida y el bienestar de la mascota.'],
                    ['text' => 'Costo estimado: {{campo.cost}}. Entiendo que puede variar según lo que se encuentre durante el procedimiento.', 'when' => 'cost'],
                    ['text' => 'Ante complicaciones, autorizo gastos adicionales hasta por {{campo.extra_amount}} sin necesidad de consultarme; por encima de ese valor deberán contactarme al {{tutor_cel}}.', 'when' => 'extra_amount'],
                    ['text' => 'Declaro que la información que suministré sobre la salud de la mascota es verdadera y completa, que pude hacer todas mis preguntas y que firmo de forma libre y voluntaria.'],
                ],
            ],

            'hospitalization' => [
                'name' => 'Autorización de hospitalización',
                'short' => 'Hospitalización',
                'description' => 'Ingreso del paciente, tratamientos, costos y contacto durante la hospitalización.',
                'version' => 1,
                'signers' => [
                    'owner' => ['label' => 'Tutor o responsable', 'required' => true],
                    'vet' => ['label' => 'Médico veterinario', 'required' => true],
                ],
                'fields' => [
                    ['key' => 'reason', 'label' => 'Motivo de la hospitalización', 'type' => 'textarea', 'required' => true],
                    ['key' => 'days', 'label' => 'Días estimados', 'type' => 'number'],
                    ['key' => 'daily_cost', 'label' => 'Valor por día', 'type' => 'money'],
                    ['key' => 'deposit', 'label' => 'Anticipo', 'type' => 'money'],
                    ['key' => 'contact', 'label' => 'Contacto para novedades', 'type' => 'text', 'help' => 'Si se deja vacío, se usa el celular del tutor.'],
                ],
                'body' => [
                    ['text' => 'Yo, {{tutor}}, identificado(a) con {{tutor_doc}}, en calidad de tutor(a) o responsable de la mascota {{mascota}} ({{especie}}, {{raza}}, {{sexo}}, {{edad}}, {{peso}}), AUTORIZO su hospitalización en {{clinica}} bajo la responsabilidad del médico veterinario {{veterinario}}.'],
                    ['text' => 'Motivo: {{campo.reason}}'],
                    ['text' => 'Tiempo estimado: {{campo.days}} día(s). Puede cambiar según la evolución del paciente.', 'when' => 'days'],
                    ['text' => 'Autorizo los exámenes, medicamentos y procedimientos que el equipo considere necesarios para el tratamiento. Si se requiere un procedimiento de riesgo (por ejemplo una cirugía), me consultarán antes, salvo una emergencia que ponga en peligro la vida de la mascota.'],
                    ['text' => 'Valor por día: {{campo.daily_cost}}, más los medicamentos, exámenes y procedimientos adicionales.', 'when' => 'daily_cost'],
                    ['text' => 'Anticipo entregado: {{campo.deposit}}.', 'when' => 'deposit'],
                    ['text' => 'Entiendo que, a pesar de los cuidados, el estado de la mascota puede empeorar e incluso puede fallecer durante la hospitalización.'],
                    ['text' => 'Para informar novedades me pueden contactar al {{contacto}}. Me comprometo a estar disponible y a recoger a la mascota cuando se me indique el alta.'],
                ],
            ],

            'voluntary_discharge' => [
                'name' => 'Alta voluntaria (retiro contra recomendación médica)',
                'short' => 'Alta voluntaria',
                'description' => 'El tutor retira al paciente en contra de la recomendación del veterinario y asume los riesgos.',
                'version' => 1,
                'signers' => [
                    'owner' => ['label' => 'Tutor o responsable', 'required' => true],
                    'vet' => ['label' => 'Médico veterinario', 'required' => true],
                    'witness' => ['label' => 'Testigo', 'required' => false],
                ],
                'fields' => [
                    ['key' => 'recommendation', 'label' => 'Lo que recomendó el veterinario', 'type' => 'textarea', 'required' => true, 'placeholder' => 'Ej.: Continuar hospitalizado 3 días con fluidoterapia y monitoreo.'],
                    ['key' => 'risks', 'label' => 'Riesgos explicados al tutor', 'type' => 'textarea', 'required' => true, 'placeholder' => 'Ej.: Deshidratación, empeoramiento del cuadro, muerte.'],
                    ['key' => 'reason', 'label' => 'Motivo del tutor para el retiro', 'type' => 'textarea', 'help' => 'Opcional.'],
                ],
                'body' => [
                    ['text' => 'Yo, {{tutor}}, identificado(a) con {{tutor_doc}}, en calidad de tutor(a) o responsable de la mascota {{mascota}} ({{especie}}, {{raza}}, {{sexo}}, {{edad}}, {{peso}}), declaro que retiro a la mascota de {{clinica}} por decisión propia y en contra de la recomendación del médico veterinario {{veterinario}}.'],
                    ['text' => 'El médico veterinario me recomendó: {{campo.recommendation}}'],
                    ['text' => 'Me explicaron los riesgos de suspender la atención: {{campo.risks}}'],
                    ['text' => 'Motivo del retiro: {{campo.reason}}', 'when' => 'reason'],
                    ['text' => 'Asumo toda la responsabilidad por el estado de salud de la mascota desde este momento y por las consecuencias de esta decisión, y libero a {{clinica}} y a su equipo de cualquier responsabilidad derivada de ella.'],
                    ['text' => 'Firmo de forma libre y voluntaria, después de haber podido hacer todas mis preguntas.'],
                ],
            ],
        ];
    }

    /**
     * Data that fills itself in any text: {{key}} => [label, example]. Case
     * fields are {{campo.key}} (see each type's `fields`).
     */
    public const PLACEHOLDERS = [
        'fecha' => ['Fecha del documento', '3 de octubre de 2026'],
        'clinica' => ['Nombre de la clínica', 'Veterinaria Anipet'],
        'clinica_nit' => ['NIT de la clínica', 'NIT 901482736-4'],
        'tutor' => ['Nombre del tutor', 'Paola Andrea Díaz'],
        'tutor_doc' => ['Documento del tutor', 'Cédula de ciudadanía 1095812233'],
        'tutor_cel' => ['Celular del tutor', '318 666 5544'],
        'mascota' => ['Nombre de la mascota', 'Rocky'],
        'especie' => ['Especie', 'canino'],
        'raza' => ['Raza', 'Bulldog Francés'],
        'sexo' => ['Sexo', 'macho'],
        'edad' => ['Edad', '3 años y 2 meses'],
        'peso' => ['Peso', '11,8 kg'],
        'microchip' => ['Microchip', 'sin microchip'],
        'veterinario' => ['Médico veterinario', 'Carolina Restrepo'],
        'veterinario_tp' => ['Tarjeta profesional', 'COMVEZCOL 18452'],
        'contacto' => ['Contacto para novedades', '318 666 5544'],
    ];

    /**
     * Placeholders used in a text that don't exist (typos like {{mascot}}).
     *
     * @return list<string>
     */
    public static function unknownPlaceholders(string $type, string $text): array
    {
        $fields = collect(self::get($type)['fields'] ?? [])->pluck('key')->all();
        preg_match_all('/\{\{\s*([^}]*?)\s*\}\}/', $text, $m);

        return array_values(array_unique(array_filter($m[1], fn (string $key) => ! (
            isset(self::PLACEHOLDERS[$key]) || (str_starts_with($key, 'campo.') && in_array(substr($key, 6), $fields, true))
        ))));
    }

    /** @return array<string, mixed>|null */
    public static function get(string $code): ?array
    {
        return self::all()[$code] ?? null;
    }

    public const ROLE_LABELS = ['owner' => 'Tutor o responsable', 'vet' => 'Médico veterinario', 'witness' => 'Testigo'];

    /** How each signature was collected (evidence page). */
    public const METHOD_LABELS = [
        'in_person' => 'En la clínica, en pantalla',
        'remote_link' => 'A distancia por enlace (verificó los últimos 4 dígitos de su documento)',
        'portal' => 'A distancia en el portal del tutor (sesión verificada)',
    ];
}
