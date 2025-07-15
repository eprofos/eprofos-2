# Guide d'utilisation - Système d'analyse des besoins EPROFOS

## Vue d'ensemble

Le système d'analyse des besoins EPROFOS est maintenant complètement implémenté et conforme au critère Qualiopi 2.4. Ce guide explique comment utiliser toutes les fonctionnalités du système.

## 🚀 Fonctionnalités principales

### Interface administrateur
- **Création de demandes** : Génération de liens sécurisés pour les analyses
- **Gestion des demandes** : Suivi du statut et des réponses
- **Visualisation des analyses** : Affichage détaillé des réponses
- **Statistiques** : Tableau de bord avec métriques

### Interface publique
- **Formulaires sécurisés** : Accès via tokens UUID uniques
- **Sauvegarde automatique** : Reprise possible à tout moment
- **Validation avancée** : Contrôles de cohérence des données
- **Interface responsive** : Compatible mobile et desktop

### Notifications automatiques
- **Email d'envoi** : Notification avec lien d'accès
- **Email de confirmation** : Accusé de réception après soumission
- **Rappels automatiques** : Notifications avant expiration
- **Notifications admin** : Alertes pour nouvelles analyses

## 📋 Workflow complet

### 1. Création d'une demande (Admin)

```bash
# Accès à l'interface admin
https://votre-domaine.com/admin/needs-analysis

# Actions disponibles :
- Créer une nouvelle demande
- Choisir le type (Entreprise/Particulier)
- Saisir les informations de contact
- Générer et envoyer le lien sécurisé
```

### 2. Réception par le bénéficiaire

```bash
# Email automatique contenant :
- Lien sécurisé personnalisé
- Instructions détaillées
- Durée de validité (30 jours)
- Informations de contact
```

### 3. Completion du formulaire

```bash
# Page d'information
https://votre-domaine.com/public/needs-analysis/{token}/info

# Formulaire adapté au type
https://votre-domaine.com/public/needs-analysis/{token}/form/{type}

# Pages de statut
- /success : Formulaire complété
- /expired : Lien expiré
- /completed : Déjà complété
```

### 4. Suivi administrateur

```bash
# Tableau de bord
https://votre-domaine.com/admin/needs-analysis

# Détail d'une analyse
https://votre-domaine.com/admin/needs-analysis/{id}

# Actions disponibles :
- Voir l'analyse complète
- Ajouter des notes admin
- Changer le statut
- Renvoyer le lien
```

## 🛠️ Commandes automatisées

### Expiration automatique

```bash
# Marquer les demandes expirées
docker compose exec -it php php bin/console app:needs-analysis:expire

# Options disponibles :
--dry-run    # Simulation sans modification
--force      # Forcer l'expiration (test)

# Planification recommandée (crontab) :
0 2 * * * docker compose exec php php bin/console app:needs-analysis:expire
```

### Rappels automatiques

```bash
# Envoyer des rappels avant expiration
docker compose exec -it php php bin/console app:needs-analysis:remind

# Options disponibles :
--days-before-expiry=7  # Jours avant expiration (défaut: 7)
--dry-run              # Simulation sans envoi
--force                # Ignorer les rappels récents

# Planification recommandée (crontab) :
0 9 * * * docker compose exec php php bin/console app:needs-analysis:remind --days-before-expiry=7
0 9 * * * docker compose exec php php bin/console app:needs-analysis:remind --days-before-expiry=3
0 9 * * * docker compose exec php php bin/console app:needs-analysis:remind --days-before-expiry=1
```

## 📧 Templates d'emails

### 1. Email d'envoi (`needs_analysis_sent.html.twig`)
- **Objet** : "Analyse des besoins de formation - EPROFOS"
- **Contenu** : Lien d'accès, instructions, informations pratiques
- **Variables** : `request`, `form_url`, `expires_at`

### 2. Email de confirmation (`needs_analysis_completed.html.twig`)
- **Objet** : "Analyse des besoins complétée avec succès"
- **Contenu** : Confirmation, prochaines étapes, résumé
- **Variables** : `request`, `analysis`

### 3. Email de rappel (`needs_analysis_reminder.html.twig`)
- **Objet** : "Rappel - Analyse des besoins EPROFOS expire bientôt"
- **Contenu** : Urgence, avantages, lien d'accès
- **Variables** : `request`, `form_url`, `days_remaining`

## 🎨 Templates d'interface

### Templates publics
- `info.html.twig` : Page d'information pré-formulaire
- `company_form.html.twig` : Formulaire entreprise
- `individual_form.html.twig` : Formulaire particulier
- `success.html.twig` : Confirmation de soumission
- `expired.html.twig` : Lien expiré
- `completed.html.twig` : Déjà complété

### Templates admin
- `index.html.twig` : Liste des demandes
- `show.html.twig` : Détail d'une demande
- `new.html.twig` : Création de demande
- `edit.html.twig` : Modification de demande
- `_company_analysis.html.twig` : Affichage analyse entreprise
- `_individual_analysis.html.twig` : Affichage analyse particulier

## 🔧 Configuration

### Variables d'environnement

```env
# Configuration email
MAILER_DSN=smtp://localhost:1025
EPROFOS_FROM_EMAIL=noreply@eprofos.fr
EPROFOS_FROM_NAME="EPROFOS - École Professionnelle"
EPROFOS_ADMIN_EMAIL=admin@eprofos.fr

# Configuration application
APP_URL=https://votre-domaine.com
```

### Services configurés

```yaml
# config/services.yaml
parameters:
    app.needs_analysis.token_expiry_days: 30
    app.needs_analysis.reminder_days: [7, 3, 1]
```

## 📊 Métriques et statistiques

### Données collectées
- **Nombre total de demandes**
- **Répartition par statut** (pending, sent, completed, expired, cancelled)
- **Répartition par type** (company, individual)
- **Taux de completion**
- **Temps moyen de completion**

### Accès aux statistiques

```bash
# Via l'interface admin
https://votre-domaine.com/admin/needs-analysis

# Via API (si implémentée)
GET /api/admin/needs-analysis/statistics
```

## 🔒 Sécurité

### Tokens d'accès
- **Format** : UUID v4 (36 caractères)
- **Unicité** : Garantie par contrainte base de données
- **Expiration** : 30 jours par défaut
- **Usage unique** : Un token par demande

### Validation des données
- **Côté client** : JavaScript avec feedback immédiat
- **Côté serveur** : Contraintes Symfony strictes
- **Base de données** : Contraintes de schéma

### Protection CSRF
- **Formulaires** : Tokens CSRF automatiques
- **Sessions** : Gestion sécurisée des sessions
- **Headers** : Protection contre les attaques XSS

## 🚨 Dépannage

### Problèmes courants

#### 1. Email non reçu
```bash
# Vérifier la configuration MAILER_DSN
docker compose exec -it php php bin/console debug:config framework mailer

# Tester l'envoi d'email
docker compose exec -it php php bin/console app:test-email votre@email.com
```

#### 2. Lien expiré prématurément
```bash
# Vérifier la configuration d'expiration
docker compose exec -it php php bin/console debug:config app

# Vérifier les demandes expirées
docker compose exec -it php php bin/console app:needs-analysis:expire --dry-run
```

#### 3. Formulaire ne se sauvegarde pas
```bash
# Vérifier les logs
docker compose logs php

# Vérifier la base de données
docker compose exec -it postgres psql -U app -d app -c "SELECT * FROM needs_analysis_request WHERE token = 'TOKEN';"
```

### Logs utiles

```bash
# Logs de l'application
docker compose logs php | grep "needs_analysis"

# Logs des emails
docker compose logs php | grep "mailer"

# Logs des commandes
docker compose logs php | grep "command"
```

## 📈 Optimisations recommandées

### Performance
- **Cache** : Mise en cache des statistiques
- **Index** : Index sur les champs de recherche
- **Pagination** : Limitation des résultats

### Monitoring
- **Métriques** : Suivi des taux de conversion
- **Alertes** : Notifications en cas d'erreur
- **Rapports** : Génération automatique de rapports

### Maintenance
- **Nettoyage** : Suppression des données anciennes
- **Archivage** : Sauvegarde des analyses complétées
- **Mise à jour** : Évolution des formulaires

## 📞 Support

### Contacts techniques
- **Email** : dev@eprofos.fr
- **Documentation** : `/docs/needs-analysis/`
- **Issues** : Repository Git du projet

### Formation utilisateurs
- **Guide admin** : Formation interface d'administration
- **Guide utilisateur** : Formation completion formulaires
- **Guide technique** : Formation maintenance système

---

**Version** : 1.0.0  
**Dernière mise à jour** : 22/06/2025  
**Conformité** : Qualiopi 2.4 ✅