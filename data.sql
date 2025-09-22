-- Création de la base de données
CREATE DATABASE azrou_sani_gestion_stock;
USE azrou_sani_gestion_stock;

-- Table des utilisateurs
CREATE TABLE utilisateurs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom_complet VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    mot_de_passe VARCHAR(255) NOT NULL,
    role ENUM('administrateur', 'magasinier', 'commercial') NOT NULL,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    dernier_acces DATETIME,
    actif BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB;

-- Table des catégories de produits (hiérarchique)
CREATE TABLE categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(50) NOT NULL,
    parent_id INT NULL,
    description TEXT,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Table des unités de mesure
CREATE TABLE unites_mesure (
    code VARCHAR(10) PRIMARY KEY,
    libelle VARCHAR(30) NOT NULL,
    type ENUM('poids', 'longueur', 'unite') NOT NULL
) ENGINE=InnoDB;

-- Table des produits
CREATE TABLE produits (
    id INT PRIMARY KEY AUTO_INCREMENT,
    reference VARCHAR(20) UNIQUE NOT NULL,
    nom VARCHAR(100) NOT NULL,
    categorie_id INT NOT NULL,
    unite_mesure VARCHAR(10) NOT NULL,
    poids_metrique DECIMAL(10,3) COMMENT 'Pour conversion kg/mètre',
    stock_actuel DECIMAL(12,3) DEFAULT 0,
    stock_minimal DECIMAL(12,3) DEFAULT 0,
    prix_achat_moyen DECIMAL(12,2),
    prix_vente_ht DECIMAL(12,2) NOT NULL,
    description TEXT,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    actif BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (categorie_id) REFERENCES categories(id),
    FOREIGN KEY (unite_mesure) REFERENCES unites_mesure(code)
) ENGINE=InnoDB;

-- Table des fournisseurs
CREATE TABLE fournisseurs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    raison_sociale VARCHAR(100) NOT NULL,
    contact_principal VARCHAR(100),
    telephone VARCHAR(20),
    email VARCHAR(100),
    adresse TEXT,
    ville VARCHAR(50),
    pays VARCHAR(50) DEFAULT 'Maroc',
    notes TEXT,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Table des clients
CREATE TABLE clients (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type ENUM('particulier', 'entreprise') NOT NULL,
    nom VARCHAR(100) NOT NULL,
    contact_principal VARCHAR(100),
    telephone VARCHAR(20),
    email VARCHAR(100),
    adresse TEXT,
    ville VARCHAR(50),
    pays VARCHAR(50) DEFAULT 'Maroc',
    notes TEXT,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Table des commandes (ventes)
CREATE TABLE commandes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    reference VARCHAR(20) UNIQUE NOT NULL,
    client_id INT NOT NULL,
    utilisateur_id INT NOT NULL,
    date_commande DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_livraison_prevue DATE,
    statut ENUM('brouillon', 'confirmee', 'preparation', 'livree', 'annulee') DEFAULT 'brouillon',
    montant_total_ht DECIMAL(12,2) DEFAULT 0,
    remise DECIMAL(5,2) DEFAULT 0,
    tva DECIMAL(5,2) DEFAULT 20,
    notes TEXT,
    FOREIGN KEY (client_id) REFERENCES clients(id),
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id)
) ENGINE=InnoDB;

-- Détails des commandes
CREATE TABLE details_commandes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    commande_id INT NOT NULL,
    produit_id INT NOT NULL,
    quantite DECIMAL(12,3) NOT NULL,
    prix_unitaire_ht DECIMAL(12,2) NOT NULL,
    remise DECIMAL(5,2) DEFAULT 0,
    tva DECIMAL(5,2) DEFAULT 20,
    FOREIGN KEY (commande_id) REFERENCES commandes(id) ON DELETE CASCADE,
    FOREIGN KEY (produit_id) REFERENCES produits(id)
) ENGINE=InnoDB;

-- Table des achats (fournisseurs)
CREATE TABLE achats (
    id INT PRIMARY KEY AUTO_INCREMENT,
    reference VARCHAR(20) UNIQUE NOT NULL,
    fournisseur_id INT NOT NULL,
    utilisateur_id INT NOT NULL,
    date_achat DATE NOT NULL,
    date_reception DATE,
    statut ENUM('attente', 'partiel', 'recu', 'annule') DEFAULT 'attente',
    montant_total_ht DECIMAL(12,2) DEFAULT 0,
    tva DECIMAL(5,2) DEFAULT 20,
    notes TEXT,
    FOREIGN KEY (fournisseur_id) REFERENCES fournisseurs(id),
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id)
) ENGINE=InnoDB;

-- Détails des achats
CREATE TABLE details_achats (
    id INT PRIMARY KEY AUTO_INCREMENT,
    achat_id INT NOT NULL,
    produit_id INT NOT NULL,
    quantite DECIMAL(12,3) NOT NULL,
    prix_unitaire_ht DECIMAL(12,2) NOT NULL,
    tva DECIMAL(5,2) DEFAULT 20,
    date_peremption DATE,
    numero_lot VARCHAR(50),
    FOREIGN KEY (achat_id) REFERENCES achats(id) ON DELETE CASCADE,
    FOREIGN KEY (produit_id) REFERENCES produits(id)
) ENGINE=InnoDB;

-- Mouvements de stock
CREATE TABLE mouvements_stock (
    id INT PRIMARY KEY AUTO_INCREMENT,
    produit_id INT NOT NULL,
    type ENUM('entree', 'sortie', 'inventaire', 'ajustement') NOT NULL,
    quantite DECIMAL(12,3) NOT NULL,
    date_mouvement DATETIME DEFAULT CURRENT_TIMESTAMP,
    utilisateur_id INT NOT NULL,
    document_type ENUM('achat', 'commande', 'inventaire', 'autre') NOT NULL,
    document_id INT,
    notes TEXT,
    FOREIGN KEY (produit_id) REFERENCES produits(id),
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id)
) ENGINE=InnoDB;

-- Table des emplacements de stockage
CREATE TABLE emplacements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(50) NOT NULL,
    description TEXT,
    actif BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB;

-- Stock par emplacement
CREATE TABLE stock_emplacements (
    produit_id INT NOT NULL,
    emplacement_id INT NOT NULL,
    quantite DECIMAL(12,3) DEFAULT 0,
    PRIMARY KEY (produit_id, emplacement_id),
    FOREIGN KEY (produit_id) REFERENCES produits(id) ON DELETE CASCADE,
    FOREIGN KEY (emplacement_id) REFERENCES emplacements(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Table des historiques de prix
CREATE TABLE historiques_prix (
    id INT PRIMARY KEY AUTO_INCREMENT,
    produit_id INT NOT NULL,
    type ENUM('achat', 'vente') NOT NULL,
    ancien_prix DECIMAL(12,2) NOT NULL,
    nouveau_prix DECIMAL(12,2) NOT NULL,
    date_changement DATETIME DEFAULT CURRENT_TIMESTAMP,
    utilisateur_id INT,
    notes TEXT,
    FOREIGN KEY (produit_id) REFERENCES produits(id),
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id)
) ENGINE=InnoDB;

-- Insertion des données de base
INSERT INTO unites_mesure (code, libelle, type) VALUES
('kg', 'Kilogramme', 'poids'),
('g', 'Gramme', 'poids'),
('m', 'Mètre', 'longueur'),
('un', 'Unité', 'unite'),
('r', 'Rouleau', 'unite');

INSERT INTO categories (nom, parent_id, description) VALUES
('Fil Toron', NULL, 'Fils en acier torsadés'),
('Accessoires', NULL, 'Accessoires pour fils et câbles'),
('Sangles', NULL, 'Sangles de levage'),
('Câbles', NULL, 'Câbles métalliques'),
('Chaînes', NULL, 'Chaînes de levage');

-- Création des triggers pour la gestion automatique du stock
DELIMITER //
CREATE TRIGGER after_detail_achat_insert
AFTER INSERT ON details_achats
FOR EACH ROW
BEGIN
    -- Mise à jour du stock du produit
    UPDATE produits SET stock_actuel = stock_actuel + NEW.quantite WHERE id = NEW.produit_id;
    
    -- Enregistrement du mouvement de stock
    INSERT INTO mouvements_stock (produit_id, type, quantite, utilisateur_id, document_type, document_id)
    VALUES (NEW.produit_id, 'entree', NEW.quantite, NEW.utilisateur_id, 'achat', NEW.achat_id);
END//

CREATE TRIGGER after_detail_commande_insert
AFTER INSERT ON details_commandes
FOR EACH ROW
BEGIN
    -- Mise à jour du stock du produit
    UPDATE produits SET stock_actuel = stock_actuel - NEW.quantite WHERE id = NEW.produit_id;
    
    -- Enregistrement du mouvement de stock (à adapter avec l'ID utilisateur réel)
    INSERT INTO mouvements_stock (produit_id, type, quantite, utilisateur_id, document_type, document_id)
    VALUES (NEW.produit_id, 'sortie', NEW.quantite, 1, 'commande', NEW.commande_id);
END//
DELIMITER ;
-- Script pour insérer un utilisateur administrateur dans la base de données
-- L'utilisateur aura l'email admin@azrou-sani.ma et le mot de passe "admin123"
-- Le mot de passe est haché pour la sécurité

-- S'assurer que la base de données est sélectionnée
USE azrou_sani_gestion_stock;

-- Insérer l'utilisateur administrateur
INSERT INTO utilisateurs (
    nom_complet, 
    email, 
    mot_de_passe,  -- Le mot de passe "admin123" haché avec password_hash()
    role, 
    date_creation, 
    actif
) VALUES (
    'Administrateur Système',
    'admin@azrou-sani.ma',
    'admin123',  -- Mot de passe "admin123" haché
    'administrateur',
    NOW(),
    TRUE
);

-- Vous pouvez également ajouter d'autres types d'utilisateurs pour tester différents rôles

-- Ajouter un utilisateur avec le rôle magasinier
INSERT INTO utilisateurs (
    nom_complet, 
    email, 
    mot_de_passe,  -- Le mot de passe "magasinier123" haché 
    role, 
    date_creation, 
    actif
) VALUES (
    'Omar Magasinier',
    'magasinier@azrou-sani.ma',
    'magasinier123',  -- Mot de passe "magasinier123" haché
    'magasinier',
    NOW(),
    TRUE
);

-- Ajouter un utilisateur avec le rôle commercial
INSERT INTO utilisateurs (
    nom_complet, 
    email, 
    mot_de_passe,  -- Le mot de passe "commercial123" haché
    role, 
    date_creation, 
    actif
) VALUES (
    'Fatima Commerciale',
    'commercial@azrou-sani.ma',
    'commercial123',  -- Mot de passe "commercial123" haché
    'commercial',
    NOW(),
    TRUE
);

-- Insertion des sous-catégories
INSERT INTO categories (nom, parent_id, description) VALUES
('Fil Toron Acier', 1, 'Fils en acier standard'),
('Fil Toron Inox', 1, 'Fils en acier inoxydable'),
('Mousquetons', 2, 'Mousquetons pour levage'),
('Manilles', 2, 'Manilles de différentes tailles'),
('Sangles Plate', 3, 'Sangles plates en polyester'),
('Sangles Ronde', 3, 'Sangles rondes pour levage');

-- Insertion des fournisseurs
INSERT INTO fournisseurs (raison_sociale, contact_principal, telephone, email, adresse, ville, pays, notes) VALUES
('Aciérie Marocaine', 'Mohamed Benali', '0522445566', 'contact@acierie.ma', 'Zone Industrielle, Casablanca', 'Casablanca', 'Maroc', 'Fournisseur principal pour acier'),
('Inox Import', 'Karim Ziri', '0533667788', 'info@inoximport.ma', 'Route de Rabat, km 5.5', 'Rabat', 'Maroc', 'Spécialiste inoxydable'),
('Métaux du Nord', 'Fatima Zahra', '0539889900', 'ventes@metauxnord.ma', 'Zone Industrielle, Tanger', 'Tanger', 'Maroc', 'Bon rapport qualité/prix'),
('Accessoires Levage SARL', 'Hassan El Mansouri', '0522334455', 'contact@levageaccess.ma', 'Avenue Hassan II, 123', 'Fès', 'Maroc', 'Livraison rapide');

-- Insertion des clients
INSERT INTO clients (type, nom, contact_principal, telephone, email, adresse, ville, pays, notes) VALUES
('entreprise', 'BTP Atlas', 'Ahmed Karim', '0522778899', 'achat@btpatlas.ma', 'Route de Meknès, km 12', 'Fès', 'Maroc', 'Client régulier - paiement 30 jours'),
('entreprise', 'Chantier Naval Agadir', 'Samira Naji', '0528887766', 'samira@cnagadir.ma', 'Port d\'Agadir', 'Agadir', 'Maroc', 'Commandes importantes'),
('particulier', 'Ali Moussa', 'Ali Moussa', '0612345678', 'ali.moussa@gmail.com', 'Rue 45, N°12', 'Meknès', 'Maroc', 'Client occasionnel'),
('entreprise', 'Mines du Sud', 'Omar El Fassi', '0522554433', 'achat@minesdusud.ma', 'Quartier Industriel', 'Ouarzazate', 'Maroc', 'Commandes volumineuses');

-- Insertion des emplacements de stockage
INSERT INTO emplacements (nom, description, actif) VALUES
('Zone A', 'Rayonnage principal - entrée', TRUE),
('Zone B', 'Arrière entrepôt - produits lourds', TRUE),
('Zone C', 'Stock sécurisé - produits de valeur', TRUE),
('Quarantaine', 'Zone de réception temporaire', TRUE);

-- Insertion des produits
INSERT INTO produits (reference, nom, categorie_id, unite_mesure, poids_metrique, stock_actuel, stock_minimal, prix_achat_moyen, prix_vente_ht, description) VALUES
('FT-AC-6', 'Fil Toron Acier 6mm', 6, 'kg', NULL, 500, 100, 15.50, 22.00, 'Fil toron acier galvanisé 6mm - rouleaux de 100kg'),
('FT-IN-8', 'Fil Toron Inox 8mm', 7, 'kg', NULL, 300, 50, 28.00, 42.00, 'Fil toron inoxydable 8mm - résistant à la corrosion'),
('MSQ-50', 'Mousqueton 50mm', 8, 'un', NULL, 120, 30, 18.00, 27.50, 'Mousqueton acier 50mm - charge 3.2t'),
('MAN-10', 'Manille 10mm', 9, 'un', NULL, 80, 20, 12.50, 19.90, 'Manille acier galvanisé 10mm - sécurité'),
('SL-P-2T', 'Sangle Plate 2T', 10, 'un', NULL, 45, 10, 85.00, 129.00, 'Sangle plate polyester 2 tonnes - 2m'),
('SL-R-5T', 'Sangle Ronde 5T', 11, 'un', NULL, 25, 5, 220.00, 349.00, 'Sangle ronde 5 tonnes - 3m'),
('CAB-12', 'Câble acier 12mm', 4, 'm', NULL, 1200, 300, 8.50, 14.90, 'Câble acier galvanisé 12mm - par mètre'),
('CH-8', 'Chaîne levage 8mm', 5, 'm', NULL, 800, 200, 6.20, 10.50, 'Chaîne de levage grade 80 - 8mm');

-- Insertion du stock par emplacement
INSERT INTO stock_emplacements (produit_id, emplacement_id, quantite) VALUES
(1, 1, 300), (1, 2, 200),
(2, 1, 200), (2, 3, 100),
(3, 1, 80), (3, 3, 40),
(4, 1, 50), (4, 3, 30),
(5, 2, 30), (5, 3, 15),
(6, 2, 15), (6, 3, 10),
(7, 2, 800), (7, 1, 400),
(8, 2, 500), (8, 1, 300);

-- Insertion d'un achat exemple
INSERT INTO achats (reference, fournisseur_id, utilisateur_id, date_achat, date_reception, statut, montant_total_ht, tva, notes) VALUES
('ACH-2023-001', 1, 2, '2023-01-15', '2023-01-18', 'recu', 7750.00, 20, 'Commande régulière de fil acier');

INSERT INTO details_achats (achat_id, produit_id, quantite, prix_unitaire_ht, tva, date_peremption, numero_lot) VALUES
(1, 1, 500, 15.50, 20, NULL, 'LOT-FT-AC-2301');

-- Insertion d'une commande exemple
INSERT INTO commandes (reference, client_id, utilisateur_id, date_commande, date_livraison_prevue, statut, montant_total_ht, remise, tva, notes) VALUES
('CMD-2023-001', 1, 3, '2023-01-20', '2023-01-25', 'livree', 438.50, 5, 20, 'Première commande de l\'année');

INSERT INTO details_commandes (commande_id, produit_id, quantite, prix_unitaire_ht, remise, tva) VALUES
(1, 1, 10, 22.00, 5, 20),
(1, 3, 5, 27.50, 5, 20),
(1, 5, 2, 129.00, 5, 20);

-- Insertion des historiques de prix
INSERT INTO historiques_prix (produit_id, type, ancien_prix, nouveau_prix, date_changement, utilisateur_id, notes) VALUES
(1, 'vente', 20.00, 22.00, '2023-01-01', 1, 'Augmentation due à hausse coût matière première'),
(3, 'vente', 25.00, 27.50, '2023-01-01', 1, 'Réajustement tarifaire'),
(5, 'vente', 120.00, 129.00, '2022-12-15', 1, 'Indexation sur inflation');

