<?php

return [
    // Shared bearer token the phone clients send. Set REMOTE_DIRECTORY_TOKEN in
    // the app .env and the same value in the phone's directory URL. Desk phones
    // cannot send headers, so the token may also travel as a "token" query param.
    'token' => env('REMOTE_DIRECTORY_TOKEN'),

    // Entries returned per request when the client asks for no explicit limit.
    'limit' => (int) env('REMOTE_DIRECTORY_LIMIT', 50),

    // Hard server side cap, a client cannot ask for more than this.
    'max_limit' => (int) env('REMOTE_DIRECTORY_MAX_LIMIT', 200),

    // Requests per minute per IP on the search route, as Laravel's throttle
    // middleware takes it. The token travels in the URL, so the endpoint is
    // one guessed string away from being read by anyone.
    'throttle' => env('REMOTE_DIRECTORY_THROTTLE', '60,1'),

    // The LDAP directory, served by "php artisan remote-directory:ldap".
    // Clients that speak LDAP instead of the XML phonebook (Yealink, Fanvil,
    // Bria) point at this listener. Credentials are sent in the clear unless
    // the listener runs with a certificate, so either set one or keep the port
    // on a network the phones reach and the internet does not.
    'ldap' => [
        'ip' => env('REMOTE_DIRECTORY_LDAP_IP', '0.0.0.0'),
        'port' => (int) env('REMOTE_DIRECTORY_LDAP_PORT', 389),

        // Every entry is published below this DN.
        'base_dn' => env('REMOTE_DIRECTORY_LDAP_BASE_DN', 'dc=flux,dc=local'),

        // The single account the phones bind with.
        'username' => env('REMOTE_DIRECTORY_LDAP_USERNAME'),
        'password' => env('REMOTE_DIRECTORY_LDAP_PASSWORD'),

        // Serving without a bind hands the whole directory to anyone who can
        // reach the port. Only for a closed phone network.
        'allow_anonymous' => (bool) env('REMOTE_DIRECTORY_LDAP_ALLOW_ANONYMOUS', false),

        // LDAPS. With a certificate but use_ssl false, clients may StartTLS.
        'use_ssl' => (bool) env('REMOTE_DIRECTORY_LDAP_USE_SSL', false),
        'ssl_cert' => env('REMOTE_DIRECTORY_LDAP_SSL_CERT'),
        'ssl_cert_key' => env('REMOTE_DIRECTORY_LDAP_SSL_CERT_KEY'),
    ],
];
