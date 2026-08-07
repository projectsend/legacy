<?php

/**
 * Generate a new csrf protection token with a cryptographically secure random generator
 *
 * @return string
 */
function getCsrfToken()
{
    if(!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function addCsrf()
{
    echo '<input type="hidden" name="csrf_token" id="csrf_token" value="'.getCsrfToken().'" />';
}

/**
 * Validates the send csrf token with a stable string comparison algorithm.
 * Do not optimize for speed!!!
 *
 * @return bool
 */
function validateCsrfToken()
{
    if (empty($_SESSION['csrf_token'])) {
        return false;
    }

    $token = isset($_REQUEST['csrf_token']) ? $_REQUEST['csrf_token'] : null;

    /**
     * Requests that send a JSON body don't populate $_REQUEST, so the token
     * has to be read from the raw input. Only done for JSON content types:
     * file uploads must never have their body buffered here.
     */
    $content_type = $_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '');
    if ($token === null && stripos($content_type, 'application/json') !== false) {
        $decoded = json_decode(file_get_contents('php://input'), true);
        if (is_array($decoded) && isset($decoded['csrf_token'])) {
            $token = $decoded['csrf_token'];
        }
    }

    if (!is_string($token)) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Any request that can change state must carry a valid token. Checking for a
 * populated $_POST is not enough: a request with an empty body, or one that
 * sends its parameters as JSON, skips the check entirely.
 */
$csrf_safe_methods = ['GET', 'HEAD', 'OPTIONS'];
if (!defined('IS_INSTALL') && !in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', $csrf_safe_methods, true) && !validateCsrfToken()) {
    exit_with_error_code(403);
}