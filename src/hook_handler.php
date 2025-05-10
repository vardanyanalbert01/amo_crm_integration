<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/resource_handler.php';

function resourceCreated(string $resourceName, array $requestData = []): void
{
    logToConsole($resourceName . ' create request:', $requestData);

    $data = [];
    if (isset($requestData[$resourceName]['add'])) {
        $requestData = $requestData[$resourceName]['add'];
        saveResourceState($resourceName, $requestData['id'], $requestData);
        $data['id'] = $requestData['id'];
        $textData = 'Name: ' . $requestData['name'] . PHP_EOL
            . ' Responsible user id: ' . $requestData['responsible_user_id'] . PHP_EOL
            . ' Created At: ' . date("Y-m-d H:i:s", $requestData['created_at']);

        $data['custom_fields_values'] = [
            "field_id" => 203,
            "values" => [
                [
                    "value" => $textData,
                ],
            ],
        ];
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Wrong data']);
        exit();
    }

    $out = sendEditRequest($resourceName, $data, $data['id']);

    logToConsole($resourceName . ' create success:', $out);
    echo json_encode(['message' => 'Success']);
    exit();
}

function resourceEdited(string $resourceName, array $requestData = []): void
{
    logToConsole($resourceName . ' edit request:', $requestData);

    $data = [];
    if (isset($requestData[$resourceName]['update'])) {
        $requestData = $requestData[$resourceName]['edit'];
        $dataDiff = getResourceStateDiff($resourceName, $requestData['id'], $requestData);
        $data['id'] = $requestData['id'];
        $textData = '';
        foreach ($dataDiff as $diff) {
            foreach ($diff as $key => $value) {
                $textData .= $key . ': ' . $value . PHP_EOL;
            }
        }
        $textData .= 'Update at: ' . date("Y-m-d H:i:s", $requestData['updated_at']);

        $data['custom_fields_values'] = [
            "field_id" => 203,
            "values" => [
                [
                    "value" => $textData,
                ],
            ],
        ];
        saveResourceState($resourceName, $requestData['id'], $requestData);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Wrong data']);
        exit();
    }

    $out = sendEditRequest($resourceName, $data, $data['id']);

    logToConsole($resourceName . ' edit success:', $out);
    echo json_encode(['message' => 'Success']);
    exit();
}

function sendEditRequest(string $resourceUri, array $data = [], ?int $resourceId = null)
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