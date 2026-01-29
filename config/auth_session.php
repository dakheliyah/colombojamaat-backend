<?php

return [

    /*
    |--------------------------------------------------------------------------
    | ITS Number Cookie (its_no) — Auth Session API
    |--------------------------------------------------------------------------
    |
    | Configuration for reading and optionally decrypting the HttpOnly `its_no`
    | cookie. The cookie is set by the OneLogin (or equivalent) auth system.
    |
    */

    'encrypted' => filter_var(env('ITS_NO_COOKIE_ENCRYPTED', 'true'), FILTER_VALIDATE_BOOLEAN),

    'decryption_key' => env('ITS_NO_DECRYPTION_KEY'),

];
