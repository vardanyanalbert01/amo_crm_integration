<?php

function logToConsole(string $message, array $context = []): void
{
    $timestamp = date('Y-m-d H:i:s');
    $output = "[$timestamp] $message";

    if (!empty($context)) {
        $output .= ' => ' . json_encode($context, JSON_UNESCAPED_UNICODE);
    }

    error_log($output);
}
