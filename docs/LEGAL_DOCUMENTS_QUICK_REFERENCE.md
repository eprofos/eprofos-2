# Quick Reference - Legal Documents URLs & Access

## 🔗 Admin URLs (Authentication Required)

| Document Type | URL | Menu Path |
|---------------|-----|-----------|
| **Dashboard** | `/admin/legal-documents/` | Sidebar → Documents légaux → Tous les documents |
| **Règlements intérieurs** | `/admin/legal-documents/reglements-interieurs` | Sidebar → Documents légaux → Règlements intérieurs |
| **Livrets d'accueil** | `/admin/legal-documents/livrets-accueil` | Sidebar → Documents légaux → Livrets d'accueil |
| **Conditions formation** | `/admin/legal-documents/conditions-formation` | Sidebar → Documents légaux → Conditions de formation |
| **Politique accessibilité** | `/admin/legal-documents/politique-accessibilite` | Sidebar → Documents légaux → Politiques accessibilité |
| **Procédures accessibilité** | `/admin/legal-documents/procedures-accessibilite` | Sidebar → Documents légaux → Procédures accessibilité |
| **FAQ Accessibilité** | `/admin/legal-documents/faq-accessibilite` | Sidebar → Documents légaux → FAQ Accessibilité |

### Admin Actions
| Action | URL Pattern | Method | Required |
|--------|-------------|--------|----------|
| View Document | `/admin/legal-documents/{id}` | GET | Document exists |
| New Document | `/admin/legal-documents/new` | GET/POST | Admin role |
| New with Type | `/admin/legal-documents/new?type={type}` | GET/POST | Valid type |
| Edit Document | `/admin/legal-documents/{id}/edit` | GET/POST | Document exists |
| Toggle Publish | `/admin/legal-documents/{id}/toggle-publish` | POST | CSRF token |
| Delete Document | `/admin/legal-documents/{id}` | POST | CSRF token |

---

## 🌐 Public URLs (No Authentication)

| Document Type | URL | Access Condition |
|---------------|-----|------------------|
| **Legal Notices** | `/mentions-legales` | Always accessible |
| **Privacy Policy** | `/politique-de-confidentialite` | Always accessible |
| **Terms of Service** | `/conditions-generales` | Always accessible |
| **Cookies Policy** | `/politique-cookies` | Always accessible |

### Notes
- Legal documents managed by the LegalDocument entity are now **admin-only**
- Public access to training documents has been removed
- All legal documents are accessible only through the admin interface

---

## ✅ Publication Conditions

A document is **publicly accessible** when ALL conditions are met:

1. ✅ `isActive = true`
2. ✅ `publishedAt IS NOT NULL` 
3. ✅ `publishedAt <= NOW()`

**Result**: Document appears on public pages and in latest version selection.

---

## 🎯 Valid Document Types

```php
// Use these exact values
'internal_regulation'     // Règlement intérieur
'student_handbook'        // Livret d'accueil stagiaire  
'training_terms'          // Conditions générales de formation
'accessibility_policy'    // Politique d'accessibilité
'accessibility_procedures' // Procédures d'accessibilité
'accessibility_faq'       // FAQ Accessibilité
```

---

## 🚨 Error Conditions

| HTTP Code | Condition | Message |
|-----------|-----------|---------|
| **404** | No published document of type | "{Document type} non disponible" |
| **404** | Invalid token | "Token invalide ou expiré" |
| **403** | Admin access without role | Access denied |
| **401** | Admin access without login | Redirect to login |

---

## 🛠️ Quick Admin Tasks

### Publish a Document
1. Go to document detail page: `/admin/legal-documents/{id}`
2. Click "Publier" button (if draft)
3. Confirms with flash message

### Create New Document with Pre-filled Type
```
/admin/legal-documents/new?type=internal_regulation
/admin/legal-documents/new?type=student_handbook
/admin/legal-documents/new?type=training_terms
/admin/legal-documents/new?type=accessibility_policy
/admin/legal-documents/new?type=accessibility_procedures
/admin/legal-documents/new?type=accessibility_faq
```

### Check Document Statistics
- Dashboard: `/admin/legal-documents/`
- Type-specific: `/admin/legal-documents/{type-slug}`

---

## 📊 Database Quick Checks

### List all documents with status
```sql
SELECT type, title, version, 
       CASE WHEN published_at IS NOT NULL THEN 'Published' ELSE 'Draft' END as status,
       is_active
FROM legal_documents 
ORDER BY type, version DESC;
```

### Check published documents count by type
```sql
SELECT type, COUNT(*) as count
FROM legal_documents 
WHERE published_at IS NOT NULL AND published_at <= NOW() AND is_active = true
GROUP BY type;
```

### Find latest published version per type
```sql
SELECT DISTINCT ON (type) type, title, version, published_at
FROM legal_documents 
WHERE published_at IS NOT NULL AND published_at <= NOW() AND is_active = true
ORDER BY type, version DESC, published_at DESC;
```

---

*Quick Reference Card - Updated {{ "now"|date("d/m/Y") }}*
