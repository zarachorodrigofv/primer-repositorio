<?php
require __DIR__ . '/auth.php';
requireLogin();
header('Location: SGI.php');
exit;
