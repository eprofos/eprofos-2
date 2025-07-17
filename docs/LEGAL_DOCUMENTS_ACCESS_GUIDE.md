# Documentation - Accès aux Documents Légaux EPROFOS

## Table des matières
1. [Vue d'ensemble](#vue-densemble)
2. [Interface Admin - Gestion des documents](#interface-admin---gestion-des-documents)
3. [Interface Publique - Consultation des documents](#interface-publique---consultation-des-documents)
4. [Conditions d'accès](#conditions-daccès)
5. [Fonctionnalités spéciales](#fonctionnalités-spéciales)
6. [Troubleshooting](#troubleshooting)

---

## Vue d'ensemble

EPROFOS propose un système complet de gestion des documents légaux conforme aux exigences Qualiopi 3.9. Chaque type de document dispose de sa propre page dédiée avec une URL unique.

### Types de documents gérés
- **Règlement intérieur** - Règles internes pour les stagiaires
- **Livret d'accueil stagiaire** - Guide d'accueil et informations pratiques
- **Conditions générales de formation** - Modalités détaillées des formations
- **Politique d'accessibilité** - Engagement handicap et inclusion
- **Procédures d'accessibilité** - Processus d'adaptation pédagogique
- **FAQ Accessibilité** - Questions fréquentes sur l'accessibilité

---

## Interface Admin - Gestion des documents

### 🔐 Conditions d'accès Admin
- **Authentification requise** : Compte admin actif
- **Rôle requis** : `ROLE_ADMIN`
- **URL de connexion** : `/admin/login`

### 📍 Dashboard principal
**URL** : `/admin/legal-documents/`  
**Accès** : Sidebar Admin → "Documents légaux" → "Tous les documents"

**Contenu disponible :**
- Vue d'ensemble avec statistiques globales
- Accès rapide à chaque type de document
- Indicateurs de conformité Qualiopi
- Actions rapides (création, gestion)

### 📋 Pages spécialisées par type

#### 1. Règlements intérieurs
- **URL** : `/admin/legal-documents/reglements-interieurs`
- **Accès** : Sidebar → "Documents légaux" → "Règlements intérieurs"
- **Badge** : 🔵 Bleu
- **Fonctionnalités** :
  - Liste des règlements par version
  - Statistiques spécifiques (total, publiés, brouillons)
  - Création rapide pré-remplie avec le type
  - Filtres : recherche, statut (publié/brouillon/inactif)

#### 2. Livrets d'accueil
- **URL** : `/admin/legal-documents/livrets-accueil`
- **Accès** : Sidebar → "Documents légaux" → "Livrets d'accueil"
- **Badge** : 🟢 Vert
- **Spécificités** : Gestion des versions par année de formation

#### 3. Conditions de formation
- **URL** : `/admin/legal-documents/conditions-formation`
- **Accès** : Sidebar → "Documents légaux" → "Conditions de formation"
- **Badge** : 🟣 Violet
- **Spécificités** : Documents contractuels critiques

#### 4. Politique d'accessibilité
- **URL** : `/admin/legal-documents/politique-accessibilite`
- **Accès** : Sidebar → "Documents légaux" → "Politiques accessibilité"
- **Badge** : 🟠 Orange avec ♿
- **Importance** : Requis pour certification Qualiopi

#### 5. Procédures d'accessibilité
- **URL** : `/admin/legal-documents/procedures-accessibilite`
- **Accès** : Sidebar → "Documents légaux" → "Procédures accessibilité"
- **Badge** : 🟡 Jaune avec ⚙️
- **Spécificités** : Documents opérationnels pour adaptations

#### 6. FAQ Accessibilité
- **URL** : `/admin/legal-documents/faq-accessibilite`
- **Accès** : Sidebar → "Documents légaux" → "FAQ Accessibilité"
- **Badge** : 🔵 Cyan avec ❓
- **Spécificités** : Questions/réponses publiques

### 🛠️ Actions disponibles par document

#### Consultation
- **URL** : `/admin/legal-documents/{id}`
- **Conditions** : Document existant, accès admin
- **Contenu** : Détails complets, métadonnées, historique

#### Création
- **URL** : `/admin/legal-documents/new`
- **URL avec type pré-rempli** : `/admin/legal-documents/new?type={document_type}`
- **Conditions** : Accès admin
- **Formulaire** : Type, titre, version, contenu, fichier (optionnel)

#### Modification
- **URL** : `/admin/legal-documents/{id}/edit`
- **Conditions** : Document existant, accès admin
- **Actions** : Modification de tous les champs, upload de fichier

#### Publication/Dépublication
- **URL** : `/admin/legal-documents/{id}/toggle-publish` (POST)
- **Conditions** : Document existant, token CSRF valide
- **Effet** : Rend le document visible/invisible au public

#### Suppression
- **URL** : `/admin/legal-documents/{id}` (POST DELETE)
- **Conditions** : Document existant, confirmation, token CSRF
- **Attention** : Action irréversible

---

## Interface Publique - Consultation des documents

### 🌐 Conditions d'accès Public
- **Authentification** : Non requise
- **Conditions** : Document publié et actif
- **Visibilité** : Seulement les documents avec `publishedAt` défini et dans le passé

### 📍 Page d'accueil des informations stagiaires
**URL** : `/informations-stagiaires`  
**Contenu** : Vue d'ensemble des documents essentiels pour les stagiaires

### 📖 Pages individuelles par document

#### 1. Règlement intérieur
- **URL** : `/reglement-interieur`
- **Slug** : `reglement-interieur`
- **Conditions** : Au moins un règlement publié
- **Erreur si vide** : "Règlement intérieur non disponible" (404)

#### 2. Livret d'accueil stagiaire
- **URL** : `/livret-accueil-stagiaire`
- **Slug** : `livret-accueil-stagiaire`
- **Conditions** : Au moins un livret publié
- **Erreur si vide** : "Livret d'accueil stagiaire non disponible" (404)

#### 3. Conditions générales de formation
- **URL** : `/conditions-generales-formation`
- **Slug** : `conditions-generales-formation`
- **Conditions** : Au moins un document conditions publié
- **Erreur si vide** : "Conditions générales de formation non disponibles" (404)

#### 4. Politique d'accessibilité
- **URL** : `/politique-accessibilite`
- **Slug** : `politique-accessibilite`
- **Conditions** : Au moins une politique publiée
- **Spécificité** : Affiche les coordonnées du référent handicap

#### 5. Procédures d'accessibilité
- **URL** : `/procedures-accessibilite`
- **Slug** : `procedures-accessibilite`
- **Conditions** : Au moins un document procédures publié
- **Spécificité** : Informations de contact référent handicap

#### 6. FAQ Accessibilité
- **URL** : `/faq-accessibilite`
- **Slug** : `faq-accessibilite`
- **Conditions** : Au moins une FAQ publiée
- **Spécificité** : Navigation vers autres documents accessibilité

### 📄 Anciennes routes (compatibilité)

#### Vue générale accessibilité
- **URL** : `/accessibilite-handicap`
- **Contenu** : Agrégation des 3 documents accessibilité
- **Conditions** : Au moins un document accessibilité publié

#### Téléchargements
- **URL** : `/documents-telechargement`
- **Contenu** : Liste de tous les documents téléchargeables
- **Conditions** : Au moins un document publié avec fichier
- **Actions disponibles** : Téléchargement individuel, contact via `/contact`

#### Téléchargement global
- **URL** : `/documents-telechargement/tout`
- **Format** : Archive ZIP
- **Conditions** : Au moins un document publié avec fichier
- **Nom fichier** : `EPROFOS_Documents_Legaux.zip`

#### Vue par type (ancienne méthode)
- **URL** : `/documents/{type}`
- **Types valides** : `internal_regulation`, `student_handbook`, `training_terms`, `accessibility_policy`, `accessibility_procedures`, `accessibility_faq`
- **Conditions** : Document du type spécifié publié

---

## Conditions d'accès

### ✅ Conditions de publication (rend le document public)
1. **Document actif** : `isActive = true`
2. **Date de publication définie** : `publishedAt IS NOT NULL`
3. **Date de publication passée** : `publishedAt <= NOW()`

### 🔍 Logique de sélection de version
- **Version affichée** : Dernière version publiée selon critères ci-dessus
- **Tri** : Par `version DESC`, puis `publishedAt DESC`
- **Unicité** : Une seule version par type affiché au public

### 🚫 Conditions d'erreur 404
- Document non trouvé
- Aucun document du type publié
- Document inactif
- Date de publication future

### 🔐 Conditions d'accès admin
- Session authentifiée valide
- Rôle `ROLE_ADMIN`
- Token CSRF valide pour les actions de modification

---

## Fonctionnalités spéciales

### 📧 Accusé de réception des documents
- **URL** : `/documents/accuse-reception/{token}`
- **Méthodes** : GET (affichage), POST (confirmation)
- **Conditions** : Token valide dans `session_registration.documentAcknowledgmentToken`
- **Sécurité** : Token unique par inscription de session
- **Statuts** :
  - Formulaire d'accusé si non encore confirmé
  - Message de confirmation si déjà traité
  - Erreur 404 si token invalide/expiré

### 📁 Gestion de fichiers
- **Upload** : Interface admin uniquement
- **Stockage** : `/public/uploads/legal/`
- **Nommage** : `{slug}-{uniqid}.{extension}`
- **Types autorisés** : PDF principalement
- **Accès public** : Via `document.getFileUrl()` si document publié

### 📊 Statistiques et métriques
- **Dashboard admin** : Compteurs par type et statut
- **Pages type** : Statistiques spécifiques au type
- **Métriques disponibles** :
  - Total documents par type
  - Documents publiés vs brouillons
  - Version courante publiée
  - Date dernière publication

---

## Troubleshooting

### ❌ Problèmes courants

#### "Document non trouvé" (404)
**Causes possibles :**
- Document pas encore publié (`publishedAt` null)
- Date de publication future
- Document inactif (`isActive = false`)
- Aucun document de ce type en base

**Solutions :**
1. Vérifier le statut de publication dans l'admin
2. Contrôler la date de publication
3. S'assurer qu'au moins un document existe pour ce type

#### Accès refusé à l'admin
**Causes possibles :**
- Session expirée
- Rôle insuffisant
- Utilisateur inactif

**Solutions :**
1. Se reconnecter à `/admin/login`
2. Vérifier les rôles utilisateur
3. Contacter un super-admin

#### Fichier non téléchargeable
**Causes possibles :**
- Fichier non uploadé
- Chemin incorrect
- Permissions fichier

**Solutions :**
1. Vérifier la présence du fichier dans `/public/uploads/legal/`
2. Contrôler les permissions du répertoire
3. Re-uploader le fichier via l'admin

#### Erreur "property does not exist" dans les templates
**Causes possibles :**
- Référence à une propriété inexistante (ex: `description` au lieu de `content`)
- Entité modifiée sans mise à jour des templates
- Cache non vidé

**Solutions :**
1. Vérifier les propriétés disponibles dans l'entité `LegalDocument`
2. Utiliser `document.content` pour le contenu au lieu de `document.description`
3. Appliquer le filtre `striptags` pour le contenu HTML : `{{ document.content|striptags|slice(0, 120) }}`
4. Vider le cache : `docker compose exec php php bin/console cache:clear`

#### Token d'accusé invalide
**Causes possibles :**
- Token expiré ou utilisé
- Mauvais format de token
- Session registration supprimée

**Solutions :**
1. Vérifier l'existence de la session registration
2. Régénérer un nouveau token si nécessaire
3. Contrôler la validité du lien email

#### Erreur de route "does not exist"
**Causes possibles :**
- Route inexistante ou mal nommée
- Cache non vidé après modification
- Erreur de typo dans les templates

**Solutions :**
1. Vérifier les routes disponibles : `docker compose exec php php bin/console debug:router | grep contact`
2. Utiliser `app_contact_index` au lieu de `app_contact`
3. Utiliser `app_formations_index` au lieu de `app_formations`
4. Vider le cache : `docker compose exec php php bin/console cache:clear`
5. Vérifier la syntaxe Twig : `{{ path('route_name') }}`

**Routes communes à vérifier :**
- Formation catalog : `app_formations_index` (pas `app_formations`)
- Contact page : `app_contact_index` (pas `app_contact`)
- Services page : `app_services_index` (pas `app_services`)

#### Erreur de syntaxe Twig "?? operator"
**Causes possibles :**
- Utilisation de l'opérateur `??` (PHP) au lieu de la syntaxe Twig
- Confusion entre syntaxe PHP et Twig
- Opérateurs non supportés dans Twig

**Solutions :**
1. Remplacer `??` par `|default` : `{{ variable ?? 'default' }}` → `{{ variable|default('default') }}`
2. Utiliser l'opérateur ternaire Twig : `{{ variable ?: 'default' }}`
3. Utiliser la condition `if` : `{% if variable is defined %}{{ variable }}{% else %}default{% endif %}`
4. Vider le cache après correction : `docker compose exec php php bin/console cache:clear`

**Exemples de correction :**
```twig
{# ❌ Incorrect (PHP syntax) #}
{{ document.content ?? 'Pas de contenu' }}

{# ✅ Correct (Twig syntax) #}
{{ document.content|default('Pas de contenu') }}
{{ document.content ?: 'Pas de contenu' }}
```

#### Erreur de structure de blocs Twig
**Causes possibles :**
- Bloc `{% endblock %}` en trop ou manquant
- Blocs mal imbriqués ou fermés prématurément
- Copier-coller de code mal formaté

**Solutions :**
1. Vérifier la correspondance des `{% block %}` et `{% endblock %}`
2. S'assurer qu'il n'y a pas d'`{% endblock %}` orphelin au milieu du template
3. Utiliser l'indentation pour visualiser la structure des blocs
4. Vérifier que chaque `{% block name %}` a son `{% endblock %}` correspondant

**Structure type d'un template :**
```twig
{% extends 'base.html.twig' %}

{% block title %}Titre{% endblock %}

{% block body %}
    <!-- Tout le contenu ici -->
{% endblock %}
```

#### Erreur "Variable does not exist"
**Causes possibles :**
- Variable non passée par le contrôleur au template
- Nom de variable incorrect dans le template
- Contrôleur et template non synchronisés

**Solutions :**
1. Vérifier que le contrôleur passe toutes les variables nécessaires
2. Vérifier l'orthographe des noms de variables
3. Utiliser des valeurs par défaut : `{{ variable|default('') }}`
4. Vérifier la correspondance entre contrôleur et template

**Exemple de correction :**
```php
// ❌ Contrôleur ne passe pas 'filters'
return $this->render('template.html.twig', [
    'data' => $data
]);

// ✅ Contrôleur passe toutes les variables nécessaires
return $this->render('template.html.twig', [
    'data' => $data,
    'filters' => $filters
]);
```

### 🔧 Commandes utiles

#### Vérifier les routes
```bash
docker compose exec php php bin/console debug:router | grep legal
```

#### Lister les documents en base
```bash
docker compose exec php php bin/console doctrine:query:sql "SELECT type, title, version, published_at FROM legal_documents ORDER BY type, version"
```

#### Nettoyer le cache
```bash
docker compose exec php php bin/console cache:clear
```

#### Recharger les fixtures
```bash
docker compose exec php php bin/console doctrine:fixtures:load --no-interaction
```

---

## Annexes

### 📋 Checklist de conformité Qualiopi 3.9

Pour être conforme au critère Qualiopi 3.9, vérifier que les documents suivants sont publiés :

- [ ] ✅ Règlement intérieur (au moins une version)
- [ ] ✅ Livret d'accueil stagiaire (version courante)
- [ ] ✅ Conditions générales de formation (version en vigueur)
- [ ] ✅ Politique d'accessibilité (document à jour)

Documents recommandés :
- [ ] 📖 Procédures d'accessibilité
- [ ] ❓ FAQ Accessibilité

### 🔗 Liens de référence

- **Référentiel Qualiopi** : Critère 3.9 - Information des publics bénéficiaires
- **Code de l'éducation** : Articles sur l'information des stagiaires
- **Loi handicap 2005** : Obligations d'accessibilité

---

*Documentation générée le {{ "now"|date("d/m/Y à H:i") }} - Version 1.0*
