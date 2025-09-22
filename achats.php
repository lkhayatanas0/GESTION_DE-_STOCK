<?php
/**
 * Page de gestion des achats
 * Fichier: achats.php
 */

// Démarrer la session
session_start();

// Vérifier si l'utilisateur est connecté et a les droits nécessaires
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Vérifier les permissions (seuls les administrateurs et magasiniers peuvent accéder)
if ($_SESSION['user_role'] === 'commercial') {
    header("Location: index.php");
    exit;
}

// Inclure la configuration de la base de données
require_once 'config/db.php';

// Récupérer les informations de l'utilisateur
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];

// Initialiser les variables pour la pagination et le filtrage
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$fournisseur_filter = isset($_GET['fournisseur']) ? (int)$_GET['fournisseur'] : 0;
$date_start = isset($_GET['date_start']) ? $_GET['date_start'] : '';
$date_end = isset($_GET['date_end']) ? $_GET['date_end'] : '';

// Construire la requête de base avec les filtres
$query = "SELECT a.*, f.raison_sociale, u.nom_complet 
          FROM achats a
          JOIN fournisseurs f ON a.fournisseur_id = f.id
          JOIN utilisateurs u ON a.utilisateur_id = u.id";

$where = [];
$params = [];

// Appliquer les filtres
if ($status_filter !== 'all') {
    $where[] = "a.statut = ?";
    $params[] = $status_filter;
}

if ($fournisseur_filter > 0) {
    $where[] = "a.fournisseur_id = ?";
    $params[] = $fournisseur_filter;
}

if (!empty($date_start)) {
    $where[] = "a.date_achat >= ?";
    $params[] = $date_start;
}

if (!empty($date_end)) {
    $where[] = "a.date_achat <= ?";
    $params[] = $date_end;
}

// Ajouter les conditions WHERE si nécessaire
if (!empty($where)) {
    $query .= " WHERE " . implode(" AND ", $where);
}

// Compter le nombre total d'achats pour la pagination
$count_query = "SELECT COUNT(*) as total FROM ($query) as total_query";
$total_achats = 0;

try {
    $db = getDbConnection();
    
    // Exécuter la requête de comptage
    $stmt = $db->prepare($count_query);
    $stmt->execute($params);
    $result = $stmt->fetch();
    $total_achats = $result['total'];
    
    // Calculer le nombre total de pages
    $total_pages = ceil($total_achats / $per_page);
    
    // Ajouter le tri et la pagination à la requête principale
    $query .= " ORDER BY a.date_achat DESC, a.id DESC LIMIT ? OFFSET ?";
    $params[] = $per_page;
    $params[] = $offset;
    
    // Exécuter la requête principale
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $achats = $stmt->fetchAll();
    
    // Récupérer la liste des fournisseurs pour le filtre
    $fournisseurs = $db->query("SELECT id, raison_sociale FROM fournisseurs ORDER BY raison_sociale")->fetchAll();
    
} catch (PDOException $e) {
    $error_message = "Erreur de base de données: " . $e->getMessage();
}

// Traitement de la suppression d'un achat
if (isset($_POST['delete_achat']) && $user_role === 'administrateur') {
    $achat_id = (int)$_POST['achat_id'];
    
    try {
        $db = getDbConnection();
        $db->beginTransaction();
        
        // Récupérer les détails de l'achat pour ajuster les stocks
        $details_query = "SELECT produit_id, quantite FROM details_achats WHERE achat_id = ?";
        $stmt = $db->prepare($details_query);
        $stmt->execute([$achat_id]);
        $details = $stmt->fetchAll();
        
        // Ajuster les stocks pour chaque produit
        foreach ($details as $detail) {
            $update_stock = "UPDATE produits SET stock_actuel = stock_actuel - ? WHERE id = ?";
            $stmt = $db->prepare($update_stock);
            $stmt->execute([$detail['quantite'], $detail['produit_id']]);
        }
        
        // Supprimer les détails de l'achat
        $delete_details = "DELETE FROM details_achats WHERE achat_id = ?";
        $stmt = $db->prepare($delete_details);
        $stmt->execute([$achat_id]);
        
        // Supprimer l'achat
        $delete_achat = "DELETE FROM achats WHERE id = ?";
        $stmt = $db->prepare($delete_achat);
        $stmt->execute([$achat_id]);
        
        $db->commit();
        
        // Rediriger pour éviter la resoumission du formulaire
        header("Location: achats.php?deleted=1");
        exit;
        
    } catch (PDOException $e) {
        $db->rollBack();
        $error_message = "Erreur lors de la suppression: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Achats - Azrou Sani Gestion Stock</title>
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
        .table-responsive {
            overflow-x: auto;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        .status-attente {
            background-color: #fff3cd;
            color: #856404;
        }
        .status-partiel {
            background-color: #cce5ff;
            color: #004085;
        }
        .status-recus {
            background-color: #d4edda;
            color: #155724;
        }
        .status-annule {
            background-color: #f8d7da;
            color: #721c24;
        }
        .pagination .page-item.active .page-link {
            background-color: #343a40;
            border-color: #343a40;
        }
        .pagination .page-link {
            color: #343a40;
        }
        .filter-section {
            background-color: white;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
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
                        <a class="nav-link active" href="achats.php">
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
                    <h2>Gestion des Achats</h2>
                    <div class="user-info">
                        <span class="me-2"><?php echo htmlspecialchars($user_name); ?></span>
                        <span class="badge bg-secondary"><?php echo htmlspecialchars($user_role); ?></span>
                    </div>
                </div>

                <?php if (isset($_GET['deleted'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        L'achat a été supprimé avec succès.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Filtres -->
                <div class="filter-section">
                    <form method="get" action="achats.php">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="status" class="form-label">Statut</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>Tous les statuts</option>
                                    <option value="attente" <?php echo $status_filter === 'attente' ? 'selected' : ''; ?>>En attente</option>
                                    <option value="partiel" <?php echo $status_filter === 'partiel' ? 'selected' : ''; ?>>Partiellement reçu</option>
                                    <option value="recu" <?php echo $status_filter === 'recu' ? 'selected' : ''; ?>>Complètement reçu</option>
                                    <option value="annule" <?php echo $status_filter === 'annule' ? 'selected' : ''; ?>>Annulé</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="fournisseur" class="form-label">Fournisseur</label>
                                <select class="form-select" id="fournisseur" name="fournisseur">
                                    <option value="0">Tous les fournisseurs</option>
                                    <?php foreach ($fournisseurs as $fournisseur): ?>
                                        <option value="<?php echo $fournisseur['id']; ?>" <?php echo $fournisseur_filter === $fournisseur['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($fournisseur['raison_sociale']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="date_start" class="form-label">Date de début</label>
                                <input type="date" class="form-control" id="date_start" name="date_start" value="<?php echo htmlspecialchars($date_start); ?>">
                            </div>
                            
                            <div class="col-md-3">
                                <label for="date_end" class="form-label">Date de fin</label>
                                <input type="date" class="form-control" id="date_end" name="date_end" value="<?php echo htmlspecialchars($date_end); ?>">
                            </div>
                            
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-filter me-1"></i> Filtrer
                                </button>
                                <a href="achats.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-1"></i> Réinitialiser
                                </a>
                                <a href="achats_ajouter.php" class="btn btn-success float-end">
                                    <i class="fas fa-plus me-1"></i> Nouvel achat
                                </a>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Liste des achats -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>
                            <i class="fas fa-list me-2"></i>
                            Liste des achats
                        </span>
                        <span class="badge bg-primary">
                            Total: <?php echo $total_achats; ?> achats
                        </span>
                    </div>
                    <div class="card-body">
                        <?php if ($total_achats === 0): ?>
                            <div class="alert alert-info">
                                Aucun achat trouvé avec les critères de recherche actuels.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Référence</th>
                                            <th>Date</th>
                                            <th>Fournisseur</th>
                                            <th>Montant HT</th>
                                            <th>Statut</th>
                                            <th>Créé par</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($achats as $achat): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($achat['reference']); ?></td>
                                                <td><?php echo date('d/m/Y', strtotime($achat['date_achat'])); ?></td>
                                                <td><?php echo htmlspecialchars($achat['raison_sociale']); ?></td>
                                                <td><?php echo number_format($achat['montant_total_ht'], 2); ?> DH</td>
                                                <td>
                                                    <?php if ($achat['statut'] === 'attente'): ?>
                                                        <span class="status-badge status-attente">En attente</span>
                                                    <?php elseif ($achat['statut'] === 'partiel'): ?>
                                                        <span class="status-badge status-partiel">Partiel</span>
                                                    <?php elseif ($achat['statut'] === 'recu'): ?>
                                                        <span class="status-badge status-recus">Reçu</span>
                                                    <?php else: ?>
                                                        <span class="status-badge status-annule">Annulé</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($achat['nom_complet']); ?></td>
                                                <td>
                                                    <a href="achats_details.php?id=<?php echo $achat['id']; ?>" class="btn btn-sm btn-outline-primary" title="Voir détails">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <?php if ($user_role === 'administrateur' && $achat['statut'] === 'attente'): ?>
                                                        <a href="achats_modifier.php?id=<?php echo $achat['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Modifier">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-sm btn-outline-danger" title="Supprimer" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $achat['id']; ?>">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                        
                                                        <!-- Modal de confirmation de suppression -->
                                                        <div class="modal fade" id="deleteModal<?php echo $achat['id']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
                                                            <div class="modal-dialog">
                                                                <div class="modal-content">
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title" id="deleteModalLabel">Confirmer la suppression</h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                    </div>
                                                                    <div class="modal-body">
                                                                        <p>Êtes-vous sûr de vouloir supprimer l'achat <strong><?php echo htmlspecialchars($achat['reference']); ?></strong> ?</p>
                                                                        <p class="text-danger">Cette action est irréversible et affectera les niveaux de stock.</p>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                                                        <form method="post" style="display: inline;">
                                                                            <input type="hidden" name="achat_id" value="<?php echo $achat['id']; ?>">
                                                                            <button type="submit" name="delete_achat" class="btn btn-danger">Supprimer</button>
                                                                        </form>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination -->
                            <nav aria-label="Page navigation">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" aria-label="Previous">
                                                <span aria-hidden="true">&laquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" aria-label="Next">
                                                <span aria-hidden="true">&raquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    <script>
        // Activer les tooltips Bootstrap
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Définir la date de fin comme aujourd'hui par défaut
            if (!document.getElementById('date_end').value) {
                var today = new Date();
                var dd = String(today.getDate()).padStart(2, '0');
                var mm = String(today.getMonth() + 1).padStart(2, '0');
                var yyyy = today.getFullYear();
                document.getElementById('date_end').value = yyyy + '-' + mm + '-' + dd;
            }
        });
    </script>
</body>
</html>