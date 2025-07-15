# Documentation des Fixtures EPROFOS

## Vue d'ensemble

Ce document décrit les fixtures créées pour la plateforme e-learning EPROFOS. Les fixtures permettent de peupler la base de données avec des données de test réalistes pour le développement et les tests.

## Fixtures créées

### 1. CategoryFixtures
**Fichier :** `src/DataFixtures/CategoryFixtures.php`

Crée 8 catégories de formations professionnelles :
- **Informatique et Numérique** - Technologies de l'information, développement web, cybersécurité
- **Management et Leadership** - Compétences managériales et de leadership
- **Langues Étrangères** - Formations linguistiques professionnelles
- **Comptabilité et Finance** - Gestion financière et comptable
- **Marketing et Communication** - Marketing digital, communication, réseaux sociaux
- **Ressources Humaines** - Gestion des talents, recrutement, droit du travail
- **Qualité et Amélioration Continue** - Méthodes qualité, amélioration des processus
- **Sécurité et Prévention** - Sécurité au travail, prévention des risques (temporairement inactive)

### 2. ServiceCategoryFixtures
**Fichier :** `src/DataFixtures/ServiceCategoryFixtures.php`

Crée 5 catégories de services :
- **Conseil et Expertise** - Services de conseil personnalisés
- **Formation Sur Mesure** - Formations adaptées aux besoins spécifiques
- **Certification et Validation** - Services de certification professionnelle
- **Accompagnement et Coaching** - Coaching professionnel et accompagnement
- **Audit et Diagnostic** - Services d'audit et d'évaluation

### 3. FormationFixtures
**Fichier :** `src/DataFixtures/FormationFixtures.php`

Crée 10 formations professionnelles détaillées :

#### Informatique et Numérique
- **Développement Web avec PHP et Symfony** (63h, 2890€) - Formation complète Symfony 7
- **Cybersécurité et Protection des Données** (35h, 1890€) - Sécurité informatique et RGPD
- **Maîtrise d'Excel Avancé et Power BI** (42h, 1990€) - Analyse de données avancée

#### Management et Leadership
- **Leadership et Management d'Équipe** (35h, 2190€) - Compétences managériales
- **Gestion de Projet Agile - Scrum Master** (35h, 2490€) - Méthodologie Scrum

#### Langues Étrangères
- **Anglais Professionnel - Business English** (42h, 1690€) - Communication professionnelle

#### Comptabilité et Finance
- **Comptabilité Générale et Analyse Financière** (42h, 2290€) - Gestion financière

#### Marketing et Communication
- **Marketing Digital et Réseaux Sociaux** (42h, 2190€) - Stratégie digitale

#### Ressources Humaines
- **Recrutement et Gestion des Talents** (35h, 2090€) - Processus RH

#### Qualité et Amélioration Continue
- **Lean Management et Amélioration Continue** (35h, 2390€) - Méthodes Lean

### 4. ServiceFixtures
**Fichier :** `src/DataFixtures/ServiceFixtures.php`

Crée 15 services répartis dans les 5 catégories :

#### Conseil et Expertise (3 services)
- Audit de Compétences et Plan de Formation
- Conseil en Transformation Digitale
- Conseil en Organisation et Méthodes

#### Formation Sur Mesure (3 services)
- Formation Intra-Entreprise Personnalisée
- Conception de Parcours de Formation
- Formation de Formateurs Internes

#### Certification et Validation (3 services)
- Préparation aux Certifications Professionnelles
- Validation des Acquis de l'Expérience (VAE)
- Évaluation et Certification Interne

#### Accompagnement et Coaching (3 services)
- Coaching Individuel de Dirigeants
- Accompagnement au Changement
- Coaching d'Équipe et Team Building

#### Audit et Diagnostic (3 services)
- Audit de Performance Organisationnelle
- Audit de Conformité et Qualité
- Diagnostic Digital et Cybersécurité

### 5. ContactRequestFixtures
**Fichier :** `src/DataFixtures/ContactRequestFixtures.php`

Crée 8 demandes de contact de test avec différents types et statuts :
- **Types :** quote (devis), advice (conseil), information, quick_registration (inscription rapide)
- **Statuts :** pending (en attente), in_progress (en cours), completed (terminé), cancelled (annulé)
- **Données :** Noms, emails, entreprises, messages réalistes
- **Relations :** Certaines demandes sont liées à des formations ou services spécifiques

### 6. AppFixtures
**Fichier :** `src/DataFixtures/AppFixtures.php`

Fixture principale qui orchestre le chargement de toutes les autres fixtures dans le bon ordre grâce aux dépendances.

## Instructions d'utilisation

### Chargement des fixtures

Pour charger toutes les fixtures dans la base de données :

```bash
docker compose exec -it php php bin/console doctrine:fixtures:load
```

Cette commande :
1. Vide complètement la base de données
2. Charge les fixtures dans l'ordre des dépendances
3. Crée toutes les données de test

### Rechargement des fixtures

Pour recharger les fixtures (attention : supprime toutes les données existantes) :

```bash
docker compose exec -it php php bin/console doctrine:fixtures:load --no-interaction
```

L'option `--no-interaction` évite la demande de confirmation.

### Vérification des données

Pour vérifier que les données ont été chargées :

```bash
# Compter les catégories
docker compose exec -it php php bin/console doctrine:query:sql "SELECT COUNT(*) FROM category"

# Compter les formations
docker compose exec -it php php bin/console doctrine:query:sql "SELECT COUNT(*) FROM formation"

# Compter les services
docker compose exec -it php php bin/console doctrine:query:sql "SELECT COUNT(*) FROM service"

# Compter les demandes de contact
docker compose exec -it php php bin/console doctrine:query:sql "SELECT COUNT(*) FROM contact_requests"
```

## Données générées

### Résumé des quantités
- **8 catégories** de formations
- **5 catégories** de services
- **10 formations** détaillées avec contenu complet
- **15 services** avec descriptions et bénéfices
- **8 demandes de contact** avec différents statuts

### Caractéristiques des données

#### Formations
- Durées variées : de 35h à 63h
- Prix réalistes : de 1690€ à 2890€
- Niveaux : Débutant, Intermédiaire, Avancé
- Formats : Présentiel, Hybride
- Contenu détaillé : objectifs, prérequis, programme
- Certaines formations sont mises en avant (featured)

#### Services
- Descriptions détaillées et professionnelles
- Bénéfices listés pour chaque service
- Icônes Font Awesome appropriées
- Répartition équilibrée dans les catégories

#### Demandes de contact
- Données personnelles réalistes
- Messages contextuels et variés
- Dates de création étalées sur 20 jours
- Statuts de traitement différents
- Notes administratives pour les demandes traitées

## Utilisation pour le développement

Ces fixtures permettent de :
- Tester l'interface utilisateur avec des données réalistes
- Valider les fonctionnalités de recherche et filtrage
- Tester les relations entre entités
- Développer les fonctionnalités d'administration
- Effectuer des tests de performance avec un volume de données approprié

## Maintenance

Pour ajouter de nouvelles données :
1. Modifier les fixtures existantes
2. Créer de nouvelles fixtures si nécessaire
3. Mettre à jour les dépendances dans `AppFixtures`
4. Recharger les fixtures avec la commande appropriée

Les fixtures sont conçues pour être facilement extensibles et maintenir la cohérence des données.