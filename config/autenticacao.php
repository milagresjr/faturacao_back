<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Access Token
    |--------------------------------------------------------------------------
    | Duração do access token emitido pelo Sanctum (em minutos).
    | Curto por design — a sessão é mantida viva pelo refresh token.
    */
    'access_token_minutes' => (int) env('ACCESS_TOKEN_EXPIRATION_MINUTES', 15),

    /*
    |--------------------------------------------------------------------------
    | Refresh Token
    |--------------------------------------------------------------------------
    | Duração do refresh token com rotação (em dias).
    | - refresh_token_days: usado quando remember_me = true (sessão persistente)
    | - refresh_token_session_days: usado quando remember_me = false (sessão curta)
    */
    'refresh_token_days' => (int) env('REFRESH_TOKEN_EXPIRATION_DAYS', 30),
    'refresh_token_session_days' => (int) env('REFRESH_TOKEN_SESSION_DAYS', 1),
];
