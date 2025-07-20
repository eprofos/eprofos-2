# Technical Implementation Guide - Legal Documents System

## 🏗️ Architecture Overview

The legal documents system is built around a single `LegalDocument` entity with type-based differentiation, providing both admin management and public access with dedicated URLs for each document type.

### Core Components

```
src/
├── Entity/LegalDocument.php              # Main entity with type constants
├── Repository/LegalDocumentRepository.php # Data access with filtering
├── Controller/
│   ├── Admin/LegalDocumentController.php  # Admin CRUD + type pages
│   └── Public/LegalController.php         # Public document display
├── Form/LegalDocumentType.php             # Admin form handling
└── Service/AnalysisEmailNotificationService.php   # Document delivery notifications
```

---

## 📝 Entity Structure

### LegalDocument Entity

```php
class LegalDocument
{
    // Type Constants - Define available document types
    public const TYPE_INTERNAL_REGULATION = 'internal_regulation';
    public const TYPE_STUDENT_HANDBOOK = 'student_handbook';
    public const TYPE_TRAINING_TERMS = 'training_terms';
    public const TYPE_ACCESSIBILITY_POLICY = 'accessibility_policy';
    public const TYPE_ACCESSIBILITY_PROCEDURES = 'accessibility_procedures';
    public const TYPE_ACCESSIBILITY_FAQ = 'accessibility_faq';

    public const TYPES = [
        self::TYPE_INTERNAL_REGULATION => 'Règlement intérieur',
        self::TYPE_STUDENT_HANDBOOK => 'Livret d\'accueil stagiaire',
        self::TYPE_TRAINING_TERMS => 'Conditions de formation',
        self::TYPE_ACCESSIBILITY_POLICY => 'Politique d\'accessibilité',
        self::TYPE_ACCESSIBILITY_PROCEDURES => 'Procédures d\'accessibilité',
        self::TYPE_ACCESSIBILITY_FAQ => 'FAQ Accessibilité',
    ];

    // Key fields for publication logic
    private ?string $type = null;           // Document type (required)
    private ?string $title = null;          // Document title
    private ?string $version = null;        // Version number/string
    private ?string $content = null;        // HTML content
    private ?string $filePath = null;       // Optional PDF file
    private bool $isActive = true;          // Active/inactive flag
    private ?\DateTimeImmutable $publishedAt = null; // Publication timestamp
    private ?array $metadata = null;        // Additional metadata (JSON)
}
```

### Key Methods

```php
// Publication status
public function isPublished(): bool
public function publish(): void
public function unpublish(): void

// File handling
public function hasFile(): bool
public function getFileUrl(): ?string
public function getAbsoluteFilePath(): ?string

// Static utilities
public static function getValidTypes(): array
```

---

## 🔍 Repository Patterns

### Key Query Methods

```php
class LegalDocumentRepository
{
    // Find latest published document of specific type
    public function findLatestPublishedByType(string $type): ?LegalDocument
    
    // Find all published documents of specific type
    public function findPublishedByType(string $type): array
    
    // Get all published documents (for downloads)
    public function findAllPublished(): array
    
    // Admin filtering with QueryBuilder
    public function createAdminQueryBuilder(array $filters = []): QueryBuilder
    
    // Statistics for dashboard
    public function getStatistics(): array
    public function getTypeStatistics(string $type): array
}
```

### Publication Logic

```sql
-- A document is "published" when:
WHERE isActive = true 
  AND publishedAt IS NOT NULL 
  AND publishedAt <= NOW()

-- Version selection (latest first):
ORDER BY version DESC, publishedAt DESC
```

---

## 🎛️ Controller Architecture

### Admin Controller Structure

```php
class LegalDocumentController
{
    // Dashboard - overview of all types
    #[Route('/', name: 'index')]
    public function index(): Response
    
    // Individual type pages with dedicated URLs
    #[Route('/reglements-interieurs', name: 'internal_regulation')]
    public function internalRegulation(): Response
    
    #[Route('/livrets-accueil', name: 'student_handbook')]
    public function studentHandbook(): Response
    
    // ... other type-specific methods
    
    // Helper method for type pages
    private function renderDocumentTypePage(string $type, string $title): Response
    
    // Standard CRUD operations
    public function show(LegalDocument $document): Response
    public function new(Request $request): Response  // Supports ?type= pre-filling
    public function edit(Request $request, LegalDocument $document): Response
    public function delete(Request $request, LegalDocument $document): Response
    public function togglePublish(Request $request, LegalDocument $document): Response
}
```

### Public Controller Structure

```php
class LegalController
{
    // Hub page with document overview
    #[Route('/informations-stagiaires', name: 'app_legal_student_information')]
    public function studentInformation(): Response
    
    // Individual document pages with SEO-friendly URLs
    #[Route('/reglement-interieur', name: 'app_legal_internal_regulation')]
    public function internalRegulation(): Response
    
    #[Route('/livret-accueil-stagiaire', name: 'app_legal_student_handbook')]
    public function studentHandbook(): Response
    
    // ... other document-specific methods
    
    // Legacy/utility endpoints
    #[Route('/documents/{type}', name: 'app_legal_document_view')]
    public function documentView(string $type): Response
    
    #[Route('/documents-telechargement', name: 'app_legal_documents_download')]
    public function documentsDownload(): Response
}
```

---

## 🎨 Template Structure

### Admin Templates

```
templates/admin/legal_document/
├── index.html.twig           # Dashboard with statistics
├── type_page.html.twig       # Individual type management
├── show.html.twig           # Document detail view
├── new.html.twig            # Creation form
├── edit.html.twig           # Edit form
└── _document_card.html.twig # Reusable document card
```

### Public Templates

```
templates/public/legal/
├── student_information.html.twig  # Hub page
├── document_display.html.twig     # Individual document display
├── accessibility.html.twig        # Legacy accessibility hub
├── documents_download.html.twig   # Download center
└── document_acknowledgment.html.twig # Receipt confirmation
```

### Template Data Patterns

```php
// Admin type page data
[
    'documents' => $documents,           // Filtered documents
    'filters' => $filters,               // Current filter values
    'type' => $type,                     // Document type constant
    'type_title' => $title,              // Human-readable title
    'type_statistics' => $statistics,    // Type-specific stats
]

// Public document display data
[
    'document' => $document,             // Latest published document
    'document_type' => $type,            // For navigation highlighting
    'page_title' => $title,              // SEO title
]
```

---

## 🔒 Security Implementation

### Access Control

```php
// Admin routes protection
#[Route('/admin/legal-documents', name: 'admin_legal_document_')]
#[IsGranted('ROLE_ADMIN')]
class LegalDocumentController extends AbstractController

// CSRF protection for state-changing operations
public function togglePublish(Request $request, LegalDocument $document): Response
{
    if ($this->isCsrfTokenValid('publish'.$document->getId(), $request->getPayload()->get('_token'))) {
        // Process publication toggle
    }
}
```

### Publication Validation

```php
// Repository method ensures only published documents are returned
public function findLatestPublishedByType(string $type): ?LegalDocument
{
    return $this->createQueryBuilder('ld')
        ->where('ld.type = :type')
        ->andWhere('ld.isActive = :active')
        ->andWhere('ld.publishedAt IS NOT NULL')
        ->andWhere('ld.publishedAt <= :now')  // Critical: prevent future publication
        ->setParameter('type', $type)
        ->setParameter('active', true)
        ->setParameter('now', new \DateTime())
        ->orderBy('ld.version', 'DESC')
        ->orderBy('ld.publishedAt', 'DESC')
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
}
```

---

## 📁 File Management

### Upload Handling

```php
private function handleFileUpload($file): ?string
{
    if (!$file) return null;
    
    $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
    $safeFilename = $this->slugger->slug($originalFilename);
    $fileName = $safeFilename.'-'.uniqid().'.'.$file->guessExtension();
    
    $uploadDir = $this->getParameter('kernel.project_dir').'/public/uploads/legal';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $file->move($uploadDir, $fileName);
    return $fileName;
}
```

### File Access Methods

```php
// Entity methods for file handling
public function hasFile(): bool
{
    return !empty($this->filePath);
}

public function getFileUrl(): ?string
{
    if (!$this->hasFile()) return null;
    return '/uploads/legal/' . $this->filePath;
}

public function getAbsoluteFilePath(): ?string
{
    if (!$this->hasFile()) return null;
    return $_ENV['KERNEL_PROJECT_DIR'] . '/public/uploads/legal/' . $this->filePath;
}
```

---

## 📊 Statistics & Analytics

### Statistics Calculation

```php
public function getStatistics(): array
{
    // Global statistics for dashboard
    return [
        'total' => $totalDocuments,
        'published' => $publishedDocuments,
        'drafts' => $draftDocuments,
        'types' => $this->countByTypes(),  // Count per document type
    ];
}

public function getTypeStatistics(string $type): array
{
    // Type-specific statistics for individual pages
    return [
        'type' => $type,
        'total' => $totalForType,
        'published' => $publishedForType,
        'drafts' => $draftsForType,
        'latest_published' => $latestPublishedDocument,
    ];
}
```

---

## 🔗 URL Generation Patterns

### Route Naming Convention

```php
// Admin routes: admin_legal_document_{action/type}
'admin_legal_document_index'                 // Dashboard
'admin_legal_document_internal_regulation'   // Type page
'admin_legal_document_show'                  // Document detail
'admin_legal_document_new'                   // Create form
'admin_legal_document_edit'                  // Edit form

// Public routes: app_legal_{type/action}
'app_legal_student_information'              // Hub page
'app_legal_internal_regulation'              // Document display
'app_legal_documents_download'               // Download center
```

### Dynamic URL Generation

```php
// Pre-fill form with document type
$this->generateUrl('admin_legal_document_new', ['type' => 'internal_regulation']);

// Link to type-specific management
$this->generateUrl('admin_legal_document_internal_regulation');

// Public document access
$this->generateUrl('app_legal_internal_regulation');
```

---

## 🧪 Testing Patterns

### Repository Testing

```php
public function testFindLatestPublishedByType(): void
{
    // Test published document retrieval
    $document = $this->repository->findLatestPublishedByType('internal_regulation');
    $this->assertNotNull($document);
    $this->assertTrue($document->isPublished());
}

public function testUnpublishedDocumentNotFound(): void
{
    // Test unpublished documents are not returned
    $document = $this->repository->findLatestPublishedByType('nonexistent_type');
    $this->assertNull($document);
}
```

### Controller Testing

```php
public function testPublicDocumentAccess(): void
{
    $this->client->request('GET', '/reglement-interieur');
    $this->assertResponseIsSuccessful();
    $this->assertSelectorTextContains('h1', 'Règlement intérieur');
}

public function testAdminRequiresAuthentication(): void
{
    $this->client->request('GET', '/admin/legal-documents/');
    $this->assertResponseRedirects(); // Should redirect to login
}
```

---

## 🔧 Configuration & Setup

### Required Parameters

```yaml
# config/services.yaml
parameters:
    legal_documents_upload_dir: '%kernel.project_dir%/public/uploads/legal'
```

### Database Migration

```php
// Migration creates legal_documents table with proper indexes
$table = $schema->createTable('legal_documents');
$table->addColumn('id', 'integer', ['autoincrement' => true]);
$table->addColumn('type', 'string', ['length' => 100]);
$table->addColumn('title', 'string', ['length' => 255]);
$table->addColumn('version', 'string', ['length' => 50]);
$table->addColumn('content', 'text', ['notnull' => false]);
$table->addColumn('file_path', 'string', ['length' => 255, 'notnull' => false]);
$table->addColumn('is_active', 'boolean', ['default' => true]);
$table->addColumn('published_at', 'datetime_immutable', ['notnull' => false]);
$table->addColumn('metadata', 'json', ['notnull' => false]);
$table->addColumn('created_at', 'datetime_immutable');
$table->addColumn('updated_at', 'datetime_immutable');

// Indexes for performance
$table->addIndex(['type'], 'idx_legal_document_type');
$table->addIndex(['published_at'], 'idx_legal_document_published');
$table->addIndex(['type', 'published_at'], 'idx_legal_document_type_published');
```

---

## 🚀 Performance Considerations

### Query Optimization

```php
// Use specific indexes for common queries
->andWhere('ld.type = :type')           // Uses idx_legal_document_type
->andWhere('ld.publishedAt <= :now')    // Uses idx_legal_document_published
->orderBy('ld.version', 'DESC')         // Consider adding version index if many versions
```

### Caching Strategies

```php
// Consider caching latest published documents
$cacheKey = 'legal_document_' . $type . '_latest';
if ($document = $cache->get($cacheKey)) {
    return $document;
}

$document = $this->repository->findLatestPublishedByType($type);
$cache->set($cacheKey, $document, 3600); // Cache for 1 hour
```

---

## 📈 Monitoring & Maintenance

### Health Checks

```php
// Verify all required document types have published versions
$requiredTypes = ['internal_regulation', 'student_handbook', 'training_terms', 'accessibility_policy'];
foreach ($requiredTypes as $type) {
    $document = $this->repository->findLatestPublishedByType($type);
    if (!$document) {
        $this->logger->warning('Missing required document type: ' . $type);
    }
}
```

### Cleanup Tasks

```php
// Remove orphaned files (files without corresponding database records)
$uploadDir = $this->getParameter('legal_documents_upload_dir');
$databaseFiles = $this->repository->getAllFilePaths();
$filesystemFiles = glob($uploadDir . '/*');

foreach ($filesystemFiles as $file) {
    if (!in_array(basename($file), $databaseFiles)) {
        unlink($file); // Remove orphaned file
    }
}
```

---

*Technical Guide - Version 1.0 - {{ "now"|date("d/m/Y") }}*
