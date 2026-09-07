<?php

test('the accounts page does not shadow the accounts prop with a route import', function () {
    $source = file_get_contents(__DIR__.'/../../resources/js/pages/Accounts.vue');

    expect($source)->not->toMatch('/import\s*\{[^}]*\bas accounts\b/');
});
