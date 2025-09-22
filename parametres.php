<?php
/**
 * Page de gestion des paramètres du système
 * Fichier: parametres.php
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

// Traitement des formulaires soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = getDbConnection();
        
        // Traitement du formulaire des unités de mesure
        if (isset($_POST['ajouter_unite'])) {
            $code = strtolower(trim($_POST['code']));
            $libelle = trim($_POST['libelle']);
            $type = $_POST['type'];
            
            // Vérifier si le code existe déjà
            $stmt = $db->prepare("SELECT COUNT(*) FROM unites_mesure WHERE code = ?");
            $stmt->execute([$code]);
            if ($stmt->fetchColumn() > 0) {
                $error = "Le code d'unité '$code' existe déjà.";
            } else {
                $stmt = $db->prepare("INSERT INTO unites_mesure (code, libelle, type) VALUES (?, ?, ?)");
                if ($stmt->execute([$code, $libelle, $type])) {
                    $notification = "L'unité de mesure a été ajoutée avec succès.";
                } else {
                    $error = "Erreur lors de l'ajout de l'unité de mesure.";
                }
            }
        }
        
        // Traitement du formulaire des catégories
        elseif (isset($_POST['ajouter_categorie'])) {
            $nom = trim($_POST['nom']);
            $parent_id = !empty($_POST['parent_id']) ? $_POST['parent_id'] : null;
            $description = trim($_POST['description']);
            
            $stmt = $db->prepare("INSERT INTO categories (nom, parent_id, description) VALUES (?, ?, ?)");
            if ($stmt->execute([$nom, $parent_id, $description])) {
                $notification = "La catégorie a été ajoutée avec succès.";
            } else {
                $error = "Erreur lors de l'ajout de la catégorie.";
            }
        }
        
        // Traitement du formulaire des emplacements
        elseif (isset($_POST['ajouter_emplacement'])) {
            $nom = trim($_POST['nom']);
            $description = trim($_POST['description']);
            
            $stmt = $db->prepare("INSERT INTO emplacements (nom, description, actif) VALUES (?, ?, TRUE)");
            if ($stmt->execute([$nom, $description])) {
                $notification = "L'emplacement a été ajouté avec succès.";
            } else {
                $error = "Erreur lors de l'ajout de l'emplacement.";
            }
        }
        
        // Traitement de la suppression
        elseif (isset($_POST['supprimer'])) {
            $type = $_POST['type'];
            $id = $_POST['id'];
            
            if ($type === 'unite') {
                // Vérifier si l'unité est utilisée
                $stmt = $db->prepare("SELECT COUNT(*) FROM produits WHERE unite_mesure = ?");
                $stmt->execute([$id]);
                if ($stmt->fetchColumn() > 0) {
                    $error = "Cette unité de mesure ne peut pas être supprimée car elle est utilisée par des produits.";
                } else {
                    $stmt = $db->prepare("DELETE FROM unites_mesure WHERE code = ?");
                    if ($stmt->execute([$id])) {
                        $notification = "L'unité de mesure a été supprimée avec succès.";
                    } else {
                        $error = "Erreur lors de la suppression de l'unité de mesure.";
                    }
                }
            } 
            elseif ($type === 'categorie') {
                // Vérifier si la catégorie est utilisée comme parent
                $stmt = $db->prepare("SELECT COUNT(*) FROM categories WHERE parent_id = ?");
                $stmt->execute([$id]);
                if ($stmt->fetchColumn() > 0) {
                    $error = "Cette catégorie ne peut pas être supprimée car elle est utilisée comme catégorie parente.";
                } else {
                    // Vérifier si la catégorie est utilisée par des produits
                    $stmt = $db->prepare("SELECT COUNT(*) FROM produits WHERE categorie_id = ?");
                    $stmt->execute([$id]);
                    if ($stmt->fetchColumn() > 0) {
                        $error = "Cette catégorie ne peut pas être supprimée car elle est utilisée par des produits.";
                    } else {
                        $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
                        if ($stmt->execute([$id])) {
                            $notification = "La catégorie a été supprimée avec succès.";
                        } else {
                            $error = "Erreur lors de la suppression de la catégorie.";
                        }
                    }
                }
            }
            elseif ($type === 'emplacement') {
                // Vérifier si l'emplacement est utilisé
                $stmt = $db->prepare("SELECT COUNT(*) FROM stock_emplacements WHERE emplacement_id = ?");
                $stmt->execute([$id]);
                if ($stmt->fetchColumn() > 0) {
                    $error = "Cet emplacement ne peut pas être supprimé car il contient du stock.";
                } else {
                    $stmt = $db->prepare("DELETE FROM emplacements WHERE id = ?");
                    if ($stmt->execute([$id])) {
                        $notification = "L'emplacement a été supprimé avec succès.";
                    } else {
                        $error = "Erreur lors de la suppression de l'emplacement.";
                    }
                }
            }
        }
        
    } catch (PDOException $e) {
        $error = "Erreur de base de données: " . $e->getMessage();
    }
}

// Récupérer les unités de mesure
try {
    $db = getDbConnection();
    
    // Récupérer toutes les unités de mesure
    $query = "SELECT code, libelle, type FROM unites_mesure ORDER BY type, libelle";
    $stmt = $db->query($query);
    $unites = $stmt->fetchAll();
    
    // Récupérer toutes les catégories
    $query = "SELECT c.id, c.nom, c.description, p.nom as parent_nom
              FROM categories c
              LEFT JOIN categories p ON c.parent_id = p.id
              ORDER BY COALESCE(p.nom, c.nom), c.nom";
    $stmt = $db->query($query);
    $categories = $stmt->fetchAll();
    
    // Récupérer les catégories parentes pour le formulaire
    $query = "SELECT id, nom FROM categories ORDER BY nom";
    $stmt = $db->query($query);
    $categories_parents = $stmt->fetchAll();
    
    // Récupérer tous les emplacements
    $query = "SELECT id, nom, description, actif FROM emplacements ORDER BY nom";
    $stmt = $db->query($query);
    $emplacements = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Erreur de base de données: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres - Azrou Sani Gestion Stock</title>
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
            max-height: 400px;
            overflow-y: auto;
        }
        .accordion-button:not(.collapsed) {
            background-color: #f8f9fa;
            color: #0d6efd;
        }
        .table th {
            position: sticky;
            top: 0;
            background-color: #f8f9fa;
            z-index: 1;
        }
        .badge-unite {
            width: 80px;
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
                        <a class="nav-link" href="utilisateurs.php">
                            <i class="fas fa-user-cog"></i> Utilisateurs
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="parametres.php">
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
                    <h2>Paramètres du système</h2>
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

                <!-- Configuration Accordion -->
                <div class="accordion" id="parametresAccordion">
                    
                    <!-- Unités de mesure -->
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingUnites">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseUnites" aria-expanded="true" aria-controls="collapseUnites">
                                <i class="fas fa-ruler me-2"></i> Unités de mesure
                            </button>
                        </h2>
                        <div id="collapseUnites" class="accordion-collapse collapse show" aria-labelledby="headingUnites" data-bs-parent="#parametresAccordion">
                            <div class="accordion-body">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="card card-dashboard">
                                            <div class="card-header">Ajouter une unité de mesure</div>
                                            <div class="card-body">
                                                <form action="" method="POST">
                                                    <div class="mb-3">
                                                        <label for="code" class="form-label">Code</label>
                                                        <input type="text" class="form-control" id="code" name="code" required maxlength="10" placeholder="Ex: kg, g, m, cm...">
                                                        <div class="form-text">Code court pour l'unité (max 10 caractères)</div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="libelle" class="form-label">Libellé</label>
                                                        <input type="text" class="form-control" id="libelle" name="libelle" required maxlength="30" placeholder="Ex: Kilogramme, Mètre...">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="type" class="form-label">Type</label>
                                                        <select class="form-select" id="type" name="type" required>
                                                            <option value="poids">Poids</option>
                                                            <option value="longueur">Longueur</option>
                                                            <option value="unite">Unité</option>
                                                        </select>
                                                    </div>
                                                    <button type="submit" name="ajouter_unite" class="btn btn-primary">
                                                        <i class="fas fa-plus-circle me-1"></i> Ajouter
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card card-dashboard">
                                            <div class="card-header">Liste des unités de mesure</div>
                                            <div class="card-body">
                                                <div class="table-responsive">
                                                    <table class="table table-striped table-hover">
                                                        <thead>
                                                            <tr>
                                                                <th>Code</th>
                                                                <th>Libellé</th>
                                                                <th>Type</th>
                                                                <th>Actions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php if (empty($unites)): ?>
                                                                <tr>
                                                                    <td colspan="4" class="text-center">Aucune unité de mesure définie</td>
                                                                </tr>
                                                            <?php else: ?>
                                                                <?php foreach ($unites as $unite): ?>
                                                                    <tr>
                                                                        <td><?php echo htmlspecialchars($unite['code']); ?></td>
                                                                        <td><?php echo htmlspecialchars($unite['libelle']); ?></td>
                                                                        <td>
                                                                            <?php if ($unite['type'] === 'poids'): ?>
                                                                                <span class="badge bg-primary badge-unite">Poids</span>
                                                                            <?php elseif ($unite['type'] === 'longueur'): ?>
                                                                                <span class="badge bg-info badge-unite">Longueur</span>
                                                                            <?php else: ?>
                                                                                <span class="badge bg-secondary badge-unite">Unité</span>
                                                                            <?php endif; ?>
                                                                        </td>
                                                                        <td>
                                                                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                                                                    data-bs-toggle="modal" 
                                                                                    data-bs-target="#supprimerModal" 
                                                                                    data-type="unite"
                                                                                    data-id="<?php echo htmlspecialchars($unite['code']); ?>"
                                                                                    data-nom="<?php echo htmlspecialchars($unite['libelle']); ?>">
                                                                                <i class="fas fa-trash-alt"></i>
                                                                            </button>
                                                                        </td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            <?php endif; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Catégories -->
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingCategories">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCategories" aria-expanded="false" aria-controls="collapseCategories">
                                <i class="fas fa-tags me-2"></i> Catégories
                            </button>
                        </h2>
                        <div id="collapseCategories" class="accordion-collapse collapse" aria-labelledby="headingCategories" data-bs-parent="#parametresAccordion">
                            <div class="accordion-body">
                                <div class="row mb-3">
                                    <div class="col-md-5">
                                        <div class="card card-dashboard">
                                            <div class="card-header">Ajouter une catégorie</div>
                                            <div class="card-body">
                                                <form action="" method="POST">
                                                    <div class="mb-3">
                                                        <label for="nom" class="form-label">Nom</label>
                                                        <input type="text" class="form-control" id="nom" name="nom" required maxlength="50" placeholder="Nom de la catégorie">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="parent_id" class="form-label">Catégorie parente (optionnel)</label>
                                                        <select class="form-select" id="parent_id" name="parent_id">
                                                            <option value="">-- Aucune (catégorie principale) --</option>
                                                            <?php foreach ($categories_parents as $cat): ?>
                                                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['nom']); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="description" class="form-label">Description</label>
                                                        <textarea class="form-control" id="description" name="description" rows="3" placeholder="Description de la catégorie"></textarea>
                                                    </div>
                                                    <button type="submit" name="ajouter_categorie" class="btn btn-primary">
                                                        <i class="fas fa-plus-circle me-1"></i> Ajouter
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-7">
                                        <div class="card card-dashboard">
                                            <div class="card-header">Liste des catégories</div>
                                            <div class="card-body">
                                                <div class="table-responsive">
                                                    <table class="table table-striped table-hover">
                                                        <thead>
                                                            <tr>
                                                                <th>Nom</th>
                                                                <th>Catégorie parente</th>
                                                                <th>Description</th>
                                                                <th>Actions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php if (empty($categories)): ?>
                                                                <tr>
                                                                    <td colspan="4" class="text-center">Aucune catégorie définie</td>
                                                                </tr>
                                                            <?php else: ?>
                                                                <?php foreach ($categories as $categorie): ?>
                                                                    <tr>
                                                                        <td><?php echo htmlspecialchars($categorie['nom']); ?></td>
                                                                        <td><?php echo $categorie['parent_nom'] ? htmlspecialchars($categorie['parent_nom']) : '<em>Catégorie principale</em>'; ?></td>
                                                                        <td><?php echo htmlspecialchars($categorie['description'] ?: '-'); ?></td>
                                                                        <td>
                                                                            <a href="categorie_modifier.php?id=<?php echo $categorie['id']; ?>" class="btn btn-sm btn-outline-primary me-1">
                                                                                <i class="fas fa-edit"></i>
                                                                            </a>
                                                                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                                                                    data-bs-toggle="modal" 
                                                                                    data-bs-target="#supprimerModal" 
                                                                                    data-type="categorie"
                                                                                    data-id="<?php echo $categorie['id']; ?>"
                                                                                    data-nom="<?php echo htmlspecialchars($categorie['nom']); ?>">
                                                                                <i class="fas fa-trash-alt"></i>
                                                                            </button>
                                                                        </td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            <?php endif; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Emplacements de stockage -->
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingEmplacements">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseEmplacements" aria-expanded="false" aria-controls="collapseEmplacements">
                                <i class="fas fa-map-marker-alt me-2"></i> Emplacements de stockage
                            </button>
                        </h2>
                        <div id="collapseEmplacements" class="accordion-collapse collapse" aria-labelledby="headingEmplacements" data-bs-parent="#parametresAccordion">
                            <div class="accordion-body">
                                <div class="row mb-3">
                                    <div class="col-md-5">
                                        <div class="card card-dashboard">
                                            <div class="card-header">Ajouter un emplacement</div>
                                            <div class="card-body">
                                                <form action="" method="POST">
                                                    <div class="mb-3">
                                                        <label for="nom_emplacement" class="form-label">Nom</label>
                                                        <input type="text" class="form-control" id="nom_emplacement" name="nom" required maxlength="50" placeholder="Nom de l'emplacement">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="description_emplacement" class="form-label">Description</label>
                                                        <textarea class="form-control" id="description_emplacement" name="description" rows="3" placeholder="Description de l'emplacement"></textarea>
                                                    </div>
                                                    <button type="submit" name="ajouter_emplacement" class="btn btn-primary">
                                                        <i class="fas fa-plus-circle me-1"></i> Ajouter
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-7">
                                        <div class="card card-dashboard">
                                            <div class="card-header">Liste des emplacements</div>
                                            <div class="card-body">
                                                <div class="table-responsive">
                                                    <table class="table table-striped table-hover">
                                                        <thead>
                                                            <tr>
                                                                <th>Nom</th>
                                                                <th>Description</th>
                                                                <th>Statut</th>
                                                                <th>Actions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php if (empty($emplacements)): ?>
                                                                <tr>
                                                                    <td colspan="4" class="text-center">Aucun emplacement défini</td>
                                                                </tr>
                                                            <?php else: ?>
                                                                <?php foreach ($emplacements as $emplacement): ?>
                                                                    <tr>
                                                                        <td><?php echo htmlspecialchars($emplacement['nom']); ?></td>
                                                                        <td><?php echo htmlspecialchars($emplacement['description'] ?: '-'); ?></td>
                                                                        <td>
                                                                            <?php if ($emplacement['actif']): ?>
                                                                                <span class="badge bg-success">Actif</span>
                                                                            <?php else: ?>
                                                                                <span class="badge bg-secondary">Inactif</span>
                                                                            <?php endif; ?>
                                                                        </td>
                                                                        <td>
                                                                            <a href="emplacement_modifier.php?id=<?php echo $emplacement['id']; ?>" class="btn btn-sm btn-outline-primary me-1">
                                                                                <i class="fas fa-edit"></i>
                                                                            </a>
                                                                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                                                                    data-bs-toggle="modal" 
                                                                                    data-bs-target="#supprimerModal" 
                                                                                    data-type="emplacement"
                                                                                    data-id="<?php echo $emplacement['id']; ?>"
                                                                                    data-nom="<?php echo htmlspecialchars($emplacement['nom']); ?>">
                                                                                <i class="fas fa-trash-alt"></i>
                                                                            </button>
                                                                        </td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            <?php endif; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Paramètres du système -->
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingSysteme">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSysteme" aria-expanded="false" aria-controls="collapseSysteme">
                                <i class="fas fa-cog me-2"></i> Paramètres du système
                            </button>
                        </h2>
                        <div id="collapseSysteme" class="accordion-collapse collapse" aria-labelledby="headingSysteme" data-bs-parent="#parametresAccordion">
                            <div class="accordion-body">
                                <div class="card card-dashboard">
                                    <div class="card-header">Configuration générale</div>
                                    <div class="card-body">
                                        <form action="" method="POST">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="entreprise_nom" class="form-label">Nom de l'entreprise</label>
                                                        <input type="text" class="form-control" id="entreprise_nom" name="entreprise_nom" value="Azrou Sani" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="entreprise_adresse" class="form-label">Adresse</label>
                                                        <textarea class="form-control" id="entreprise_adresse" name="entreprise_adresse" rows="3">Z.I., Azrou, Maroc</textarea>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="entreprise_telephone" class="form-label">Téléphone</label>
                                                        <input type="text" class="form-control" id="entreprise_telephone" name="entreprise_telephone" value="+212 5XX-XXXXXX">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="tva_defaut" class="form-label">Taux de TVA par défaut (%)</label>
                                                        <input type="number" class="form-control" id="tva_defaut" name="tva_defaut" min="0" max="100" step="0.1" value="20">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="devise" class="form-label">Devise</label>
                                                        <input type="text" class="form-control" id="devise" name="devise" value="MAD">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="format_date" class="form-label">Format de date</label>
                                                        <select class="form-select" id="format_date" name="format_date">
                                                            <option value="d/m/Y" selected>JJ/MM/AAAA</option>
                                                            <option value="Y-m-d">AAAA-MM-JJ</option>
                                                            <option value="m/d/Y">MM/JJ/AAAA</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                            <button type="submit" name="enregistrer_parametres" class="btn btn-primary">
                                                <i class="fas fa-save me-1"></i> Enregistrer les paramètres
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                
                                <div class="card card-dashboard mt-3">
                                    <div class="card-header">Sauvegarde et restauration</div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h5>Sauvegarde de la base de données</h5>
                                                <p>Vous pouvez créer une sauvegarde complète de la base de données pour une restauration ultérieure.</p>
                                                <a href="backup.php" class="btn btn-success">
                                                    <i class="fas fa-download me-1"></i> Créer une sauvegarde
                                                </a>
                                            </div>
                                            <div class="col-md-6">
                                                <h5>Restauration de la base de données</h5>
                                                <p class="text-danger">Attention: La restauration écrasera toutes les données actuelles!</p>
                                                <form action="restore.php" method="POST" enctype="multipart/form-data">
                                                    <div class="mb-3">
                                                        <label for="backup_file" class="form-label">Fichier de sauvegarde</label>
                                                        <input type="file" class="form-control" id="backup_file" name="backup_file" accept=".sql">
                                                    </div>
                                                    <button type="submit" name="restore" class="btn btn-warning">
                                                        <i class="fas fa-upload me-1"></i> Restaurer
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
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
                    <h5 class="modal-title" id="supprimerModalLabel">Confirmer la suppression</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Êtes-vous sûr de vouloir supprimer <span id="element-type"></span> <strong id="element-nom"></strong> ?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <form action="" method="POST">
                        <input type="hidden" name="type" id="delete-type">
                        <input type="hidden" name="id" id="delete-id">
                        <button type="submit" name="supprimer" class="btn btn-danger">Supprimer</button>
                    </form>
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
                const type = button.getAttribute('data-type');
                const id = button.getAttribute('data-id');
                const nom = button.getAttribute('data-nom');
                
                let elementType = '';
                if (type === 'unite') {
                    elementType = "l'unité de mesure";
                } else if (type === 'categorie') {
                    elementType = "la catégorie";
                } else if (type === 'emplacement') {
                    elementType = "l'emplacement";
                }
                
                document.getElementById('element-type').textContent = elementType;
                document.getElementById('element-nom').textContent = nom;
                document.getElementById('delete-type').value = type;
                document.getElementById('delete-id').value = id;
            });
        });
    </script>
</body>
</html>