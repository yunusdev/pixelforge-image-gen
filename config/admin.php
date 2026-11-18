<?php

return [

    /*
    | The grader admin seeded by GraderAdminSeeder. Set both on the server; with no password nothing is seeded.
    */

    'grader' => [
        'email' => env('GRADER_ADMIN_EMAIL', 'grader@example.com'),
        'password' => env('GRADER_ADMIN_PASSWORD'),
    ],

];
