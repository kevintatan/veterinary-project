<?php

it('responds on the versioned health endpoint', function () {
    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertExactJson(['status' => 'ok']);
});

it('rejects unauthenticated requests to clinic endpoints with JSON 401', function () {
    $this->getJson('/api/v1/me')->assertUnauthorized();
});
