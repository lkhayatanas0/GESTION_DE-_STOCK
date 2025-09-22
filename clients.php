<?php
/**
 * Page de gestion des clients
 * Fichier: clients.php
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

// Vérifier les permissions (seuls les administrateurs et commerciaux peuvent accéder)
if ($user_role !== 'administrateur' && $user_role !== 'commercial') {
    header("Location: index.php");
    exit;
}

// Traitement du formulaire de recherche
$search_query = '';
if (isset($_GET['search'])) {
    $search_query = trim($_GET['search']);
}

// Traitement du filtre par type
$filter_type = '';
if (isset($_GET['type']) && in_array($_GET['type'], ['particulier', 'entreprise'])) {
    $filter_type = $_GET['type'];
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Récupérer la liste des clients
try {
    $db = getDbConnection();
    
    // Préparer la requête de base
    $query = "SELECT * FROM clients WHERE 1=1";
    $params = [];
    
    // Ajouter les conditions de recherche
    if (!empty($search_query)) {
        $query .= " AND (nom LIKE ? OR contact_principal LIKE ? OR telephone LIKE ? OR email LIKE ?)";
        $search_param = "%$search_query%";
        $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    }
    
    // Ajouter le filtre par type
    if (!empty($filter_type)) {
        $query .= " AND type = ?";
        $params[] = $filter_type;
    }
    
    // Compter le nombre total de clients
    $count_query = str_replace('SELECT *', 'SELECT COUNT(*) as total', $query);
    $stmt = $db->prepare($count_query);
    $stmt->execute($params);
    $total_clients = $stmt->fetch()['total'];
    $total_pages = ceil($total_clients / $per_page);
    
    // Ajouter le tri et la pagination
    $query .= " ORDER BY nom ASC LIMIT ? OFFSET ?";
    $params[] = $per_page;
    $params[] = $offset;
    
    // Exécuter la requête principale
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $clients = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error_message = "Erreur de base de données: " . $e->getMessage();
}

// Traitement de la suppression d'un client
if (isset($_POST['delete_client']) && $user_role === 'administrateur') {
    $client_id = (int)$_POST['client_id'];
    
    try {
        // Vérifier d'abord si le client a des commandes
        $db = getDbConnection();
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM commandes WHERE client_id = ?");
        $stmt->execute([$client_id]);
        $has_orders = $stmt->fetch()['total'] > 0;
        
        if ($has_orders) {
            $error_message = "Impossible de supprimer ce client car il a des commandes associées.";
        } else {
            // Supprimer le client
            $stmt = $db->prepare("DELETE FROM clients WHERE id = ?");
            $stmt->execute([$client_id]);
            
            if ($stmt->rowCount() > 0) {
                $success_message = "Client supprimé avec succès.";
                // Recharger la page pour actualiser la liste
                header("Location: clients.php?deleted=1");
                exit;
            } else {
                $error_message = "Aucun client trouvé avec cet ID.";
            }
        }
    } catch (PDOException $e) {
        $error_message = "Erreur lors de la suppression du client: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Clients - Azrou Sani Gestion Stock</title>
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
        .badge-particulier {
            background-color: #6c757d;
        }
        .badge-entreprise {
            background-color: #0d6efd;
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
                        <a class="nav-link active" href="clients.php">
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
                    <h2>Gestion des Clients</h2>
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
                        Client supprimé avec succès.
                    </div>
                <?php endif; ?>

                <!-- Card principale -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-users me-2"></i>
                            Liste des Clients
                        </div>
                        <div>
                            <a href="client_ajouter.php" class="btn btn-sm btn-success">
                                <i class="fas fa-plus me-1"></i> Nouveau Client
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Barre de recherche et filtres -->
                        <form method="get" class="mb-4">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <input type="text" name="search" class="form-control" placeholder="Rechercher un client..." value="<?php echo htmlspecialchars($search_query); ?>">
                                        <button class="btn btn-outline-secondary" type="submit">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <select name="type" class="form-select" onchange="this.form.submit()">
                                        <option value="">Tous les types</option>
                                        <option value="particulier" <?php echo $filter_type === 'particulier' ? 'selected' : ''; ?>>Particuliers</option>
                                        <option value="entreprise" <?php echo $filter_type === 'entreprise' ? 'selected' : ''; ?>>Entreprises</option>
                                    </select>
                                </div>
                                <div class="col-md-3 text-end">
                                    <?php if (!empty($search_query) || !empty($filter_type)): ?>
                                        <a href="clients.php" class="btn btn-outline-danger">
                                            <i class="fas fa-times"></i> Réinitialiser
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>

                        <!-- Tableau des clients -->
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Nom</th>
                                        <th>Contact</th>
                                        <th>Téléphone</th>
                                        <th>Email</th>
                                        <th>Ville</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($clients)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center">Aucun client trouvé</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($clients as $client): ?>
                                            <tr>
                                                <td>
                                                    <?php if ($client['type'] === 'particulier'): ?>
                                                        <span class="badge badge-particulier">Particulier</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-entreprise">Entreprise</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($client['nom']); ?></td>
                                                <td><?php echo htmlspecialchars($client['contact_principal'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($client['telephone'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($client['email'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($client['ville'] ?? 'N/A'); ?></td>
                                                <td class="action-buttons">
                                                    <a href="client_details.php?id=<?php echo $client['id']; ?>" class="btn btn-sm btn-outline-primary" title="Voir détails">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="client_modifier.php?id=<?php echo $client['id']; ?>" class="btn btn-sm btn-outline-warning" title="Modifier">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <?php if ($user_role === 'administrateur'): ?>
                                                        <button class="btn btn-sm btn-outline-danger delete-btn" 
                                                                data-client-id="<?php echo $client['id']; ?>" 
                                                                data-client-name="<?php echo htmlspecialchars($client['nom']); ?>"
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
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search_query) ? '&search='.urlencode($search_query) : ''; ?><?php echo !empty($filter_type) ? '&type='.$filter_type : ''; ?>" aria-label="Previous">
                                                <span aria-hidden="true">&laquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search_query) ? '&search='.urlencode($search_query) : ''; ?><?php echo !empty($filter_type) ? '&type='.$filter_type : ''; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search_query) ? '&search='.urlencode($search_query) : ''; ?><?php echo !empty($filter_type) ? '&type='.$filter_type : ''; ?>" aria-label="Next">
                                                <span aria-hidden="true">&raquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer text-muted">
                        Total: <?php echo $total_clients; ?> client(s) trouvé(s)
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
                        <p>Êtes-vous sûr de vouloir supprimer le client <strong id="client-name-to-delete"></strong> ?</p>
                        <p class="text-danger">Cette action est irréversible !</p>
                        <input type="hidden" name="client_id" id="client-id-to-delete">
                        <input type="hidden" name="delete_client" value="1">
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
                    const clientId = this.getAttribute('data-client-id');
                    const clientName = this.getAttribute('data-client-name');
                    
                    document.getElementById('client-id-to-delete').value = clientId;
                    document.getElementById('client-name-to-delete').textContent = clientName;
                    
                    deleteModal.show();
                });
            });
        });
    </script>
</body>
</html>