<?php

return [
    'client_id' => env('STACKAPPS_CLIENT_ID'),
    'client_secret' => env('STACKAPPS_CLIENT_SECRET'),
    'key' => env('STACKAPPS_KEY'),
    'redirect_uri' => env('STACKAPPS_REDIRECT_URI', 'https://stackexchange.com/oauth/login_success'),
    'version_prefix' => env('STACKAPPS_VERSION_PREFIX', '/2.2'),
];//See https://stackapps.com/apps/oauth/view/{your_app_id}
