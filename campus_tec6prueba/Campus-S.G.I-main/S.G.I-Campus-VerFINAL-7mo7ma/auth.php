<?php
session_start();

function requireLogin(){
  if (empty($_SESSION['dni']) || empty($_SESSION['rol'])) {
    header('Location: index.html');
    exit;
  }

  if (!empty($_SESSION['must_change_password']) && basename($_SERVER['PHP_SELF']) !== 'changepassword.html' && basename($_SERVER['PHP_SELF']) !== 'changepass.php') {
    header('Location: changepassword.html');
    exit;
  }
}

function currentRole(): string {
  return strtolower(trim($_SESSION['rol'] ?? ''));
}
