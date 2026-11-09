<?php

use Database\Seeders\DemoSeeder;

it('refuses to load demo data (known passwords) outside local and testing', function () {
    app()->detectEnvironment(fn () => 'production');

    expect(fn () => (new DemoSeeder)->setContainer(app())->__invoke())->toThrow(RuntimeException::class, 'Datos de demostración: solo en local.');
});
