<?php
// ============================================================
// config/google.php — Google OAuth 2.0 configuration
// Create credentials at https://console.cloud.google.com/apis/credentials
// ============================================================

// ⚠️ REPLACE THESE with your actual Google OAuth credentials
define('GOOGLE_CLIENT_ID', '657183368925-8pp3qdau71di7rv8pk6r2dv5ch14cqlb.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', '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');
define('GOOGLE_REDIRECT_URI', 'http://localhost/lost-knowledge/google_callback.php');

/**
 * Build the Google OAuth 2.0 authorization URL.
 *
 * @return string Full Google login URL
 */
function google_auth_url(): string
{
    $params = http_build_query([
        'client_id' => GOOGLE_CLIENT_ID,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'access_type' => 'online',
        'prompt' => 'select_account',
    ]);

    return 'https://accounts.google.com/o/oauth2/v2/auth?' . $params;
}

/**
 * Exchange an authorization code for an access token.
 *
 * @param string $code The authorization code from Google
 * @return array|null  Token data or null on failure
 */
function google_get_token(string $code): ?array
{
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'code' => $code,
            'client_id' => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'redirect_uri' => GOOGLE_REDIRECT_URI,
            'grant_type' => 'authorization_code',
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log("[LK] Google token cURL error: $error");
        return null;
    }

    $data = json_decode($response, true);
    return $data['access_token'] ?? null ? $data : null;
}

/**
 * Fetch the user's Google profile using an access token.
 *
 * @param string $accessToken A valid Google access token
 * @return array|null         User info array or null
 */
function google_get_user(string $accessToken): ?array
{
    $ch = curl_init('https://www.googleapis.com/oauth2/v2/userinfo');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["Authorization: Bearer $accessToken"],
        CURLOPT_TIMEOUT => 10,
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log("[LK] Google userinfo cURL error: $error");
        return null;
    }

    $data = json_decode($response, true);
    return ($data && isset($data['id'])) ? $data : null;
}
