<?php
/**
 * Page de gestion des mouvements de stock
 * Fichier: mouvements.php
 */

// Démarrer la session
session_start();

// Vérifier si l'utilisateur est connecté et a le rôle approprié
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['administrateur', 'gestionnaire'])) {
    header("Location: login.php");
    exit;
}

// Inclure la configuration de la base de données
require_once 'config/db.php';

// Initialiser les variables
$error_message = '';
$success_message = '';
$mouvements = [];
$filters = [
    'type' => '',
    'produit_id' => '',
    'date_debut' => '',
    'date_fin' => '',
    'utilisateur_id' => ''
];

// Récupérer les filtres s'ils existent
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $filters['type'] = $_GET['type'] ?? '';
    $filters['produit_id'] = $_GET['produit_id'] ?? '';
    $filters['date_debut'] = $_GET['date_debut'] ?? '';
    $filters['date_fin'] = $_GET['date_fin'] ?? '';
    $filters['utilisateur_id'] = $_GET['utilisateur_id'] ?? '';
}

// Récupérer la liste des produits pour le filtre
try {
    $db = getDbConnection();
    $query = "SELECT id, reference, nom FROM produits ORDER BY nom ASC";
    $stmt = $db->query($query);
    $produits = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Erreur lors de la récupération des produits: " . $e->getMessage();
}

// Récupérer la liste des utilisateurs pour le filtre
try {
    $query = "SELECT id, nom_complet FROM utilisateurs ORDER BY nom_complet ASC";
    $stmt = $db->query($query);
    $utilisateurs = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Erreur lors de la récupération des utilisateurs: " . $e->getMessage();
}

// Construire la requête SQL avec les filtres
try {
    $query = "SELECT m.id, m.date_mouvement, m.type, m.quantite, 
                 p.reference as produit_reference, p.nom as produit_nom,
                 u.nom_complet as utilisateur_nom
          FROM mouvements_stock m
          JOIN produits p ON m.produit_id = p.id
          JOIN utilisateurs u ON m.utilisateur_id = u.id
          WHERE 1=1";
    
    $params = [];
    
    // Filtre par type
    if (!empty($filters['type'])) {
        $query .= " AND m.type = ?";
        $params[] = $filters['type'];
    }
    
    // Filtre par produit
    if (!empty($filters['produit_id'])) {
        $query .= " AND m.produit_id = ?";
        $params[] = $filters['produit_id'];
    }
    
    // Filtre par date de début
    if (!empty($filters['date_debut'])) {
        $query .= " AND m.date_mouvement >= ?";
        $params[] = $filters['date_debut'] . ' 00:00:00';
    }
    
    // Filtre par date de fin
    if (!empty($filters['date_fin'])) {
        $query .= " AND m.date_mouvement <= ?";
        $params[] = $filters['date_fin'] . ' 23:59:59';
    }
    
    // Filtre par utilisateur
    if (!empty($filters['utilisateur_id'])) {
        $query .= " AND m.utilisateur_id = ?";
        $params[] = $filters['utilisateur_id'];
    }
    
    $query .= " ORDER BY m.date_mouvement DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $mouvements = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error_message = "Erreur lors de la récupération des mouvements: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mouvements de Stock - Azrou Sani Gestion Stock</title>
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
            max-height: 600px;
            overflow-y: auto;
        }
        .badge-entree {
            background-color: #28a745;
        }
        .badge-sortie {
            background-color: #dc3545;
        }
        .badge-inventaire {
            background-color: #17a2b8;
        }
        .badge-ajustement {
            background-color: #ffc107;
            color: #212529;
        }
        .filter-section {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            

            <!-- Main Content -->
            <div class="col-md-10 content">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-exchange-alt me-2"></i> Mouvements de Stock</h2>
                    <div class="user-info">
                        <span class="me-2"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                        <span class="badge bg-secondary"><?php echo htmlspecialchars($_SESSION['user_role']); ?></span>
                    </div>
                </div>

                <!-- Messages d'erreur/succès -->
                <?php if ($error_message): ?>
                    <div class="alert alert-danger">
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <?php if ($success_message): ?>
                    <div class="alert alert-success">
                        <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>

                <!-- Filtres -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-filter me-2"></i>Filtres
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="type" class="form-label">Type de mouvement</label>
                                <select class="form-select" id="type" name="type">
                                    <option value="">Tous les types</option>
                                    <option value="entree" <?php echo $filters['type'] === 'entree' ? 'selected' : ''; ?>>Entrée</option>
                                    <option value="sortie" <?php echo $filters['type'] === 'sortie' ? 'selected' : ''; ?>>Sortie</option>
                                    <option value="inventaire" <?php echo $filters['type'] === 'inventaire' ? 'selected' : ''; ?>>Inventaire</option>
                                    <option value="ajustement" <?php echo $filters['type'] === 'ajustement' ? 'selected' : ''; ?>>Ajustement</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="produit_id" class="form-label">Produit</label>
                                <select class="form-select" id="produit_id" name="produit_id">
                                    <option value="">Tous les produits</option>
                                    <?php foreach ($produits as $produit): ?>
                                        <option value="<?php echo $produit['id']; ?>" <?php echo $filters['produit_id'] == $produit['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($produit['reference']); ?> - <?php echo htmlspecialchars($produit['nom']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label for="date_debut" class="form-label">Date début</label>
                                <input type="date" class="form-control" id="date_debut" name="date_debut" 
                                       value="<?php echo htmlspecialchars($filters['date_debut']); ?>">
                            </div>
                            
                            <div class="col-md-2">
                                <label for="date_fin" class="form-label">Date fin</label>
                                <input type="date" class="form-control" id="date_fin" name="date_fin" 
                                       value="<?php echo htmlspecialchars($filters['date_fin']); ?>">
                            </div>
                            
                            <div class="col-md-2">
                                <label for="utilisateur_id" class="form-label">Utilisateur</label>
                                <select class="form-select" id="utilisateur_id" name="utilisateur_id">
                                    <option value="">Tous les utilisateurs</option>
                                    <?php foreach ($utilisateurs as $utilisateur): ?>
                                        <option value="<?php echo $utilisateur['id']; ?>" <?php echo $filters['utilisateur_id'] == $utilisateur['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($utilisateur['nom_complet']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-12 d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-filter me-1"></i> Filtrer
                                </button>
                                <a href="mouvements.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-1"></i> Réinitialiser
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tableau des mouvements -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-list me-2"></i> Liste des mouvements
                        </div>
                        <div>
                            <a href="mouvement_ajouter.php" class="btn btn-sm btn-success">
                                <i class="fas fa-plus me-1"></i> Nouveau mouvement
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th width="15%">Date</th>
                                        <th width="15%">Référence</th>
                                        <th width="20%">Produit</th>
                                        <th width="10%">Type</th>
                                        <th width="10%">Quantité</th>
                                        <th width="20%">Utilisateur</th>
                                        <th width="10%">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($mouvements)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center">Aucun mouvement trouvé</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($mouvements as $mouvement): ?>
                                            <tr>
                                                <td><?php echo date('d/m/Y H:i', strtotime($mouvement['date_mouvement'])); ?></td>
                                                <td><?php echo htmlspecialchars($mouvement['produit_reference']); ?></td>                                                <td>
                                                    <?php echo htmlspecialchars($mouvement['produit_reference']); ?> - 
                                                    <?php echo htmlspecialchars($mouvement['produit_nom']); ?>
                                                </td>
                                                <td>
                                                    <?php if ($mouvement['type'] === 'entree'): ?>
                                                        <span class="badge badge-entree">Entrée</span>
                                                    <?php elseif ($mouvement['type'] === 'sortie'): ?>
                                                        <span class="badge badge-sortie">Sortie</span>
                                                    <?php elseif ($mouvement['type'] === 'inventaire'): ?>
                                                        <span class="badge badge-inventaire">Inventaire</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-ajustement">Ajustement</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo number_format($mouvement['quantite'], 2); ?></td>
                                                <td><?php echo htmlspecialchars($mouvement['utilisateur_nom']); ?></td>
                                                <td>
                                                    <a href="mouvement_details.php?id=<?php echo $mouvement['id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary" 
                                                       title="Voir les détails">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <?php if (in_array($_SESSION['user_role'], ['administrateur'])): ?>
                                                        <a href="mouvement_modifier.php?id=<?php echo $mouvement['id']; ?>" 
                                                           class="btn btn-sm btn-outline-warning" 
                                                           title="Modifier">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="badge bg-primary">Entrée</span>
                                <span class="badge bg-danger">Sortie</span>
                                <span class="badge bg-info">Inventaire</span>
                                <span class="badge bg-warning text-dark">Ajustement</span>
                            </div>
                            <div>
                                <span class="text-muted">
                                    Total: <?php echo count($mouvements); ?> mouvement(s)
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    <script>
        // Script pour gérer la date de fin >= date de début
        document.addEventListener('DOMContentLoaded', function() {
            const dateDebut = document.getElementById('date_debut');
            const dateFin = document.getElementById('date_fin');
            
            dateDebut.addEventListener('change', function() {
                if (dateFin.value && new Date(dateFin.value) < new Date(this.value)) {
                    dateFin.value = this.value;
                }
                dateFin.min = this.value;
            });
        });
    </script>
</body>
</html>