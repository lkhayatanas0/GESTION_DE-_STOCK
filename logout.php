<?php
/**
 * Page de déconnexion
 * Fichier: logout.php
 */

// Démarrer la session
session_start();

// Détruire toutes les variables de session
$_SESSION = array();

// Détruire le cookie de session si présent
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 42000, '/');
}

// Détruire la session
session_destroy();

// Rediriger vers la page de connexion
header("Location: login.php");
exit;
?>