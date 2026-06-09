<?php
session_start();

function requireLogin(){
  if (empty($_SESSION['dni']) || empty($_SESSION['rol'])) {
    header('Location: index.html'); // o login.php
    exit;
  }
}

function currentRole(): string {
  return strtolower(trim($_SESSION['rol'] ?? ''));
}
