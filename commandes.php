<?php
/**
 * Page de gestion des commandes
 * Fichier: commandes.php
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

// Initialiser les variables de filtrage
$statut_filter = isset($_GET['statut']) ? $_GET['statut'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$date_debut = isset($_GET['date_debut']) ? $_GET['date_debut'] : '';
$date_fin = isset($_GET['date_fin']) ? $_GET['date_fin'] : '';

// Construire la requête SQL avec les filtres
$sql_where = "WHERE 1=1";
$params = [];

if (!empty($statut_filter)) {
    $sql_where .= " AND c.statut = :statut";
    $params[':statut'] = $statut_filter;
}

if (!empty($search)) {
    $sql_where .= " AND (c.reference LIKE :search OR cl.nom LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($date_debut)) {
    $sql_where .= " AND c.date_commande >= :date_debut";
    $params[':date_debut'] = $date_debut . ' 00:00:00';
}

if (!empty($date_fin)) {
    $sql_where .= " AND c.date_commande <= :date_fin";
    $params[':date_fin'] = $date_fin . ' 23:59:59';
}

// Récupérer les commandes avec pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

try {
    $db = getDbConnection();
    
    // Compter le nombre total de commandes pour la pagination
    $count_sql = "SELECT COUNT(*) as total FROM commandes c $sql_where";
    $count_stmt = $db->prepare($count_sql);
    foreach ($params as $key => $value) {
        $count_stmt->bindValue($key, $value);
    }
    $count_stmt->execute();
    $total_commandes = $count_stmt->fetch()['total'];
    $total_pages = ceil($total_commandes / $per_page);
    
    // Récupérer les commandes
    $sql = "SELECT c.*, cl.nom as client_nom, u.nom_complet as utilisateur_nom
            FROM commandes c
            JOIN clients cl ON c.client_id = cl.id
            JOIN utilisateurs u ON c.utilisateur_id = u.id
            $sql_where
            ORDER BY c.date_commande DESC
            LIMIT :offset, :per_page";
    
    $stmt = $db->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
    $stmt->execute();
    $commandes = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error_message = "Erreur de base de données: " . $e->getMessage();
}

// Gérer les actions sur les commandes
if (isset($_POST['action']) && isset($_POST['commande_id'])) {
    $commande_id = intval($_POST['commande_id']);
    $action = $_POST['action'];
    
    try {
        $db = getDbConnection();
        
        switch ($action) {
            case 'confirmer':
                $sql = "UPDATE commandes SET statut = 'confirmee' WHERE id = :id";
                break;
            case 'preparer':
                $sql = "UPDATE commandes SET statut = 'preparation' WHERE id = :id";
                break;
            case 'livrer':
                $sql = "UPDATE commandes SET statut = 'livree' WHERE id = :id";
                break;
            case 'annuler':
                // Pour annuler, nous devons remettre les produits en stock
                $db->beginTransaction();
                
                // Récupérer les détails de la commande
                $details_sql = "SELECT produit_id, quantite FROM details_commandes WHERE commande_id = :id";
                $details_stmt = $db->prepare($details_sql);
                $details_stmt->bindValue(':id', $commande_id, PDO::PARAM_INT);
                $details_stmt->execute();
                $details = $details_stmt->fetchAll();
                
                // Mettre à jour le stock pour chaque produit
                foreach ($details as $detail) {
                    $update_stock_sql = "UPDATE produits SET stock_actuel = stock_actuel + :quantite WHERE id = :produit_id";
                    $update_stock_stmt = $db->prepare($update_stock_sql);
                    $update_stock_stmt->bindValue(':quantite', $detail['quantite']);
                    $update_stock_stmt->bindValue(':produit_id', $detail['produit_id'], PDO::PARAM_INT);
                    $update_stock_stmt->execute();
                    
                    // Enregistrer le mouvement de stock (entrée due à annulation)
                    $mouvement_sql = "INSERT INTO mouvements_stock (produit_id, type, quantite, utilisateur_id, document_type, document_id, notes)
                                    VALUES (:produit_id, 'entree', :quantite, :user_id, 'commande', :commande_id, 'Annulation de commande')";
                    $mouvement_stmt = $db->prepare($mouvement_sql);
                    $mouvement_stmt->bindValue(':produit_id', $detail['produit_id'], PDO::PARAM_INT);
                    $mouvement_stmt->bindValue(':quantite', $detail['quantite']);
                    $mouvement_stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
                    $mouvement_stmt->bindValue(':commande_id', $commande_id, PDO::PARAM_INT);
                    $mouvement_stmt->execute();
                }
                
                // Mettre à jour le statut de la commande
                $status_sql = "UPDATE commandes SET statut = 'annulee' WHERE id = :id";
                $status_stmt = $db->prepare($status_sql);
                $status_stmt->bindValue(':id', $commande_id, PDO::PARAM_INT);
                $status_stmt->execute();
                
                $db->commit();
                break;
        }
        
        if ($action != 'annuler') {
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':id', $commande_id, PDO::PARAM_INT);
            $stmt->execute();
        }
        
        // Rediriger pour éviter la soumission multiple du formulaire
        header("Location: commandes.php" . (empty($_SERVER['QUERY_STRING']) ? '' : '?' . $_SERVER['QUERY_STRING']));
        exit;
        
    } catch (PDOException $e) {
        $error_message = "Erreur lors du traitement de la commande: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Commandes - Azrou Sani Gestion Stock</title>
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
        .badge-brouillon { background-color: #6c757d; }
        .badge-confirmee { background-color: #0d6efd; }
        .badge-preparation { background-color: #ffc107; color: #000; }
        .badge-livree { background-color: #198754; }
        .badge-annulee { background-color: #dc3545; }
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
                        <a class="nav-link active" href="commandes.php">
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
                    <h2>Gestion des Commandes</h2>
                    <div class="user-info">
                        <span class="me-2"><?php echo htmlspecialchars($user_name); ?></span>
                        <span class="badge bg-secondary"><?php echo htmlspecialchars($user_role); ?></span>
                    </div>
                </div>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger">
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <!-- Actions rapides et filtres -->
                <div class="card card-dashboard mb-4">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <form method="get" class="row g-3">
                                    <div class="col-md-3">
                                        <label for="statut" class="form-label">Statut</label>
                                        <select name="statut" id="statut" class="form-select">
                                            <option value="">Tous</option>
                                            <option value="brouillon" <?php echo $statut_filter === 'brouillon' ? 'selected' : ''; ?>>Brouillon</option>
                                            <option value="confirmee" <?php echo $statut_filter === 'confirmee' ? 'selected' : ''; ?>>Confirmée</option>
                                            <option value="preparation" <?php echo $statut_filter === 'preparation' ? 'selected' : ''; ?>>En préparation</option>
                                            <option value="livree" <?php echo $statut_filter === 'livree' ? 'selected' : ''; ?>>Livrée</option>
                                            <option value="annulee" <?php echo $statut_filter === 'annulee' ? 'selected' : ''; ?>>Annulée</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="date_debut" class="form-label">Date début</label>
                                        <input type="date" class="form-control" id="date_debut" name="date_debut" value="<?php echo $date_debut; ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label for="date_fin" class="form-label">Date fin</label>
                                        <input type="date" class="form-control" id="date_fin" name="date_fin" value="<?php echo $date_fin; ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label for="search" class="form-label">Recherche</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="search" name="search" placeholder="Référence, Client..." value="<?php echo htmlspecialchars($search); ?>">
                                            <button class="btn btn-outline-secondary" type="submit">
                                                <i class="fas fa-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            <div class="col-md-4 text-end">
                                <a href="commandes_ajouter.php" class="btn btn-primary">
                                    <i class="fas fa-plus-circle me-2"></i>Nouvelle commande
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Liste des commandes -->
                <div class="card card-dashboard">
                    <div class="card-header">
                        <i class="fas fa-list me-2"></i>
                        Liste des commandes
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Référence</th>
                                        <th>Client</th>
                                        <th>Date</th>
                                        <th>Livraison prévue</th>
                                        <th>Montant HT</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($commandes)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center">Aucune commande trouvée</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($commandes as $commande): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($commande['reference']); ?></td>
                                                <td><?php echo htmlspecialchars($commande['client_nom']); ?></td>
                                                <td><?php echo date('d/m/Y', strtotime($commande['date_commande'])); ?></td>
                                                <td>
                                                    <?php echo $commande['date_livraison_prevue'] ? date('d/m/Y', strtotime($commande['date_livraison_prevue'])) : '-'; ?>
                                                </td>
                                                <td><?php echo number_format($commande['montant_total_ht'], 2, ',', ' ') . ' DH'; ?></td>
                                                <td>
                                                    <?php 
                                                    $statut_class = 'badge-' . $commande['statut']; 
                                                    $statut_label = '';
                                                    
                                                    switch ($commande['statut']) {
                                                        case 'brouillon': $statut_label = 'Brouillon'; break;
                                                        case 'confirmee': $statut_label = 'Confirmée'; break;
                                                        case 'preparation': $statut_label = 'En préparation'; break;
                                                        case 'livree': $statut_label = 'Livrée'; break;
                                                        case 'annulee': $statut_label = 'Annulée'; break;
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $statut_class; ?>"><?php echo $statut_label; ?></span>
                                                </td>
                                                <td>
                                                    <div class="btn-group">
                                                        <a href="commande_details.php?id=<?php echo $commande['id']; ?>" class="btn btn-sm btn-outline-primary" title="Voir détails">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        
                                                        <?php if ($commande['statut'] !== 'annulee' && $commande['statut'] !== 'livree'): ?>
                                                            <a href="commande_modifier.php?id=<?php echo $commande['id']; ?>" class="btn btn-sm btn-outline-warning" title="Modifier">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            
                                                            <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="Actions">
                                                                <i class="fas fa-cog"></i>
                                                            </button>
                                                            <ul class="dropdown-menu">
                                                                <?php if ($commande['statut'] === 'brouillon'): ?>
                                                                    <li>
                                                                        <form method="post" style="display: inline;">
                                                                            <input type="hidden" name="commande_id" value="<?php echo $commande['id']; ?>">
                                                                            <input type="hidden" name="action" value="confirmer">
                                                                            <button type="submit" class="dropdown-item">
                                                                                <i class="fas fa-check me-2"></i>Confirmer
                                                                            </button>
                                                                        </form>
                                                                    </li>
                                                                <?php endif; ?>
                                                                
                                                                <?php if ($commande['statut'] === 'confirmee'): ?>
                                                                    <li>
                                                                        <form method="post" style="display: inline;">
                                                                            <input type="hidden" name="commande_id" value="<?php echo $commande['id']; ?>">
                                                                            <input type="hidden" name="action" value="preparer">
                                                                            <button type="submit" class="dropdown-item">
                                                                                <i class="fas fa-box me-2"></i>Marquer en préparation
                                                                            </button>
                                                                        </form>
                                                                    </li>
                                                                <?php endif; ?>
                                                                
                                                                <?php if ($commande['statut'] === 'preparation'): ?>
                                                                    <li>
                                                                        <form method="post" style="display: inline;">
                                                                            <input type="hidden" name="commande_id" value="<?php echo $commande['id']; ?>">
                                                                            <input type="hidden" name="action" value="livrer">
                                                                            <button type="submit" class="dropdown-item">
                                                                                <i class="fas fa-truck me-2"></i>Marquer comme livrée
                                                                            </button>
                                                                        </form>
                                                                    </li>
                                                                <?php endif; ?>
                                                                
                                                                <li>
                                                                    <form method="post" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir annuler cette commande ? Cette action remettra les produits en stock.');">
                                                                        <input type="hidden" name="commande_id" value="<?php echo $commande['id']; ?>">
                                                                        <input type="hidden" name="action" value="annuler">
                                                                        <button type="submit" class="dropdown-item text-danger">
                                                                            <i class="fas fa-times me-2"></i>Annuler
                                                                        </button>
                                                                    </form>
                                                                </li>
                                                            </ul>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($user_role === 'administrateur'): ?>
                                                            <form method="post" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer définitivement cette commande ?');">
                                                                <input type="hidden" name="commande_id" value="<?php echo $commande['id']; ?>">
                                                                <input type="hidden" name="action" value="supprimer">
                                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Supprimer">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
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
                                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($_SERVER['QUERY_STRING']) ? '&' . preg_replace('/page=\d+&?/', '', $_SERVER['QUERY_STRING']) : ''; ?>" aria-label="Précédent">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                    
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($_SERVER['QUERY_STRING']) ? '&' . preg_replace('/page=\d+&?/', '', $_SERVER['QUERY_STRING']) : ''; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($_SERVER['QUERY_STRING']) ? '&' . preg_replace('/page=\d+&?/', '', $_SERVER['QUERY_STRING']) : ''; ?>" aria-label="Suivant">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Statistiques des commandes -->
                <div class="card card-dashboard mt-4">
                    <div class="card-header">
                        <i class="fas fa-chart-bar me-2"></i>
                        Statistiques des commandes
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php
                            // Récupérer les statistiques des commandes par statut
                            try {
                                $stats_sql = "SELECT statut, COUNT(*) as total FROM commandes GROUP BY statut";
                                $stats_stmt = $db->query($stats_sql);
                                $stats = $stats_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                                
                                $status_colors = [
                                    'brouillon' => 'secondary',
                                    'confirmee' => 'primary',
                                    'preparation' => 'warning',
                                    'livree' => 'success',
                                    'annulee' => 'danger'
                                ];
                                
                                $status_labels = [
                                    'brouillon' => 'Brouillons',
                                    'confirmee' => 'Confirmées',
                                    'preparation' => 'En préparation',
                                    'livree' => 'Livrées',
                                    'annulee' => 'Annulées'
                                ];
                                
                                foreach ($status_labels as $status_key => $status_label) {
                                    $count = isset($stats[$status_key]) ? $stats[$status_key] : 0;
                                    $color = $status_colors[$status_key];
                                    ?>
                                    <div class="col-md-2 mb-3">
                                        <div class="card bg-<?php echo $color; ?> text-white">
                                            <div class="card-body text-center">
                                                <h3 class="mb-0"><?php echo $count; ?></h3>
                                                <div><?php echo $status_label; ?></div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php
                                }
                                
                                // Total des commandes
                                $total = array_sum($stats);
                                ?>
                                <div class="col-md-2 mb-3">
                                    <div class="card bg-dark text-white">
                                        <div class="card-body text-center">
                                            <h3 class="mb-0"><?php echo $total; ?></h3>
                                            <div>Total</div>
                                        </div>
                                    </div>
                                </div>
                                <?php
                            } catch (PDOException $e) {
                                echo '<div class="alert alert-danger">Erreur lors du chargement des statistiques</div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
</body>
</html>