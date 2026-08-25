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
];
