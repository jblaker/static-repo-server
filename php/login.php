<?php
// login.php — renders login form and handles POST

ini_set('session.save_path', '/var/lib/php/sessions');
session_start();

// Already logged in
if (!empty($_SESSION['username'])) {
	$next = $_GET['next'] ?? '/';
	header('Location: ' . $next);
	exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$username = trim($_POST['username'] ?? '');
	$password = $_POST['password'] ?? '';
	$next     = $_POST['next'] ?? '/';

	$users_file = '/data/users.txt';

	if (!file_exists($users_file)) {
		$error = 'User database not found.';
	} else {
		$matched = false;
		$lines   = file($users_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

		foreach ($lines as $line) {
			// Skip comments
			if (str_starts_with(trim($line), '#')) continue;

			// Format: username:bcrypt_hash:role
			$parts = explode(':', $line, 3);
			if (count($parts) !== 3) continue;

			[$file_user, $file_hash, $file_role] = $parts;

			if ($file_user === $username && password_verify($password, $file_hash)) {
				$matched = true;
				$_SESSION['username'] = $username;
				$_SESSION['role']     = trim($file_role);
				session_regenerate_id(true);
				header('Location: ' . $next);
				exit;
			}
		}

		if (!$matched) {
			$error = 'Invalid username or password.';
		}
	}
} else {
	$next = $_GET['next'] ?? '/';
}

$next_escaped = htmlspecialchars($next, ENT_QUOTES);
$error_html   = $error ? '<p class="error">' . htmlspecialchars($error) . '</p>' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Login</title>
	<style>
		*, *::before, *::after { box-sizing: border-box; }

		body {
			margin: 0;
			display: flex;
			align-items: center;
			justify-content: center;
			min-height: 100vh;
			background: #f0f2f5;
			font-family: system-ui, sans-serif;
		}

		.card {
			background: #fff;
			border-radius: 8px;
			box-shadow: 0 2px 12px rgba(0,0,0,.1);
			padding: 2.5rem 2rem;
			width: 100%;
			max-width: 380px;
		}

		h1 {
			margin: 0 0 1.5rem;
			font-size: 1.4rem;
			text-align: center;
			color: #111;
		}

		label {
			display: block;
			font-size: .85rem;
			font-weight: 600;
			color: #444;
			margin-bottom: .3rem;
		}

		input[type=text],
		input[type=password] {
			width: 100%;
			padding: .6rem .8rem;
			border: 1px solid #ccc;
			border-radius: 5px;
			font-size: 1rem;
			margin-bottom: 1rem;
			outline: none;
			transition: border-color .2s;
		}

		input:focus { border-color: #4a90e2; }

		button {
			width: 100%;
			padding: .7rem;
			background: #4a90e2;
			color: #fff;
			border: none;
			border-radius: 5px;
			font-size: 1rem;
			cursor: pointer;
			transition: background .2s;
		}

		button:hover { background: #357abd; }

		.error {
			color: #c0392b;
			font-size: .875rem;
			margin-bottom: 1rem;
			text-align: center;
		}
	</style>
</head>
<body>
<div class="card">
	<h1>Sign In</h1>
	<?= $error_html ?>
	<form method="post" action="/login">
		<input type="hidden" name="next" value="<?= $next_escaped ?>">
		<label for="username">Username</label>
		<input type="text" id="username" name="username" required autofocus>
		<label for="password">Password</label>
		<input type="password" id="password" name="password" required>
		<button type="submit">Sign In</button>
	</form>
</div>
</body>
</html>
