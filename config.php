<?php

return [
    'driver' => 'sqlite', // or 'mysql', 'pgsql', etc.
    'sqlite' => [
        'database' => 'database.db',
    ],
    'mysql' => [
        'host' => '127.0.0.1',
        'database' => 'your_database',
        'username' => 'your_username',
        'password' => 'your_password',
    ],
    // Add other database configurations as needed
];
