<?php
/**
 * Page de gestion du stock
 * Fichier: stock.php
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

// Initialisation des variables
$search = isset($_GET['search']) ? $_GET['search'] : '';
$categorie = isset($_GET['categorie']) ? $_GET['categorie'] : '';
$emplacement = isset($_GET['emplacement']) ? $_GET['emplacement'] : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$items_per_page = 10;
$offset = ($page - 1) * $items_per_page;

// Message de succès après une opération
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
unset($_SESSION['success_message']);

try {
    $db = getDbConnection();
    
    // Récupérer toutes les catégories pour le filtre
    $query_categories = "SELECT id, nom FROM categories ORDER BY nom";
    $stmt_categories = $db->query($query_categories);
    $categories = $stmt_categories->fetchAll();
    
    // Récupérer tous les emplacements pour le filtre
    $query_emplacements = "SELECT id, nom FROM emplacements WHERE actif = TRUE ORDER BY nom";
    $stmt_emplacements = $db->query($query_emplacements);
    $emplacements = $stmt_emplacements->fetchAll();
    
    // Construction de la requête de base pour les produits
    $query = "SELECT p.id, p.reference, p.nom, p.stock_actuel, p.stock_minimal, 
                     p.unite_mesure, c.nom AS categorie_nom, u.libelle AS unite,
                     IFNULL((SELECT SUM(se.quantite) FROM stock_emplacements se WHERE se.produit_id = p.id), 0) AS stock_total_emplacements
              FROM produits p
              LEFT JOIN categories c ON p.categorie_id = c.id
              LEFT JOIN unites_mesure u ON p.unite_mesure = u.code
              WHERE p.actif = TRUE";
    
    // Ajouter les conditions de recherche et filtre
    $params = [];
    
    if (!empty($search)) {
        $query .= " AND (p.reference LIKE :search OR p.nom LIKE :search)";
        $params[':search'] = "%$search%";
    }
    
    if (!empty($categorie)) {
        $query .= " AND p.categorie_id = :categorie";
        $params[':categorie'] = $categorie;
    }
    
    if ($filter === 'stock_low') {
        $query .= " AND p.stock_actuel <= p.stock_minimal";
    }
    
    if ($filter === 'stock_mismatch') {
        $query .= " AND p.stock_actuel != IFNULL((SELECT SUM(se.quantite) FROM stock_emplacements se WHERE se.produit_id = p.id), 0)";
    }
    
    // Compte total pour la pagination
    $query_count = str_replace("SELECT p.id, p.reference, p.nom, p.stock_actuel, p.stock_minimal, 
                     p.unite_mesure, c.nom AS categorie_nom, u.libelle AS unite,
                     IFNULL((SELECT SUM(se.quantite) FROM stock_emplacements se WHERE se.produit_id = p.id), 0) AS stock_total_emplacements", 
                   "SELECT COUNT(*) as total", $query);
    
    $stmt_count = $db->prepare($query_count);
    foreach ($params as $key => $value) {
        $stmt_count->bindValue($key, $value);
    }
    $stmt_count->execute();
    $total_items = $stmt_count->fetch()['total'];
    $total_pages = ceil($total_items / $items_per_page);
    
    // Ajouter la pagination à la requête principale
    $query .= " ORDER BY p.reference ASC LIMIT :offset, :limit";
    $params[':offset'] = $offset;
    $params[':limit'] = $items_per_page;
    
    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    $produits = $stmt->fetchAll();
    
    // Si un emplacement spécifique est sélectionné, récupérer les détails de stock pour cet emplacement
    if (!empty($emplacement)) {
        $query_emplacement_stock = "SELECT p.id, p.reference, p.nom, se.quantite, p.unite_mesure, u.libelle AS unite
                                    FROM stock_emplacements se
                                    JOIN produits p ON se.produit_id = p.id
                                    LEFT JOIN unites_mesure u ON p.unite_mesure = u.code
                                    WHERE se.emplacement_id = :emplacement_id
                                    ORDER BY p.reference ASC";
        
        $stmt_emplacement_stock = $db->prepare($query_emplacement_stock);
        $stmt_emplacement_stock->bindValue(':emplacement_id', $emplacement, PDO::PARAM_INT);
        $stmt_emplacement_stock->execute();
        $emplacement_stock = $stmt_emplacement_stock->fetchAll();
        
        // Récupérer les informations de l'emplacement
        $query_emplacement_info = "SELECT nom, description FROM emplacements WHERE id = :emplacement_id";
        $stmt_emplacement_info = $db->prepare($query_emplacement_info);
        $stmt_emplacement_info->bindValue(':emplacement_id', $emplacement, PDO::PARAM_INT);
        $stmt_emplacement_info->execute();
        $emplacement_info = $stmt_emplacement_info->fetch();
    }
    
} catch (PDOException $e) {
    $error_message = "Erreur de base de données: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion du Stock - Azrou Sani Gestion Stock</title>
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
            overflow-x: auto;
        }
        .stock-warning {
            color: #dc3545;
            font-weight: bold;
        }
        .stock-mismatch {
            background-color: #fff3cd;
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
                        <a class="nav-link active" href="stock.php">
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
                    <h2>Gestion du Stock</h2>
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

                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success">
                        <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>

                <!-- Filtres et recherche -->
                <div class="card card-dashboard mb-4">
                    <div class="card-header">
                        <i class="fas fa-filter me-2"></i>
                        Filtres et recherche
                    </div>
                    <div class="card-body">
                        <form action="stock.php" method="GET" class="row g-3">
                            <div class="col-md-3">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control" name="search" placeholder="Rechercher par référence ou nom" value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <select name="categorie" class="form-select">
                                    <option value="">Toutes les catégories</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>" <?php echo ($categorie == $cat['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['nom']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select name="emplacement" class="form-select">
                                    <option value="">Tous les emplacements</option>
                                    <?php foreach ($emplacements as $emp): ?>
                                        <option value="<?php echo $emp['id']; ?>" <?php echo ($emplacement == $emp['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($emp['nom']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="filter" class="form-select">
                                    <option value="">Tous les produits</option>
                                    <option value="stock_low" <?php echo ($filter === 'stock_low') ? 'selected' : ''; ?>>Stock bas</option>
                                    <option value="stock_mismatch" <?php echo ($filter === 'stock_mismatch') ? 'selected' : ''; ?>>Incohérence de stock</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">Filtrer</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Boutons d'action rapide -->
                <div class="mb-4">
                    <div class="row">
                        <div class="col-md-3">
                            <a href="ajustement_stock.php" class="btn btn-success w-100">
                                <i class="fas fa-balance-scale me-2"></i>
                                Ajustement de stock
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="transfert_stock.php" class="btn btn-info w-100 text-white">
                                <i class="fas fa-exchange-alt me-2"></i>
                                Transfert entre emplacements
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="inventaire.php" class="btn btn-warning w-100">
                                <i class="fas fa-clipboard-list me-2"></i>
                                Faire un inventaire
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="emplacements.php" class="btn btn-secondary w-100">
                                <i class="fas fa-map-marker-alt me-2"></i>
                                Gérer les emplacements
                            </a>
                        </div>
                    </div>
                </div>

                <?php if (!empty($emplacement) && isset($emplacement_info)): ?>
                <!-- Stock par emplacement sélectionné -->
                <div class="card card-dashboard mb-4">
                    <div class="card-header">
                        <i class="fas fa-map-marker-alt me-2"></i>
                        Stock dans l'emplacement: <?php echo htmlspecialchars($emplacement_info['nom']); ?>
                        <?php if (!empty($emplacement_info['description'])): ?>
                            <small class="text-muted ms-2">(<?php echo htmlspecialchars($emplacement_info['description']); ?>)</small>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (empty($emplacement_stock)): ?>
                            <div class="alert alert-info">
                                Aucun produit n'est stocké dans cet emplacement.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Référence</th>
                                            <th>Nom du produit</th>
                                            <th>Quantité</th>
                                            <th>Unité</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($emplacement_stock as $item): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($item['reference']); ?></td>
                                                <td><?php echo htmlspecialchars($item['nom']); ?></td>
                                                <td><?php echo number_format($item['quantite'], 2); ?></td>
                                                <td><?php echo htmlspecialchars($item['unite']); ?></td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="produit_details.php?id=<?php echo $item['id']; ?>" class="btn btn-outline-primary" title="Voir détails du produit">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="ajustement_stock.php?produit_id=<?php echo $item['id']; ?>&emplacement_id=<?php echo $emplacement; ?>" class="btn btn-outline-success" title="Ajuster le stock">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="transfert_stock.php?produit_id=<?php echo $item['id']; ?>&emplacement_source=<?php echo $emplacement; ?>" class="btn btn-outline-info" title="Transférer">
                                                            <i class="fas fa-exchange-alt"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Liste générale du stock -->
                <div class="card card-dashboard">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-list me-2"></i>
                            État du stock global
                        </div>
                        <a href="export_stock.php" class="btn btn-sm btn-outline-success">
                            <i class="fas fa-file-excel me-1"></i>
                            Exporter le stock
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Référence</th>
                                        <th>Produit</th>
                                        <th>Catégorie</th>
                                        <th>Stock actuel</th>
                                        <th>Stock minimal</th>
                                        <th>Stock par emplacements</th>
                                        <th>Unité</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($produits)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center">Aucun produit trouvé</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($produits as $produit): ?>
                                            <?php 
                                                $stock_mismatch = abs($produit['stock_actuel'] - $produit['stock_total_emplacements']) > 0.01;
                                            ?>
                                            <tr class="<?php echo $stock_mismatch ? 'stock-mismatch' : ''; ?>">
                                                <td><?php echo htmlspecialchars($produit['reference']); ?></td>
                                                <td><?php echo htmlspecialchars($produit['nom']); ?></td>
                                                <td><?php echo htmlspecialchars($produit['categorie_nom']); ?></td>
                                                <td <?php echo ($produit['stock_actuel'] <= $produit['stock_minimal']) ? 'class="stock-warning"' : ''; ?>>
                                                    <?php echo number_format($produit['stock_actuel'], 2); ?>
                                                    <?php if ($produit['stock_actuel'] <= $produit['stock_minimal']): ?>
                                                        <i class="fas fa-exclamation-triangle ms-1" title="Stock bas"></i>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo number_format($produit['stock_minimal'], 2); ?></td>
                                                <td>
                                                    <?php echo number_format($produit['stock_total_emplacements'], 2); ?>
                                                    <?php if ($stock_mismatch): ?>
                                                        <i class="fas fa-exclamation-circle ms-1 text-warning" title="Incohérence détectée"></i>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($produit['unite']); ?></td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="produit_details.php?id=<?php echo $produit['id']; ?>" class="btn btn-outline-primary" title="Voir détails">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="stock_details.php?id=<?php echo $produit['id']; ?>" class="btn btn-outline-info" title="Détails du stock">
                                                            <i class="fas fa-warehouse"></i>
                                                        </a>
                                                        <a href="ajustement_stock.php?produit_id=<?php echo $produit['id']; ?>" class="btn btn-outline-success" title="Ajuster le stock">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
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
                            <nav aria-label="Page navigation" class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&categorie=<?php echo urlencode($categorie); ?>&emplacement=<?php echo urlencode($emplacement); ?>&filter=<?php echo urlencode($filter); ?>">
                                            Précédent
                                        </a>
                                    </li>
                                    
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&categorie=<?php echo urlencode($categorie); ?>&emplacement=<?php echo urlencode($emplacement); ?>&filter=<?php echo urlencode($filter); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&categorie=<?php echo urlencode($categorie); ?>&emplacement=<?php echo urlencode($emplacement); ?>&filter=<?php echo urlencode($filter); ?>">
                                            Suivant
                                        </a>
                                    </li>
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
</body>
</html>