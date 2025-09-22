<?php
/**
 * Page d'ajout de commande
 * Fichier: commandes_ajouter.php
 */

// Démarrer la session
session_start();

// Vérifier si l'utilisateur est connecté et a le rôle approprié
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['administrateur', 'commercial'])) {
    header("Location: login.php");
    exit;
}

// Inclure la configuration de la base de données
require_once 'config/db.php';

// Initialiser les variables
$error_message = '';
$success_message = '';
$clients = [];
$produits = [];

// Récupérer la liste des clients
try {
    $db = getDbConnection();
    $query = "SELECT id, nom, type FROM clients ORDER BY nom ASC";
    $stmt = $db->query($query);
    $clients = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Erreur lors de la récupération des clients: " . $e->getMessage();
}

// Récupérer la liste des produits actifs
try {
    $query = "SELECT id, reference, nom, stock_actuel, prix_vente_ht FROM produits WHERE actif = TRUE ORDER BY nom ASC";
    $stmt = $db->query($query);
    $produits = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Erreur lors de la récupération des produits: " . $e->getMessage();
}

// Traitement du formulaire d'ajout de commande
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Valider les données
    $client_id = filter_input(INPUT_POST, 'client_id', FILTER_VALIDATE_INT);
    $date_livraison = $_POST['date_livraison'] ?? '';
    $remise = filter_input(INPUT_POST, 'remise', FILTER_VALIDATE_FLOAT);
    $notes = $_POST['notes'] ?? '';
    $produits_commandes = $_POST['produits'] ?? [];

    // Validation des données
    if (!$client_id) {
        $error_message = "Veuillez sélectionner un client valide.";
    } elseif (empty($produits_commandes)) {
        $error_message = "Veuillez ajouter au moins un produit à la commande.";
    } else {
        try {
            $db->beginTransaction();

            // Générer une référence de commande
            $reference = 'CMD-' . date('Ymd-His');

            // Insérer la commande principale
            $query = "INSERT INTO commandes (
                reference, client_id, utilisateur_id, 
                date_livraison_prevue, remise, notes
            ) VALUES (?, ?, ?, ?, ?, ?)";
            
            $stmt = $db->prepare($query);
            $stmt->execute([
                $reference,
                $client_id,
                $_SESSION['user_id'],
                $date_livraison ?: null,
                $remise ?: 0,
                $notes
            ]);
            
            $commande_id = $db->lastInsertId();
            $montant_total = 0;

            // Insérer les produits de la commande
            foreach ($produits_commandes as $produit) {
                $produit_id = filter_var($produit['id'], FILTER_VALIDATE_INT);
                $quantite = filter_var($produit['quantite'], FILTER_VALIDATE_FLOAT);
                $prix = filter_var($produit['prix'], FILTER_VALIDATE_FLOAT);
                
                if ($produit_id && $quantite > 0 && $prix > 0) {
                    $query = "INSERT INTO details_commandes (
                        commande_id, produit_id, quantite, prix_unitaire_ht
                    ) VALUES (?, ?, ?, ?)";
                    
                    $stmt = $db->prepare($query);
                    $stmt->execute([$commande_id, $produit_id, $quantite, $prix]);
                    
                    $montant_total += ($quantite * $prix);
                    
                    // Mettre à jour le stock (sera géré par le trigger)
                }
            }

            // Appliquer la remise
            $montant_total = $montant_total * (1 - ($remise / 100));

            // Mettre à jour le montant total de la commande
            $query = "UPDATE commandes SET montant_total_ht = ? WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$montant_total, $commande_id]);

            $db->commit();
            
            $success_message = "Commande #$reference ajoutée avec succès!";
            
            // Rediriger vers la page de détail de la commande
            header("Location: commande_details.php?id=$commande_id");
            exit;
            
        } catch (PDOException $e) {
            $db->rollBack();
            $error_message = "Erreur lors de l'ajout de la commande: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouvelle Commande - Azrou Sani Gestion Stock</title>
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
            max-height: 400px;
            overflow-y: auto;
        }
        .produit-row {
            transition: all 0.3s;
        }
        .produit-row:hover {
            background-color: #f1f1f1;
        }
        #produits-commande tbody tr {
            cursor: pointer;
        }
        #produits-commande tbody tr:hover {
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <!--  -->

            <!-- Main Content -->
            <div class="col-md-10 content">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-plus-circle me-2"></i> Nouvelle Commande</h2>
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

                <!-- Formulaire de commande -->
                <div class="card">
                    <div class="card-header">
                        Détails de la commande
                    </div>
                    <div class="card-body">
                        <form id="commande-form" method="POST">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="client_id" class="form-label">Client *</label>
                                    <select class="form-select" id="client_id" name="client_id" required>
                                        <option value="">Sélectionnez un client...</option>
                                        <?php foreach ($clients as $client): ?>
                                            <option value="<?php echo $client['id']; ?>">
                                                <?php echo htmlspecialchars($client['nom']); ?>
                                                (<?php echo $client['type'] === 'entreprise' ? 'Entreprise' : 'Particulier'; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="date_livraison" class="form-label">Date de livraison prévue</label>
                                    <input type="date" class="form-control" id="date_livraison" name="date_livraison" 
                                           min="<?php echo date('Y-m-d'); ?>">
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="remise" class="form-label">Remise (%)</label>
                                    <input type="number" class="form-control" id="remise" name="remise" 
                                           min="0" max="100" step="0.01" value="0">
                                </div>
                                <div class="col-md-8">
                                    <label for="notes" class="form-label">Notes</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                                </div>
                            </div>

                            <hr>

                            <h5 class="mb-3">Produits commandés</h5>
                            
                            <!-- Liste des produits à ajouter -->
                            <div class="row mb-4">
                                <div class="col-md-8">
                                    <label for="produit_id" class="form-label">Ajouter un produit</label>
                                    <select class="form-select" id="produit_id">
                                        <option value="">Sélectionnez un produit...</option>
                                        <?php foreach ($produits as $produit): ?>
                                            <option value="<?php echo $produit['id']; ?>" 
                                                    data-prix="<?php echo $produit['prix_vente_ht']; ?>"
                                                    data-stock="<?php echo $produit['stock_actuel']; ?>">
                                                <?php echo htmlspecialchars($produit['reference']); ?> - 
                                                <?php echo htmlspecialchars($produit['nom']); ?>
                                                (Stock: <?php echo $produit['stock_actuel']; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="quantite" class="form-label">Quantité</label>
                                    <input type="number" class="form-control" id="quantite" min="0.001" step="0.001" value="1">
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="button" class="btn btn-primary w-100" id="ajouter-produit">
                                        <i class="fas fa-plus me-1"></i> Ajouter
                                    </button>
                                </div>
                            </div>

                            <!-- Tableau des produits commandés -->
                            <div class="table-responsive">
                                <table class="table table-bordered" id="produits-commande">
                                    <thead class="table-light">
                                        <tr>
                                            <th width="15%">Référence</th>
                                            <th width="30%">Produit</th>
                                            <th width="15%">Prix unitaire (HT)</th>
                                            <th width="15%">Quantité</th>
                                            <th width="15%">Total (HT)</th>
                                            <th width="10%">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Les produits seront ajoutés ici dynamiquement -->
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="4" class="text-end fw-bold">Total HT:</td>
                                            <td id="total-ht">0.00</td>
                                            <td></td>
                                        </tr>
                                        <tr>
                                            <td colspan="4" class="text-end fw-bold">Remise:</td>
                                            <td id="montant-remise">0.00</td>
                                            <td></td>
                                        </tr>
                                        <tr>
                                            <td colspan="4" class="text-end fw-bold">Total après remise:</td>
                                            <td id="total-apres-remise">0.00</td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <!-- Champ caché pour les produits -->
                            <input type="hidden" name="produits" id="produits-hidden">

                            <div class="d-flex justify-content-end mt-4">
                                <a href="commandes.php" class="btn btn-outline-secondary me-2">
                                    <i class="fas fa-times me-1"></i> Annuler
                                </a>
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-check me-1"></i> Enregistrer la commande
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const produitSelect = document.getElementById('produit_id');
            const quantiteInput = document.getElementById('quantite');
            const ajouterBtn = document.getElementById('ajouter-produit');
            const produitsTable = document.getElementById('produits-commande').getElementsByTagName('tbody')[0];
            const totalHtCell = document.getElementById('total-ht');
            const montantRemiseCell = document.getElementById('montant-remise');
            const totalApresRemiseCell = document.getElementById('total-apres-remise');
            const remiseInput = document.getElementById('remise');
            const produitsHidden = document.getElementById('produits-hidden');
            const commandeForm = document.getElementById('commande-form');

            let produitsCommande = [];
            let totalHt = 0;

            // Ajouter un produit à la commande
            ajouterBtn.addEventListener('click', function() {
                const selectedOption = produitSelect.options[produitSelect.selectedIndex];
                const produitId = produitSelect.value;
                const quantite = parseFloat(quantiteInput.value);
                
                if (!produitId) {
                    alert('Veuillez sélectionner un produit');
                    return;
                }
                
                if (isNaN(quantite) || quantite <= 0) {
                    alert('Veuillez entrer une quantité valide');
                    return;
                }
                
                const reference = selectedOption.text.split(' - ')[0];
                const nom = selectedOption.text.split(' - ')[1].split(' (Stock:')[0];
                const prix = parseFloat(selectedOption.dataset.prix);
                const stock = parseFloat(selectedOption.dataset.stock);
                
                // Vérifier si le produit est déjà dans la commande
                const index = produitsCommande.findIndex(p => p.id == produitId);
                
                if (index !== -1) {
                    // Mettre à jour la quantité si le produit existe déjà
                    produitsCommande[index].quantite += quantite;
                } else {
                    // Ajouter un nouveau produit
                    produitsCommande.push({
                        id: produitId,
                        reference: reference,
                        nom: nom,
                        prix: prix,
                        quantite: quantite
                    });
                }
                
                // Mettre à jour l'affichage
                updateProduitsTable();
                updateTotal();
                
                // Réinitialiser les champs
                produitSelect.value = '';
                quantiteInput.value = '1';
            });
            
            // Mettre à jour le tableau des produits
            function updateProduitsTable() {
                produitsTable.innerHTML = '';
                
                produitsCommande.forEach((produit, index) => {
                    const row = produitsTable.insertRow();
                    row.className = 'produit-row';
                    
                    const total = produit.prix * produit.quantite;
                    
                    row.innerHTML = `
                        <td>${produit.reference}</td>
                        <td>${produit.nom}</td>
                        <td>${produit.prix.toFixed(2)}</td>
                        <td>${produit.quantite}</td>
                        <td>${total.toFixed(2)}</td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-danger supprimer-produit" data-index="${index}">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </td>
                    `;
                });
                
                // Ajouter les écouteurs d'événements pour les boutons de suppression
                document.querySelectorAll('.supprimer-produit').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const index = parseInt(this.dataset.index);
                        produitsCommande.splice(index, 1);
                        updateProduitsTable();
                        updateTotal();
                    });
                });
                
                // Mettre à jour le champ caché
                produitsHidden.value = JSON.stringify(produitsCommande);
            }
            
            // Mettre à jour les totaux
            function updateTotal() {
                totalHt = produitsCommande.reduce((sum, produit) => sum + (produit.prix * produit.quantite), 0);
                totalHtCell.textContent = totalHt.toFixed(2);
                
                const remise = parseFloat(remiseInput.value) || 0;
                const montantRemise = totalHt * (remise / 100);
                const totalApresRemise = totalHt - montantRemise;
                
                montantRemiseCell.textContent = montantRemise.toFixed(2);
                totalApresRemiseCell.textContent = totalApresRemise.toFixed(2);
            }
            
            // Mettre à jour les totaux lorsque la remise change
            remiseInput.addEventListener('input', updateTotal);
            
            // Valider le formulaire avant soumission
            commandeForm.addEventListener('submit', function(e) {
                if (produitsCommande.length === 0) {
                    e.preventDefault();
                    alert('Veuillez ajouter au moins un produit à la commande');
                }
            });
        });
    </script>
</body>
</html>      