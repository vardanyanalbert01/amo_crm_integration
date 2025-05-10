<?php

function saveResourceState(string $resourceName, string $resourceId, array $data = []): void
{
    $directory = __DIR__ . "/../resources/$resourceName";

    if (!is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    $filePath = "$directory/$resourceId.json";
    $log = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

    file_put_contents($filePath, $log);
}

function getResourceState(string $resourceName, string $resourceId): array
{
    $filePath = __DIR__ . "/../resources/$resourceName/$resourceId.json";
    if (!file_exists($filePath)) {
        return [];
    }

    return json_decode(file_get_contents($filePath), true);
}

function getResourceStateDiff(string $resourceName, string $resourceId, array $data = []): array
{
    $filePath = __DIR__ . "/../resources/$resourceName/$resourceId.json";

    if (!file_exists($filePath)) {
        return ['added' => $data, 'removed' => [], 'changed' => []];
    }

    $oldData = json_decode(file_get_contents($filePath), true);

    $added = [];
    $removed = [];
    $changed = [];

    foreach ($data as $key => $value) {
        if (!array_key_exists($key, $oldData)) {
            $added[$key] = $value;
        } elseif ($oldData[$key] !== $value) {
            $changed[$key] = ['old' => $oldData[$key], 'new' => $value];
        }
    }

    foreach ($oldData as $key => $value) {
        if (!array_key_exists($key, $data)) {
            $removed[$key] = $value;
        }
    }

    return compact('added', 'removed', 'changed');
}
