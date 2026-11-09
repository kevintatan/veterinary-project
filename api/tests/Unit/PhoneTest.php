<?php

use App\Support\Phone;
use App\Support\SearchText;

it('normalizes Colombian numbers to E.164', function (string $raw, ?string $expected) {
    expect(Phone::normalize($raw))->toBe($expected);
})->with([
    ['3157482096', '+573157482096'],
    ['315 748 2096', '+573157482096'],
    ['315-748-2096', '+573157482096'],
    ['573157482096', '+573157482096'],
    ['+57 315 748 2096', '+573157482096'],
    ['6076345678', '+576076345678'],   // landline with area code
    ['+1 (305) 555-0123', '+13055550123'], // foreign number keeps its country
    ['12345', null],
    ['', null],
]);

it('normalizes search text without accents or extra spaces', function () {
    expect(SearchText::normalize('  Carolina   RESTREPO Gómez '))->toBe('carolina restrepo gomez')
        ->and(SearchText::words('Peña Núñez'))->toBe(['pena', 'nunez']);
});
