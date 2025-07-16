# Documentation des Fixtures - Système de Questionnaires EPROFOS

## Vue d'ensemble

Ce document décrit les fixtures créées pour le système de questionnaires de la plateforme EPROFOS, en conformité avec le critère Qualiopi 2.8 sur l'évaluation et le positionnement des apprenants.

## Structure des Fixtures

### 1. QuestionnaireFixtures.php

Crée **8 questionnaires** couvrant différents types et domaines :

#### Questionnaires de Positionnement
- **Développement PHP/Symfony** : 10 questions (actif)
- **Excel Avancé et Power BI** : 10 questions (actif)  
- **Anglais Professionnel** : 10 questions (actif)
- **Gestion de Projet Agile** : 8 questions (brouillon)

#### Questionnaires d'Évaluation
- **Leadership et Management** : 8 questions (actif)
- **Cybersécurité** : 10 questions (évaluation compétences, actif)

#### Questionnaires de Satisfaction
- **Marketing Digital** : 10 questions (spécialisé, actif)
- **Satisfaction Générale** : 12 questions (générique, actif)

### 2. QuestionFixtures.php

Crée **70 questions** au total avec différents types :

#### Types de Questions
- **Single Choice** (QCM simple) : 25 questions
- **Multiple Choice** (QCM multiple) : 15 questions
- **Textarea** (Texte long) : 12 questions
- **Text** (Texte court) : 8 questions
- **Number** (Numérique) : 6 questions
- **File Upload** (Téléchargement) : 3 questions
- **Date** : 1 question
- **Email** : 0 question (type disponible)

#### Caractéristiques
- Questions obligatoires et optionnelles
- Textes d'aide et placeholders
- Limitations de longueur
- Système de points pour l'évaluation
- Types de fichiers autorisés pour les uploads

### 3. QuestionOptionFixtures.php

Crée **267 options** pour les questions à choix multiples :

#### Répartition
- Options correctes/incorrectes pour l'évaluation
- Points attribués selon la pertinence
- Ordonnancement des options
- Textes réalistes adaptés au contexte

### 4. QuestionnaireResponseFixtures.php

Crée **102 réponses** de questionnaires avec différents états :

#### États des Réponses
- **Terminées** : 78 réponses (76%)
- **En cours** : 16 réponses (16%)
- **Abandonnées** : 8 réponses (8%)

#### Données Générées
- Informations personnelles réalistes (Faker FR)
- Scores calculés automatiquement
- Durées de completion variables (10-60 min)
- Notes d'évaluateur et recommandations
- États d'évaluation (en attente, en cours, terminé)

#### Répartition par Questionnaire
- **PHP/Symfony** : 15 réponses
- **Leadership** : 12 réponses
- **Cybersécurité** : 8 réponses
- **Excel** : 12 réponses
- **Anglais** : 10 réponses
- **Marketing** : 20 réponses
- **Satisfaction générale** : 25 réponses

### 5. QuestionResponse (Réponses individuelles)

Crée **768 réponses individuelles** aux questions :

#### Types de Réponses
- Choix multiples avec IDs d'options
- Textes libres contextualisés
- Valeurs numériques réalistes
- Dates de naissance pour statistiques
- Fichiers simulés (noms générés)

## Fonctionnalités Couvertes

### Conformité Qualiopi 2.8

✅ **Positionnement initial** : Questionnaires de positionnement avant formation
✅ **Évaluation des acquis** : Questionnaires d'évaluation post-formation
✅ **Satisfaction** : Recueil de satisfaction spécialisé et générique
✅ **Traçabilité** : Historique complet des réponses et évaluations

### Types de Questions Supportés

✅ **Texte court/long** : Pour réponses ouvertes
✅ **Choix unique** : QCM classique
✅ **Choix multiples** : Sélection multiple
✅ **Numérique** : Années d'expérience, scores, etc.
✅ **Date** : Informations temporelles
✅ **Email** : Validation d'adresses
✅ **Upload** : CV, documents, etc.

### Fonctionnalités Avancées

✅ **Multi-étapes** : Navigation par étapes configurable
✅ **Limitation de temps** : Durée maximale par questionnaire
✅ **Progression** : Barre de progression et navigation
✅ **Calcul de scores** : Système de points automatique
✅ **Évaluation manuelle** : Notes et recommandations
✅ **Personnalisation** : Messages d'accueil et de fin

## Utilisation des Fixtures

### Chargement
```bash
# Charger toutes les fixtures
php bin/console doctrine:fixtures:load

# Charger seulement les questionnaires
php bin/console doctrine:fixtures:load --group=questionnaire
```

### Références Disponibles
Les fixtures créent des références utilisables :
- `QuestionnaireFixtures::QUESTIONNAIRE_PHP_POSITIONING`
- `QuestionnaireFixtures::QUESTIONNAIRE_LEADERSHIP_EVALUATION`
- `QuestionnaireFixtures::QUESTIONNAIRE_CYBERSECURITY_SKILLS`
- etc.

### Données de Test

#### Comptes Utilisateurs Types
- Développeurs avec différents niveaux PHP
- Managers avec expériences variées
- Apprenants en cybersécurité
- Utilisateurs Excel de tous niveaux
- Professionnels anglophones

#### Scénarios de Test
- Questionnaires complétés avec scores variés
- Abandons en cours de route
- Évaluations en attente de traitement
- Satisfactions excellentes à médiocres

## Statistiques Générées

### Volumétrie
- 8 questionnaires actifs
- 70 questions diversifiées
- 267 options de réponse
- 102 participants simulés
- 768 réponses individuelles

### Répartition par Type
- 50% questionnaires de positionnement
- 25% questionnaires d'évaluation
- 25% questionnaires de satisfaction

### Taux de Completion
- 76% de réponses complètes
- 16% en cours
- 8% abandonnées

## Points d'Attention

### Conformité RGPD
- Données générées avec Faker (fictives)
- Emails uniques mais non réels
- Possibilité d'anonymisation

### Performance
- Fixtures optimisées pour le développement
- Données réalistes sans surcharge
- Relations correctement définies

### Extensibilité
- Structure modulaire pour ajouts
- Références partagées entre fixtures
- Types de questions extensibles

## Cas d'Usage

### Tests Fonctionnels
- Validation du parcours complet
- Test des différents types de questions
- Vérification des calculs de scores

### Démonstrations
- Présentation client avec données réalistes
- Formation des utilisateurs
- Tests de charge

### Développement
- Données cohérentes pour le développement
- Tests unitaires et d'intégration
- Validation des nouvelles fonctionnalités

## Maintenance

### Mise à Jour
Les fixtures sont versionnées et peuvent être mises à jour :
- Ajout de nouveaux questionnaires
- Modification des questions existantes
- Évolution des types de réponses

### Sauvegarde
Les données de fixtures peuvent être exportées :
```bash
# Export SQL
php bin/console doctrine:schema:create --dump-sql

# Export données
php bin/console doctrine:fixtures:export
```

Cette documentation sera mise à jour en fonction de l'évolution du système de questionnaires.
