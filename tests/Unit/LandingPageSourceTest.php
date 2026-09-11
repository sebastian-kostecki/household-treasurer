<?php

test('welcome page no longer loads the Laravel starter Inter stylesheet', function () {
    $source = file_get_contents(__DIR__.'/../../resources/js/pages/Welcome.vue');

    expect($source)->not->toContain('rsms.me/inter');
});

test('welcome page no longer uses the Laravel starter brand red', function () {
    $source = file_get_contents(__DIR__.'/../../resources/js/pages/Welcome.vue');

    expect($source)->not->toContain('#f53003');
    expect($source)->not->toContain('#F53003');
});
