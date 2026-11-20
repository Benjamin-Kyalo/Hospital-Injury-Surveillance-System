<?php
// Database settings for the local PostgreSQL cluster in runtime\pgdata.
// No XAMPP needed. If you use your main PostgreSQL instead, change the port
// to 5432 and fill in your own password.
return [
    'host'     => '127.0.0.1',
    'port'     => '55432',
    'dbname'   => 'HISS',
    'user'     => 'postgres',
    'password' => '',
];
