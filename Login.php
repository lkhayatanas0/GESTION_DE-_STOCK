<?php
/**
 * Page de connexion au système de gestion de stock
 * Fichier: login.php
 */

// Démarrer la session
session_start();

// Si l'utilisateur est déjà connecté, rediriger vers la page d'accueil
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Inclure la configuration de la base de données
require_once 'config/db.php';

// Variables pour stocker les messages d'erreur et les données du formulaire
$errors = [];
$email = '';

// Traitement du formulaire de connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer et nettoyer les données du formulaire
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    
    // Validation basique
    if (empty($email)) {
        $errors[] = "L'adresse email est requise";
    }
    
    if (empty($password)) {
        $errors[] = "Le mot de passe est requis";
    }
    
    // S'il n'y a pas d'erreurs, vérifier les identifiants dans la base de données
    if (empty($errors)) {
        try {
            $db = getDbConnection();
            
            $query = "SELECT id, nom_complet, email, mot_de_passe, role 
                     FROM utilisateurs 
                     WHERE email = :email AND actif = TRUE";
                     
            $stmt = $db->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            $user = $stmt->fetch();
            
            // Vérification simplifiée avec comparaison directe du mot de passe
            if ($user && $password === $user['mot_de_passe']) {
                // Identifiants corrects - Créer la session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['nom_complet'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                
                // Mettre à jour la date du dernier accès
                $updateQuery = "UPDATE utilisateurs SET dernier_acces = NOW() WHERE id = :id";
                $updateStmt = $db->prepare($updateQuery);
                $updateStmt->bindParam(':id', $user['id']);
                $updateStmt->execute();
                
                // Rediriger vers la page d'accueil
                header("Location: index.php");
                exit;
            } else {
                $errors[] = "Email ou mot de passe incorrect";
            }
        } catch (PDOException $e) {
            $errors[] = "Erreur de connexion: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Azrou Sani Gestion Stock</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .login-container {
            max-width: 400px;
            margin: 100px auto;
            padding: 20px;
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .logo {
            text-align: center;
            margin-bottom: 20px;
        }
        .logo img {
            max-height: 80px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="logo">
                <h2>Azrou Sani</h2>
                <p>Système de Gestion de Stock</p>
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                <div class="mb-3">
                    <label for="email" class="form-label">Adresse Email</label>
                    <input type="email" class="form-control" id="email" name="email" 
                           value="<?php echo htmlspecialchars($email); ?>" required>
                </div>
                
                <div class="mb-3">
                    <label for="password" class="form-label">Mot de Passe</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">Se Connecter</button>
                </div>
            </form>
            
            <div class="mt-3 text-center">
                <small>Vous avez oublié votre mot de passe? Contactez l'administrateur.</small>
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>