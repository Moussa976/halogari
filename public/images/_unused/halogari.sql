--
-- Base de données : `covoiturage_mayotte`
--

-- --------------------------------------------------------

--
-- Structure de la table `messages`
--

DROP TABLE IF EXISTS `messages`;
CREATE TABLE IF NOT EXISTS `messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `expediteur_id` int NOT NULL,
  `destinataire_id` int NOT NULL,
  `contenu` text NOT NULL,
  `date_envoi` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `expediteur_id` (`expediteur_id`),
  KEY `destinataire_id` (`destinataire_id`)
);

--
-- Déchargement des données de la table `messages`
--

INSERT INTO `messages` (`id`, `expediteur_id`, `destinataire_id`, `contenu`, `date_envoi`) VALUES
(1, 1, 1, 'Bonjour', '2025-04-30 14:26:27'),
(2, 1, 1, 'Bonjour,dfd rgs f', '2025-04-30 14:28:04');

-- --------------------------------------------------------

--
-- Structure de la table `notes`
--

DROP TABLE IF EXISTS `notes`;
CREATE TABLE IF NOT EXISTS `notes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `noteur_id` int NOT NULL,
  `note_pour_id` int NOT NULL,
  `trajet_id` int NOT NULL,
  `note` int NOT NULL,
  `commentaire` text,
  `date_note` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `noteur_id` (`noteur_id`),
  KEY `note_pour_id` (`note_pour_id`),
  KEY `trajet_id` (`trajet_id`)
) ;

-- --------------------------------------------------------

--
-- Structure de la table `reservations`
--

DROP TABLE IF EXISTS `reservations`;
CREATE TABLE IF NOT EXISTS `reservations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `passager_id` int NOT NULL,
  `trajet_id` int NOT NULL,
  `statut` enum('réservé','annulé') DEFAULT 'réservé',
  `date_reservation` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `passager_id` (`passager_id`),
  KEY `trajet_id` (`trajet_id`)
);

--
-- Déchargement des données de la table `reservations`
--

INSERT INTO `reservations` (`id`, `passager_id`, `trajet_id`, `statut`, `date_reservation`) VALUES
(1, 2, 3, 'réservé', '2025-04-30 13:42:21'),
(2, 2, 3, 'réservé', '2025-04-30 13:45:02'),
(3, 2, 3, 'réservé', '2025-04-30 14:28:25'),
(4, 2, 3, 'réservé', '2025-04-30 20:18:04'),
(5, 2, 1, 'réservé', '2025-05-01 16:16:33');

-- --------------------------------------------------------

--
-- Structure de la table `trajets`
--

DROP TABLE IF EXISTS `trajets`;
CREATE TABLE IF NOT EXISTS `trajets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `conducteur_id` int NOT NULL,
  `depart` varchar(255) NOT NULL,
  `arrivee` varchar(255) NOT NULL,
  `date_trajet` date NOT NULL,
  `heure_trajet` time NOT NULL,
  `places_disponibles` int NOT NULL,
  `prix` decimal(5,2) NOT NULL,
  `date_publication` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `conducteur_id` (`conducteur_id`)
);

--
-- Déchargement des données de la table `trajets`
--

INSERT INTO `trajets` (`id`, `conducteur_id`, `depart`, `arrivee`, `date_trajet`, `heure_trajet`, `places_disponibles`, `prix`, `date_publication`) VALUES
(1, 1, 'Bouéni', 'Dapani', '2025-05-03', '00:56:00', 3, 2.05, '2025-04-27 23:08:10'),
(2, 1, 'Bouyouni', 'Bandraboua', '2025-04-15', '05:10:00', 25, 87.00, '2025-04-27 23:10:48'),
(3, 1, 'Barakani', 'Choungui', '2025-04-15', '14:46:00', 53, 35.00, '2025-04-30 12:41:21');

-- --------------------------------------------------------

--
-- Structure de la table `utilisateurs`
--

DROP TABLE IF EXISTS `utilisateurs`;
CREATE TABLE IF NOT EXISTS `utilisateurs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) NOT NULL,
  `prenom` varchar(255) NOT NULL,
  `email` varchar(191) NOT NULL,
  `mot_de_passe` varchar(255) NOT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `date_creation` datetime DEFAULT CURRENT_TIMESTAMP,
  `role` enum('admin','conducteur','passager') DEFAULT 'passager',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
);

--
-- Déchargement des données de la table `utilisateurs`
--

INSERT INTO `utilisateurs` (`id`, `nom`, `prenom`, `email`, `mot_de_passe`, `telephone`, `date_creation`, `role`) VALUES
(1, 'INSSA', 'Moussa', 'moussainssa@outlook.fr', '$2b$10$wVoBHO47RnZR29ezuctuNOaEzN6sesduH9K4RvN0dUz1m1bThaDhW', '063939274954', '2025-04-30 13:52:55', 'conducteur');

