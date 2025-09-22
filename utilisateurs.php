<?php
/**
 * Page de gestion des utilisateurs
 * Fichier: utilisateurs.php
 */

// Démarrer la session
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Vérifier si l'utilisateur a les droits d'administrateur
if ($_SESSION['user_role'] !== 'administrateur') {
    header("Location: index.php");
    exit;
}

// Inclure la configuration de la base de données
require_once 'config/db.php';

// Récupérer les informations de l'utilisateur
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];

// Messages de notification et d'erreur
$notification = "";
$error = "";

// Supprimer un utilisateur
if (isset($_GET['action']) && $_GET['action'] === 'supprimer' && isset($_GET['id'])) {
    try {
        $db = getDbConnection();
        
        // Vérifier que l'utilisateur ne s'auto-supprime pas
        if ($_GET['id'] == $user_id) {
            $error = "Vous ne pouvez pas supprimer votre propre compte.";
        } else {
            $stmt = $db->prepare("UPDATE utilisateurs SET actif = FALSE WHERE id = ?");
            if ($stmt->execute([$_GET['id']])) {
                $notification = "L'utilisateur a été désactivé avec succès.";
            } else {
                $error = "Erreur lors de la désactivation de l'utilisateur.";
            }
        }
    } catch (PDOException $e) {
        $error = "Erreur de base de données: " . $e->getMessage();
    }
}

// Récupérer la liste des utilisateurs
try {
    $db = getDbConnection();
    
    // Paramètres de pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    // Compter le nombre total d'utilisateurs
    $query = "SELECT COUNT(*) as total FROM utilisateurs";
    $stmt = $db->query($query);
    $total_utilisateurs = $stmt->fetch()['total'];
    $total_pages = ceil($total_utilisateurs / $limit);
    
    // Récupérer les utilisateurs avec pagination
    $query = "SELECT id, nom_complet, email, role, date_creation, dernier_acces, actif 
              FROM utilisateurs 
              ORDER BY date_creation DESC
              LIMIT :limit OFFSET :offset";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $utilisateurs = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Erreur de base de données: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Utilisateurs - Azrou Sani Gestion Stock</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .sidebar {
            min-height: 100vh;
            background-color: #343a40;
            color: white;
            padding-top: 20px;
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.75);
            margin-bottom: 5px;
        }
        .sidebar .nav-link:hover {
            color: white;
        }
        .sidebar .nav-link.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
        }
        .sidebar .nav-link i {
            margin-right: 10px;
        }
        .content {
            padding: 20px;
        }
        .card-dashboard {
            margin-bottom: 20px;
            border: none;
            border-radius: 5px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .card-dashboard .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            font-weight: bold;
        }
        .table-responsive {
            max-height: 600px;
            overflow-y: auto;
        }
        .table th {
            background-color: #f8f9fa;
            position: sticky;
            top: 0;
            z-index: 1;
        }
        .user-info {
            display: flex;
            align-items: center;
        }
        .pagination {
            margin-bottom: 0;
        }
        .role-badge {
            width: 100px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar">
                <div class="d-flex align-items-center mb-4 px-3">
                    <h5 class="mb-0">Azrou Sani</h5>
                </div>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-tachometer-alt"></i> Tableau de bord
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="produits.php">
                            <i class="fas fa-boxes"></i> Produits
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="stock.php">
                            <i class="fas fa-warehouse"></i> Stock
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="commandes.php">
                            <i class="fas fa-shipping-fast"></i> Commandes
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="achats.php">
                            <i class="fas fa-truck-loading"></i> Achats
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="clients.php">
                            <i class="fas fa-users"></i> Clients
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="fournisseurs.php">
                            <i class="fas fa-industry"></i> Fournisseurs
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="utilisateurs.php">
                            <i class="fas fa-user-cog"></i> Utilisateurs
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="parametres.php">
                            <i class="fas fa-cogs"></i> Paramètres
                        </a>
                    </li>
                    <li class="nav-item mt-5">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Déconnexion
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 content">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Gestion des Utilisateurs</h2>
                    <div class="user-info">
                        <span class="me-2"><?php echo htmlspecialchars($user_name); ?></span>
                        <span class="badge bg-secondary"><?php echo htmlspecialchars($user_role); ?></span>
                    </div>
                </div>

                <?php if (!empty($notification)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $notification; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Actions bar -->
                <div class="card card-dashboard mb-4">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-9">
                                <form class="d-flex" action="" method="GET">
                                    <input type="text" name="search" class="form-control me-2" placeholder="Rechercher un utilisateur..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                                    <button type="submit" class="btn btn-outline-primary">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </form>
                            </div>
                            <div class="col-md-3 text-end">
                                <a href="utilisateur_ajouter.php" class="btn btn-primary">
                                    <i class="fas fa-plus me-1"></i> Ajouter un utilisateur
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Liste des utilisateurs -->
                <div class="card card-dashboard">
                    <div class="card-header">
                        <i class="fas fa-users me-2"></i>
                        Liste des utilisateurs
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Nom complet</th>
                                        <th>Email</th>
                                        <th>Rôle</th>
                                        <th>Date de création</th>
                                        <th>Dernier accès</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($utilisateurs)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center">Aucun utilisateur trouvé</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($utilisateurs as $index => $utilisateur): ?>
                                            <tr>
                                                <td><?php echo $offset + $index + 1; ?></td>
                                                <td><?php echo htmlspecialchars($utilisateur['nom_complet']); ?></td>
                                                <td><?php echo htmlspecialchars($utilisateur['email']); ?></td>
                                                <td>
                                                    <?php if ($utilisateur['role'] === 'administrateur'): ?>
                                                        <span class="badge bg-danger role-badge">Administrateur</span>
                                                    <?php elseif ($utilisateur['role'] === 'magasinier'): ?>
                                                        <span class="badge bg-success role-badge">Magasinier</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-info role-badge">Commercial</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo date('d/m/Y', strtotime($utilisateur['date_creation'])); ?></td>
                                                <td>
                                                    <?php echo $utilisateur['dernier_acces'] ? date('d/m/Y H:i', strtotime($utilisateur['dernier_acces'])) : 'Jamais'; ?>
                                                </td>
                                                <td>
                                                    <?php if ($utilisateur['actif']): ?>
                                                        <span class="badge bg-success">Actif</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Inactif</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="utilisateur_modifier.php?id=<?php echo $utilisateur['id']; ?>" class="btn btn-sm btn-outline-primary" title="Modifier">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <?php if ($utilisateur['id'] != $user_id): ?>
                                                            <?php if ($utilisateur['actif']): ?>
                                                                <a href="#" class="btn btn-sm btn-outline-danger" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#supprimerModal" 
                                                                    data-id="<?php echo $utilisateur['id']; ?>"
                                                                    data-nom="<?php echo htmlspecialchars($utilisateur['nom_complet']); ?>"
                                                                    title="Désactiver">
                                                                    <i class="fas fa-user-slash"></i>
                                                                </a>
                                                            <?php else: ?>
                                                                <a href="utilisateur_reactiver.php?id=<?php echo $utilisateur['id']; ?>" class="btn btn-sm btn-outline-success" title="Réactiver">
                                                                    <i class="fas fa-user-check"></i>
                                                                </a>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Pagination -->
                    <div class="card-footer">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                Affichage de <?php echo min($offset + 1, $total_utilisateurs); ?> à <?php echo min($offset + $limit, $total_utilisateurs); ?> sur <?php echo $total_utilisateurs; ?> utilisateurs
                            </div>
                            <nav>
                                <ul class="pagination">
                                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>" aria-label="Précédent">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                    
                                    <?php for($i = max(1, $page - 2); $i <= min($page + 2, $total_pages); $i++): ?>
                                        <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>" aria-label="Suivant">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de confirmation de suppression -->
    <div class="modal fade" id="supprimerModal" tabindex="-1" aria-labelledby="supprimerModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="supprimerModalLabel">Confirmer la désactivation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Êtes-vous sûr de vouloir désactiver l'utilisateur <strong id="nomUtilisateur"></strong> ?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <a href="#" id="confirmerSuppression" class="btn btn-danger">Désactiver</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    <script>
        // Script pour le modal de confirmation de suppression
        document.addEventListener('DOMContentLoaded', function() {
            const supprimerModal = document.getElementById('supprimerModal');
            supprimerModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const id = button.getAttribute('data-id');
                const nom = button.getAttribute('data-nom');
                
                document.getElementById('nomUtilisateur').textContent = nom;
                document.getElementById('confirmerSuppression').href = 'utilisateurs.php?action=supprimer&id=' + id;
            });
        });
    </script>
</body>
</html>