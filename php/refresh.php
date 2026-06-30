<?php
// refresh.php — admin-only endpoint that pulls latest repo content

ini_set('session.save_path', '/var/lib/php/sessions');
session_start();

// Must be logged in as admin
if (empty($_SESSION['username'])) {
	http_response_code(401);
	echo json_encode(['error' => 'Not authenticated.']);
	exit;
}

if ($_SESSION['role'] !== 'admin') {
	http_response_code(403);
	echo json_encode(['error' => 'Admin role required.']);
	exit;
}

// Allow GET and POST (GET is convenient for browser-based triggering)
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
	http_response_code(405);
	header('Content-Type: application/json');
	echo json_encode(['error' => 'GET or POST required.']);
	exit;
}

header('Content-Type: application/json');

$repo_dir = '/var/www/html';
$log_file = '/data/refresh.log';

$timestamp = date('Y-m-d H:i:s');
$user      = $_SESSION['username'];

// Run git pull then rsync into webroot
$repo_clone  = '/repo';
$repo_subdir = getenv('REPO_SUBDIR');
$repo_branch = getenv('REPO_BRANCH') ?: 'main';
$sync_src    = $repo_subdir ? "$repo_clone/$repo_subdir" : $repo_clone;

$cmd = implode(' && ', [
	'cd ' . escapeshellarg($repo_clone) . ' && git -c safe.directory=' . escapeshellarg($repo_clone) . ' pull origin ' . escapeshellarg($repo_branch) . ' 2>&1',
	'rsync -a --delete ' . escapeshellarg($sync_src . '/') . ' ' . escapeshellarg($repo_dir . '/') . ' 2>&1',
	'chown -R www-data:www-data ' . escapeshellarg($repo_dir),
]);

$output = shell_exec($cmd);
$exit   = 0;

// shell_exec returns null on failure
if ($output === null) {
	$output = 'shell_exec returned null — check git/SSH config.';
	$exit   = 1;
}

// Log the attempt
$log_line = "[$timestamp] user=$user exit=$exit output=" . trim($output) . PHP_EOL;
file_put_contents($log_file, $log_line, FILE_APPEND | LOCK_EX);

if ($exit === 0) {
	echo json_encode([
		'status'  => 'ok',
		'message' => trim($output),
		'time'    => $timestamp,
	]);
} else {
	http_response_code(500);
	echo json_encode([
		'status'  => 'error',
		'message' => trim($output),
		'time'    => $timestamp,
	]);
}
