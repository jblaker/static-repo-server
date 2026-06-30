<?php
// auth_check.php — internal endpoint called by nginx auth_request
// Returns 200 if session is valid, 401 otherwise.

ini_set('session.save_path', '/var/lib/php/sessions');
session_start();

if (!empty($_SESSION['username']) && !empty($_SESSION['role'])) {
	http_response_code(200);
} else {
	http_response_code(401);
}
