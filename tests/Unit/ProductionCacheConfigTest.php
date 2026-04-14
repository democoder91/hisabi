<?php

it('uses the database cache driver in the production environment template', function (): void {
    $environmentTemplate = file_get_contents(dirname(__DIR__, 2).'/.env.prod.example');

    expect($environmentTemplate)->not->toBeFalse()
        ->and($environmentTemplate)->toContain('CACHE_DRIVER=database')
        ->and($environmentTemplate)->not->toContain('CACHE_DRIVER=file');
});
