<?php

it('redirige la racine vers le tableau de bord', function () {
    $this->get('/')->assertRedirect(route('dashboard'));
});
