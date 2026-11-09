<?php

/*
 * WhatsApp (docs/whatsapp.md). One shared Zooetia number for every clinic.
 *
 * WHATSAPP_DRIVER:
 *  - fake: development and until Meta approves the WABA. Nothing leaves the
 *          server: messages are written to the log and the `messages` table.
 *  - meta: WhatsApp Business Cloud API (needs the credentials below).
 */
return [
    'driver' => env('WHATSAPP_DRIVER', 'fake'),

    'meta' => [
        'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'waba_id' => env('WHATSAPP_WABA_ID'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),       // permanent system-user token
        'app_secret' => env('WHATSAPP_APP_SECRET'),           // validates webhook signatures (4d)
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),       // webhook subscription handshake (4d)
        'display_phone' => env('WHATSAPP_DISPLAY_PHONE'),
    ],

    /*
     * Template approved in Meta for each purpose. The body (and its {{n}}
     * variables, in this order) is in docs/whatsapp.md §3 and must match.
     */
    'templates' => [
        'appointment_reminder' => ['name' => env('WHATSAPP_TPL_APPOINTMENT', 'zooetia_recordatorio_cita'), 'language' => 'es_CO'],
        'next_control' => ['name' => env('WHATSAPP_TPL_NEXT_CONTROL', 'zooetia_recordatorio_control'), 'language' => 'es_CO'],
        // Online booking (docs/reservas.md, B2).
        'booking_received' => ['name' => env('WHATSAPP_TPL_BOOKING_RECEIVED', 'zooetia_solicitud_recibida'), 'language' => 'es_CO'],
        'booking_accepted' => ['name' => env('WHATSAPP_TPL_BOOKING_ACCEPTED', 'zooetia_solicitud_aceptada'), 'language' => 'es_CO'],
        'booking_rejected' => ['name' => env('WHATSAPP_TPL_BOOKING_REJECTED', 'zooetia_solicitud_rechazada'), 'language' => 'es_CO'],
    ],

    // Default sending window and notice (each clinic can change them).
    'defaults' => [
        'send_from' => '08:00',
        'send_until' => '19:00',
        'min_notice_hours' => 3,
    ],
];
