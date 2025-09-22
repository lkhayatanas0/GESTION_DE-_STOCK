<?php
/**
 * Page de gestion des fournisseurs
 * Fichier: fournisseurs.php
 */

// Démarrer la session
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Inclure la configuration de la base de données
require_once 'config/db.php';

// Récupérer les informations de l'utilisateur
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];

// Vérifier les permissions (seuls les administrateurs et magasiniers peuvent accéder)
if ($user_role !== 'administrateur' && $user_role !== 'magasinier') {
    header("Location: index.php");
    exit;
}

// Traitement du formulaire de recherche
$search_query = '';
if (isset($_GET['search'])) {
    $search_query = trim($_GET['search']);
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Récupérer la liste des fournisseurs
try {
    $db = getDbConnection();
    
    // Préparer la requête de base
    $query = "SELECT * FROM fournisseurs WHERE 1=1";
    $params = [];
    
    // Ajouter les conditions de recherche
    if (!empty($search_query)) {
        $query .= " AND (raison_sociale LIKE ? OR contact_principal LIKE ? OR telephone LIKE ? OR email LIKE ?)";
        $search_param = "%$search_query%";
        $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    }
    
    // Compter le nombre total de fournisseurs
    $count_query = str_replace('SELECT *', 'SELECT COUNT(*) as total', $query);
    $stmt = $db->prepare($count_query);
    $stmt->execute($params);
    $total_fournisseurs = $stmt->fetch()['total'];
    $total_pages = ceil($total_fournisseurs / $per_page);
    
    // Ajouter le tri et la pagination
    $query .= " ORDER BY raison_sociale ASC LIMIT ? OFFSET ?";
    $params[] = $per_page;
    $params[] = $offset;
    
    // Exécuter la requête principale
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $fournisseurs = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error_message = "Erreur de base de données: " . $e->getMessage();
}

// Traitement de la suppression d'un fournisseur
if (isset($_POST['delete_fournisseur']) && $user_role === 'administrateur') {
    $fournisseur_id = (int)$_POST['fournisseur_id'];
    
    try {
        // Vérifier d'abord si le fournisseur a des achats
        $db = getDbConnection();
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM achats WHERE fournisseur_id = ?");
        $stmt->execute([$fournisseur_id]);
        $has_achats = $stmt->fetch()['total'] > 0;
        
        if ($has_achats) {
            $error_message = "Impossible de supprimer ce fournisseur car il a des achats associés.";
        } else {
            // Supprimer le fournisseur
            $stmt = $db->prepare("DELETE FROM fournisseurs WHERE id = ?");
            $stmt->execute([$fournisseur_id]);
            
            if ($stmt->rowCount() > 0) {
                $success_message = "Fournisseur supprimé avec succès.";
                // Recharger la page pour actualiser la liste
                header("Location: fournisseurs.php?deleted=1");
                exit;
            } else {
                $error_message = "Aucun fournisseur trouvé avec cet ID.";
            }
        }
    } catch (PDOException $e) {
        $error_message = "Erreur lors de la suppression du fournisseur: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Fournisseurs - Azrou Sani Gestion Stock</title>
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
        .card {
            margin-bottom: 20px;
            border: none;
            border-radius: 5px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            font-weight: bold;
        }
        .badge-fournisseur {
            background-color: #6f42c1;
        }
        .table-responsive {
            max-height: 600px;
            overflow-y: auto;
        }
        .action-buttons .btn {
            margin-right: 5px;
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
                        <a class="nav-link active" href="fournisseurs.php">
                            <i class="fas fa-industry"></i> Fournisseurs
                        </a>
                    </li>
                    <?php if ($user_role === 'administrateur'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="utilisateurs.php">
                            <i class="fas fa-user-cog"></i> Utilisateurs
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="parametres.php">
                            <i class="fas fa-cogs"></i> Paramètres
                        </a>
                    </li>
                    <?php endif; ?>
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
                    <h2>Gestion des Fournisseurs</h2>
                    <div class="user-info">
                        <span class="me-2"><?php echo htmlspecialchars($user_name); ?></span>
                        <span class="badge bg-secondary"><?php echo htmlspecialchars($user_role); ?></span>
                    </div>
                </div>

                <!-- Messages d'alerte -->
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger">
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success">
                        <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['deleted'])): ?>
                    <div class="alert alert-success">
                        Fournisseur supprimé avec succès.
                    </div>
                <?php endif; ?>

                <!-- Card principale -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-industry me-2"></i>
                            Liste des Fournisseurs
                        </div>
                        <div>
                            <a href="fournisseur_ajouter.php" class="btn btn-sm btn-success">
                                <i class="fas fa-plus me-1"></i> Nouveau Fournisseur
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Barre de recherche -->
                        <form method="get" class="mb-4">
                            <div class="row g-3">
                                <div class="col-md-9">
                                    <div class="input-group">
                                        <input type="text" name="search" class="form-control" placeholder="Rechercher un fournisseur..." value="<?php echo htmlspecialchars($search_query); ?>">
                                        <button class="btn btn-outline-secondary" type="submit">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-3 text-end">
                                    <?php if (!empty($search_query)): ?>
                                        <a href="fournisseurs.php" class="btn btn-outline-danger">
                                            <i class="fas fa-times"></i> Réinitialiser
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>

                        <!-- Tableau des fournisseurs -->
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Raison Sociale</th>
                                        <th>Contact</th>
                                        <th>Téléphone</th>
                                        <th>Email</th>
                                        <th>Ville</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($fournisseurs)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center">Aucun fournisseur trouvé</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($fournisseurs as $fournisseur): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($fournisseur['raison_sociale']); ?></td>
                                                <td><?php echo htmlspecialchars($fournisseur['contact_principal'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($fournisseur['telephone'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($fournisseur['email'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($fournisseur['ville'] ?? 'N/A'); ?></td>
                                                <td class="action-buttons">
                                                    <a href="fournisseur_details.php?id=<?php echo $fournisseur['id']; ?>" class="btn btn-sm btn-outline-primary" title="Voir détails">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="fournisseur_modifier.php?id=<?php echo $fournisseur['id']; ?>" class="btn btn-sm btn-outline-warning" title="Modifier">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <?php if ($user_role === 'administrateur'): ?>
                                                        <button class="btn btn-sm btn-outline-danger delete-btn" 
                                                                data-fournisseur-id="<?php echo $fournisseur['id']; ?>" 
                                                                data-fournisseur-name="<?php echo htmlspecialchars($fournisseur['raison_sociale']); ?>"
                                                                title="Supprimer">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Page navigation">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search_query) ? '&search='.urlencode($search_query) : ''; ?>" aria-label="Previous">
                                                <span aria-hidden="true">&laquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search_query) ? '&search='.urlencode($search_query) : ''; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search_query) ? '&search='.urlencode($search_query) : ''; ?>" aria-label="Next">
                                                <span aria-hidden="true">&raquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer text-muted">
                        Total: <?php echo $total_fournisseurs; ?> fournisseur(s) trouvé(s)
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de confirmation de suppression -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteModalLabel">Confirmer la suppression</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Êtes-vous sûr de vouloir supprimer le fournisseur <strong id="fournisseur-name-to-delete"></strong> ?</p>
                        <p class="text-danger">Cette action est irréversible !</p>
                        <input type="hidden" name="fournisseur_id" id="fournisseur-id-to-delete">
                        <input type="hidden" name="delete_fournisseur" value="1">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-danger">Supprimer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    <script>
        // Gestion de la suppression avec confirmation
        document.addEventListener('DOMContentLoaded', function() {
            const deleteButtons = document.querySelectorAll('.delete-btn');
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            
            deleteButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const fournisseurId = this.getAttribute('data-fournisseur-id');
                    const fournisseurName = this.getAttribute('data-fournisseur-name');
                    
                    document.getElementById('fournisseur-id-to-delete').value = fournisseurId;
                    document.getElementById('fournisseur-name-to-delete').textContent = fournisseurName;
                    
                    deleteModal.show();
                });
            });
        });
    </script>
</body>
</html>