<?php

test('public registration is unavailable', function () {
    $response = $this->get('/register');

    $response->assertNotFound();

    $response = $this->post('/register', [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertNotFound();
});
