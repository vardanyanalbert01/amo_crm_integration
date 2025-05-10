<?php

require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/resource_handler.php';

function getToken(): array
{
    $accessToken = getResourceState('token', getenv('TOKEN_FILE'));

    if (
        !empty($accessToken)
        && isset($accessToken['access_token'])
        && isset($accessToken['refresh_token'])
        && isset($accessToken['expires'])
    ) {

        return $accessToken;
    } else {
        logToConsole('Token not found:');
        return [];
    }
}

function saveToken(array $accessToken): void
{
    logToConsole('Save token');
    if (
        !empty($accessToken)
        && isset($accessToken['access_token'])
        && isset($accessToken['refresh_token'])
        && isset($accessToken['expires'])
    ) {
        saveResourceState('token', getenv('TOKEN_FILE'), $accessToken);
    } else {
        logToConsole('Invalid access token');
        exit('Invalid access token ' . var_export($accessToken, true));
    }
}

function refreshToken(): void
{
    logToConsole('Refresh token request:');

    $accessToken = getResourceState('token', getenv('TOKEN_FILE'));
    if (empty($accessToken)) {
        logToConsole('Token not found:');
    }

    $response = sendAuthRequest('refresh_token', $accessToken['refresh_token'] ?? null);

    $tokenData = [
        'access_token' => $response['access_token'],
        'refresh_token' => $response['refresh_token'],
        'token_type' => $response['token_type'],
        'expires' => $response['expires_in'],
        'received' => time(),
    ];

    saveToken($tokenData);
}

function sendAuthRequest(string $grantType, ?string $token = null)
{
    $data = [
        'client_id' => getenv('CLIENT_ID'),
        'client_secret' => getenv('CLIENT_SECRET'),
        'grant_type' => $grantType,
        'redirect_uri' => getenv('REDIRECT_URI'),
    ];

    switch ($grantType) {
        case 'authorization_code':
            $data['code'] = $token;
            break;
        case 'refresh_token':
            $data['refresh_token'] = $token;
            break;
        default:
            throw  new \Exception('Unsupported grant type: ' . $grantType);
    }

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_USERAGENT, 'amoCRM-oAuth-client/1.0');
    curl_setopt($curl, CURLOPT_URL, getenv('ACCOUNT_DOMAIN') . 'oauth2/access_token');
    curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type:application/json']);
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 1);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
    $out = curl_exec($curl);
    $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    $code = (int)$code;
    $errors = [
        400 => 'Bad request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not found',
        500 => 'Internal server error',
        502 => 'Bad gateway',
        503 => 'Service unavailable',
    ];

    try {
        if ($code < 200 || $code > 204) {
            throw new Exception(isset($errors[$code]) ? $errors[$code] : 'Undefined error', $code);
        }

        return json_decode($out, true);
    } catch (\Exception $e) {
        logToConsole('AUTH REQUEST ERR:', [
            'message' => $e->getMessage(),
            'code' => $code,
            'response' => json_encode($out),
        ]);

        die('Ошибка: ' . $e->getMessage() . PHP_EOL . 'Код ошибки: ' . $e->getCode());
    }
}