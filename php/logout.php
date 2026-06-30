<?php
// logout.php

ini_set('session.save_path', '/var/lib/php/sessions');
session_start();
session_destroy();

header('Location: /login');
exit;
