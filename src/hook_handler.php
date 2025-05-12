<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/resource_handler.php';

try {
    if (php_sapi_name() === 'cli') {
        $resource = $argv[1] ?? null;
        $action = $argv[2] ?? null;
        $stdin = file_get_contents('php://stdin');
        $data = json_decode($stdin, true);

        if ($resource && $action && $data) {
            if ($action === 'create') {
                logToConsole($resource . ' create request:', $data);
                resourceCreated($resource, $data);
            } elseif ($action === 'edit') {
                logToConsole($resource . ' edit request:', $data);
                resourceEdited($resource, $data);
            } else {
                throw new Exception("Invalid action provided");
            }
        } else {
            throw new Exception("'Missing resource, action, or data");
        }
    }
} catch (Exception $e) {
    logToConsole('ERROR ', ['message' => $e->getMessage()]);
}


function resourceCreated(string $resourceName, array $requestData = []): void
{
    $data = [];
    $textData = '';
    if (!empty($requestData[$resourceName]['add'])) {
        $requestData = $requestData[$resourceName]['add'];
        $textData = 'Name: ' . $requestData['name'] . PHP_EOL
            . ' Responsible user id: ' . $requestData['responsible_user_id'] . PHP_EOL
            . ' Created At: ' . date("Y-m-d H:i:s", $requestData['created_at']);

        $data['id'] = $requestData['id'];
        $data['custom_fields_values'] = [
            "field_id" => 2167797,
            "field_name" => "Tекстовое примечание",
            "values" => [
                [
                    "value" => $textData,
                ],
            ],
        ];
    } else {
        throw new Exception ('Wrong data');
    }

    $out = sendEditRequest($resourceName, $data['id'], $data);
    logToConsole($resourceName . ' create success:', $out);

    $requestData['custom_fields'][] = [
        "id" => 2167797,
        "name" => "Tекстовое примечание",
        "values" => [
            [
                "value" => $textData,
            ],
        ],
    ];
    saveResourceState($resourceName, $requestData['id'], $requestData);
    exit();
}

function resourceEdited(string $resourceName, array $requestData = []): void
{
    $data = [];
    $textData = '';
    if (!empty($requestData[$resourceName]['update'])) {
        $requestData = $requestData[$resourceName]['update'];
        $dataDiff = getResourceStateDiff($resourceName, $requestData['id'], $requestData);
        foreach ($dataDiff as $diff) {
            foreach ($diff as $key => $value) {
                $textData .= $key . ': ' . $value . PHP_EOL;
            }
        }
        if (empty($textData)) {
            exit();
        }
        $textData .= 'Update at: ' . date("Y-m-d H:i:s", $requestData['updated_at']);

        $data['id'] = $requestData['id'];
        $data['custom_fields_values'] = [
            "field_id" => 2167797,
            "field_name" => "Tекстовое примечание",
            "values" => [
                [
                    "value" => $textData,
                ],
            ],
        ];
    } else {
        throw new Exception ('Wrong data');
    }

    $out = sendEditRequest($resourceName, $data['id'], $data);
    logToConsole($resourceName . ' edit success:', $out);
    $requestData['custom_fields'][] = [
        "id" => 2167797,
        "name" => "Tекстовое примечание",
        "values" => [
            [
                "value" => $textData,
            ],
        ],
    ];
    saveResourceState($resourceName, $requestData['id'], $requestData);
    exit();
}

function sendEditRequest(string $resourceUri, ?int $resourceId = null,  array $data = [])
{
    $token = getToken();

    if (time() >= $token['received'] + $token['expires']) {
        refreshToken();
        $token = getToken();
    }

    $uri = 'api/v4/' . $resourceUri;
    if ($resourceId) {
        $uri .= '/' . $resourceId;
    }

    logToConsole($uri, $data);

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_USERAGENT, 'amoCRM-oAuth-client/1.0');
    curl_setopt($curl, CURLOPT_URL, getenv('ACCOUNT_DOMAIN') . $uri);
    curl_setopt($curl, CURLOPT_HTTPHEADER, [
        'Content-Type:application/json',
        'Authorization: Bearer ' . $token['access_token'],
    ]);
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PATCH');
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
        logToConsole(strtoupper($resourceId) . ' REQUEST ERR:', [
            'message' => $e->getMessage(),
            'code' => $code,
            'response' => json_encode($out),
        ]);

        die('Ошибка: ' . $e->getMessage() . PHP_EOL . 'Код ошибки: ' . $e->getCode());
    }
}