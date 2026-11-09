<?php

use App\Messaging\WhatsApp\MetaWhatsAppProvider;
use App\Messaging\WhatsApp\OutgoingMessage;
use App\Messaging\WhatsApp\WhatsAppException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config([
        'whatsapp.meta.api_version' => 'v21.0',
        'whatsapp.meta.phone_number_id' => '123456789',
        'whatsapp.meta.access_token' => 'test-token',
    ]);
    $this->message = new OutgoingMessage('+573123334455', 'zooetia_recordatorio_cita', 'es_CO', ['Luz', 'Veterinaria Anipet', 'Kira', 'a las 9:30 a. m. del martes 6 de octubre'], 'texto');
});

it('sends an approved template in the Cloud API format', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.ABC']]])]);

    $result = (new MetaWhatsAppProvider)->send($this->message);

    expect($result->providerMessageId)->toBe('wamid.ABC');
    Http::assertSent(fn (Request $request) => $request->url() === 'https://graph.facebook.com/v21.0/123456789/messages'
        && $request->hasHeader('Authorization', 'Bearer test-token')
        && $request['to'] === '573123334455'
        && $request['type'] === 'template'
        && $request['template']['name'] === 'zooetia_recordatorio_cita'
        && $request['template']['language']['code'] === 'es_CO'
        && $request['template']['components'][0]['parameters'][2] === ['type' => 'text', 'text' => 'Kira']);
});

it('tells temporary errors (retry) from permanent ones (do not retry)', function (int $status, bool $retryable) {
    Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'nope']], $status)]);

    try {
        (new MetaWhatsAppProvider)->send($this->message);
        $this->fail('Expected WhatsAppException');
    } catch (WhatsAppException $e) {
        expect($e->retryable)->toBe($retryable)->and($e->getMessage())->toContain('nope');
    }
})->with([
    'rate limit' => [429, true],
    'Meta down' => [503, true],
    'bad number or template' => [400, false],
]);

it('refuses to run without credentials', function () {
    config(['whatsapp.meta.access_token' => null]);

    expect(fn () => (new MetaWhatsAppProvider)->send($this->message))->toThrow(WhatsAppException::class, 'no está configurado');
});
