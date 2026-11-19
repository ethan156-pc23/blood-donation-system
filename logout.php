<?php
require __DIR__ . '/config.php';
if (user()) audit('Logout', user()['email']);
$_SESSION = [];
session_destroy();
session_start();
flash('You have been signed out.');
redirect('login.php');
