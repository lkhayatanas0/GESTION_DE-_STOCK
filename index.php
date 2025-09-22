<?php
/**
 * Page d'accueil du système de gestion de stock
 * Fichier: index.php
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

// Récupérer quelques statistiques de base
try {
    $db = getDbConnection();
    
    // Nombre total de produits
    $query = "SELECT COUNT(*) as total FROM produits WHERE actif = TRUE";
    $stmt = $db->query($query);
    $total_produits = $stmt->fetch()['total'];
    
    // Nombre de produits en stock bas
    $query = "SELECT COUNT(*) as total FROM produits WHERE stock_actuel <= stock_minimal AND actif = TRUE";
    $stmt = $db->query($query);
    $produits_stock_bas = $stmt->fetch()['total'];
    
    // Nombre de commandes en attente
    $query = "SELECT COUNT(*) as total FROM commandes WHERE statut IN ('confirmee', 'preparation')";
    $stmt = $db->query($query);
    $commandes_en_attente = $stmt->fetch()['total'];
    
    // Nombre d'achats en attente
    $query = "SELECT COUNT(*) as total FROM achats WHERE statut IN ('attente', 'partiel')";
    $stmt = $db->query($query);
    $achats_en_attente = $stmt->fetch()['total'];
    
    // Récupérer les 5 derniers mouvements de stock
    $query = "SELECT m.id, m.date_mouvement, m.type, m.quantite, p.reference, p.nom, u.nom_complet
              FROM mouvements_stock m
              JOIN produits p ON m.produit_id = p.id
              JOIN utilisateurs u ON m.utilisateur_id = u.id
              ORDER BY m.date_mouvement DESC
              LIMIT 5";
    $stmt = $db->query($query);
    $derniers_mouvements = $stmt->fetchAll();
    
    // Récupérer les 5 produits les plus bas en stock (par rapport au minimum)
    $query = "SELECT id, reference, nom, stock_actuel, stock_minimal, 
              (stock_actuel - stock_minimal) as difference
              FROM produits
              WHERE actif = TRUE
              ORDER BY difference ASC
              LIMIT 5";
    $stmt = $db->query($query);
    $produits_critiques = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error_message = "Erreur de base de données: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - Azrou Sani Gestion Stock</title>
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
        .stat-card {
            padding: 15px;
            border-radius: 5px;
            color: white;
            margin-bottom: 20px;
        }
        .stat-card i {
            font-size: 2.5rem;
            opacity: 0.6;
        }
        .stat-card .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
        }
        .bg-primary-subtle {
            background-color: rgba(13, 110, 253, 0.9);
        }
        .bg-success-subtle {
            background-color: rgba(25, 135, 84, 0.9);
        }
        .bg-warning-subtle {
            background-color: rgba(255, 193, 7, 0.9);
        }
        .bg-danger-subtle {
            background-color: rgba(220, 53, 69, 0.9);
        }
        .table-responsive {
            max-height: 400px;
            overflow-y: auto;
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
                        <a class="nav-link active" href="index.php">
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
                    <h2>Tableau de bord</h2>
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

                <!-- Stats Cards -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="stat-card bg-primary-subtle">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="stat-number"><?php echo $total_produits; ?></div>
                                    <div>Total des produits</div>
                                </div>
                                <div>
                                    <i class="fas fa-boxes"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="stat-card bg-danger-subtle">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="stat-number"><?php echo $produits_stock_bas; ?></div>
                                    <div>Produits en stock bas</div>
                                </div>
                                <div>
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="stat-card bg-success-subtle">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="stat-number"><?php echo $commandes_en_attente; ?></div>
                                    <div>Commandes en attente</div>
                                </div>
                                <div>
                                    <i class="fas fa-shopping-cart"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="stat-card bg-warning-subtle">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="stat-number"><?php echo $achats_en_attente; ?></div>
                                    <div>Achats en attente</div>
                                </div>
                                <div>
                                    <i class="fas fa-truck-loading"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content Area -->
                <div class="row">
                    <!-- Produits en stock critique -->
                    <div class="col-md-6">
                        <div class="card card-dashboard">
                            <div class="card-header">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                Produits en stock critique
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Référence</th>
                                                <th>Produit</th>
                                                <th>Stock actuel</th>
                                                <th>Stock minimal</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($produits_critiques)): ?>
                                                <tr>
                                                    <td colspan="5" class="text-center">Aucun produit en stock critique</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($produits_critiques as $produit): ?>
                                                    <tr <?php echo ($produit['stock_actuel'] < $produit['stock_minimal']) ? 'class="table-danger"' : ''; ?>>
                                                        <td><?php echo htmlspecialchars($produit['reference']); ?></td>
                                                        <td><?php echo htmlspecialchars($produit['nom']); ?></td>
                                                        <td><?php echo number_format($produit['stock_actuel'], 2); ?></td>
                                                        <td><?php echo number_format($produit['stock_minimal'], 2); ?></td>
                                                        <td>
                                                            <a href="produit_details.php?id=<?php echo $produit['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="card-footer text-end">
                                <a href="produits.php?filter=stock_low" class="btn btn-sm btn-outline-danger">
                                    Voir tous les produits en stock bas
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Derniers mouvements de stock -->
                    <div class="col-md-6">
                        <div class="card card-dashboard">
                            <div class="card-header">
                                <i class="fas fa-exchange-alt me-2"></i>
                                Derniers mouvements de stock
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Produit</th>
                                                <th>Type</th>
                                                <th>Quantité</th>
                                                <th>Par</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($derniers_mouvements)): ?>
                                                <tr>
                                                    <td colspan="5" class="text-center">Aucun mouvement récent</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($derniers_mouvements as $mouvement): ?>
                                                    <tr>
                                                        <td><?php echo date('d/m/Y H:i', strtotime($mouvement['date_mouvement'])); ?></td>
                                                        <td title="<?php echo htmlspecialchars($mouvement['nom']); ?>">
                                                            <?php echo htmlspecialchars($mouvement['reference']); ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($mouvement['type'] === 'entree'): ?>
                                                                <span class="badge bg-success">Entrée</span>
                                                            <?php elseif ($mouvement['type'] === 'sortie'): ?>
                                                                <span class="badge bg-danger">Sortie</span>
                                                            <?php elseif ($mouvement['type'] === 'inventaire'): ?>
                                                                <span class="badge bg-info">Inventaire</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-warning">Ajustement</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo number_format($mouvement['quantite'], 2); ?></td>
                                                        <td><?php echo htmlspecialchars($mouvement['nom_complet']); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="card-footer text-end">
                                <a href="mouvements.php" class="btn btn-sm btn-outline-secondary">
                                    Voir tous les mouvements
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Accès rapides -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card card-dashboard">
                            <div class="card-header">
                                <i class="fas fa-bolt me-2"></i>
                                Accès rapides
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3 mb-3">
                                        <a href="commandes_ajouter.php" class="btn btn-lg btn-outline-primary w-100 h-100 d-flex flex-column justify-content-center align-items-center py-3">
                                            <i class="fas fa-plus-circle mb-2" style="font-size: 2rem;"></i>
                                            <span>Nouvelle commande</span>
                                        </a>
                                    </div>
                                    
                                    <div class="col-md-3 mb-3">
                                        <a href="achats_ajouter.php" class="btn btn-lg btn-outline-success w-100 h-100 d-flex flex-column justify-content-center align-items-center py-3">
                                            <i class="fas fa-truck mb-2" style="font-size: 2rem;"></i>
                                            <span>Nouvel achat</span>
                                        </a>
                                    </div>
                                    
                                    <div class="col-md-3 mb-3">
                                        <a href="produits_ajouter.php" class="btn btn-lg btn-outline-info w-100 h-100 d-flex flex-column justify-content-center align-items-center py-3">
                                            <i class="fas fa-box mb-2" style="font-size: 2rem;"></i>
                                            <span>Nouveau produit</span>
                                        </a>
                                    </div>
                                    
                                    <div class="col-md-3 mb-3">
                                        <a href="inventaire.php" class="btn btn-lg btn-outline-warning w-100 h-100 d-flex flex-column justify-content-center align-items-center py-3">
                                            <i class="fas fa-clipboard-list mb-2" style="font-size: 2rem;"></i>
                                            <span>Faire un inventaire</span>
                                        </a>
                                    </div>
                                </div>
                            </div>
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