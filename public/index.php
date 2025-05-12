<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/logger.php';
require_once __DIR__ . '/../src/hook_handler.php';

function getRequestData(): array
{
    $contentType = $_SERVER["CONTENT_TYPE"] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'POST') {
        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents("php://input");
            return json_decode($raw, true) ?? [];
        }

        if (
            str_contains($contentType, 'application/x-www-form-urlencoded') ||
            str_contains($contentType, 'multipart/form-data')
        ) {
            return $_POST;
        }
    }

    return [];
}

$requestUri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        if (str_starts_with($requestUri, '/code_auth')) {
            $code = $_GET['code'] ?? '';

            if (!empty($code)) {
                $response = sendAuthRequest('authorization_code', $code);

                $tokenData = [
                    'access_token' => $response['access_token'],
                    'refresh_token' => $response['refresh_token'],
                    'token_type' => $response['token_type'],
                    'expires' => $response['expires_in'],
                    'received' => time(),
                ];

                saveToken($tokenData);
                echo json_encode(['success' => true]);
            } else {
                http_response_code(422);
                echo json_encode(['error' => 'Invalid request parameters.']);
                exit();
            }
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Route not found']);
            exit();
        }
    } else if ($method === 'POST') {
        $data = getRequestData();
        $resource = '';
        $action = '';

        if (str_starts_with($requestUri, '/contact/edit')) {
            $resource = 'contacts';
            $action = 'edit';
        } elseif (str_starts_with($requestUri, '/contact')) {
            $resource = 'contacts';
            $action = 'create';
        } elseif (str_starts_with($requestUri, '/lead/edit')) {
            $resource = 'leads';
            $action = 'edit';
        } elseif (str_starts_with($requestUri, '/lead')) {
            $resource = 'leads';
            $action = 'create';
        } else {
            logToConsole('Route not found', ['uri' => $requestUri, 'method' => $method]);
            http_response_code(404);
            echo json_encode(['error' => 'Route not found']);
            exit();
        }

        $cmd = 'php ' . __DIR__ . '/hook_handler.php ' . $resource . ' ' . $action;

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', '/dev/null', 'a'],
            2 => ['file', '/dev/null', 'a'],
        ];

        $pipes = [];

        $process = proc_open($cmd, $descriptors, $pipes);

        if (is_resource($process)) {
            fwrite($pipes[0], json_encode($data));
            fclose($pipes[0]);
            proc_close($process);
        }
    } else {
        logToConsole('Method not allowed', [
            'uri' => $requestUri,
            'method' => $method,
        ]);

        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit();
    }
} catch (Exception $e) {
    logToConsole('Server error', [
        'message' => $e->getMessage(),
        'uri' => $requestUri,
        'method' => $method,
        'trace' => $e->getTraceAsString(),
    ]);

    http_response_code(500);
    echo json_encode(['error' => 'Internal error']);
    exit();
}