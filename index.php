<?php
require __DIR__ . '/config.php';
redirect(user() ? 'dashboard.php' : 'login.php');
