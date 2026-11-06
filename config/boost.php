<?php

declare(strict_types=1);

/**
 * Project overrides for laravel/boost. Every key missing here keeps its
 * default from vendor/laravel/boost/config/boost.php.
 */
return [

    /*
     * Boost writes its guidelines block into CLAUDE.md as soon as that file
     * exists. CLAUDE.md here only imports AGENTS.md, so the block stays in
     * AGENTS.md, where every agent reads it.
     */
    'agents' => [
        'claude_code' => [
            'guidelines_path' => 'AGENTS.md',
        ],
    ],

    /*
     * Guideline sections left out of the generated block. The project deploys
     * with scripts/deploy.sh, so the Laravel Cloud section does not apply.
     */
    'guidelines' => [
        'exclude' => ['deployments'],
    ],

];
