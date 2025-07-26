# EPROFOS AI Coding Agent Instructions

## Project Overview
EPROFOS is a Symfony 7.3/PHP 8.4 Learning Management System (LMS) for professional training with public catalog, admin interface, and CRM features. Built with Docker/FrankenPHP, PostgreSQL, Bootstrap 5, and Stimulus for interactivity. **Critical**: Must satisfy Qualiopi certification requirements for French training quality standards.

**Core Technologies**: Symfony Asset Mapper (not Webpack), KnpSnappyBundle for PDFs, PHPOffice for documents, Doctrine with extensive fixtures, PostgreSQL with advanced constraints.

## Essential Architecture

### Core Entity Relationships
- `Training\Formation` (training courses) → `Training\Category` (technical/transversal)
- `Formation` → `Module` → `Chapter` → `Course` → `Exercise`/`QCM` (5-level pedagogical hierarchy)
- `Service` → `ServiceCategory` (conseil, accompagnement, certifications, sur-mesure)
- `ContactRequest` with specialized forms (quote, advice, info, quick registration)
- `NeedsAnalysisRequest` with company/individual variants + token-based security
- `Prospect` → `ProspectNote` (CRM system for lead management)
- `User` → `Student` (authentication and enrollment system)
- `Document` system with versioning, metadata, UI components, and templates
- `Questionnaire` → `Question` → `QuestionOption` with response tracking
- `AttendanceRecord` for session tracking and Qualiopi compliance

### Hierarchical Program Structure
**Critical Pattern**: Training content uses a 5-level hierarchy:
- **Formation**: Overall training course with Qualiopi-compliant fields
- **Module**: Learning modules within formations (2-5 per formation)
- **Chapter**: Detailed content within modules (3-8 per module)
- **Course**: Individual course sessions within chapters
- **Exercise/QCM**: Interactive exercises and assessments within courses

Each level has dedicated fields for learning objectives, evaluation methods, and success criteria to meet Qualiopi requirements.

### Controller Structure
```
src/Controller/
├── Public/     # Public-facing controllers (FormationController, ContactController, etc.)
├── Admin/      # Admin interface controllers
└── Student/    # Student dashboard controllers
```

**Key Pattern**: Public controllers use repository methods like `findCategoriesWithActiveFormations()`, `createCatalogQueryBuilder()` for filtering.

### Database & Migrations
- PostgreSQL with comprehensive constraints and indexes
- Migrations in `migrations/Version*.php` - run with `doctrine:migrations:migrate`
- Current migration: `Version20250721094647.php` with full schema
- Fixtures load realistic test data: `doctrine:fixtures:load`
- 30+ specialized fixture files with dependency management via `AppFixtures`

### Frontend Architecture
- **Asset Mapper** (not Webpack Encore) - use `importmap.php` for dependencies
- **Stimulus Controllers**: Follow pattern in `assets/controllers/formation_filter_controller.js`
- **Bootstrap 5** + FontAwesome + Tabler CSS framework
- **Turbo** integration for SPA-like navigation

## Development Workflow

### Essential Commands
```bash
# Start development environment
docker compose up --wait

# Database operations
docker compose exec php php bin/console doctrine:migrations:migrate
docker compose exec php php bin/console doctrine:fixtures:load

# Clear cache
docker compose exec php php bin/console cache:clear
```

### File Upload Pattern
- Images stored in `public/uploads/` with parameter configuration
- Entity methods like `getImagePath()` handle file references
- Use `{{ asset('uploads/formations/' ~ formation.image) }}` in templates

## Project-Specific Conventions

### Ajax Filtering Pattern
**Critical**: Controllers return different responses based on `$request->isXmlHttpRequest()`:
- Regular requests: full template
- Ajax requests: partial template (e.g., `_formations_list.html.twig`)

Example implementation:
```php
if ($request->isXmlHttpRequest()) {
    return $this->render('public/formation/_formations_list.html.twig', [
        'formations' => $formations,
        'current_page' => $page,
        'total_pages' => $totalPages,
    ]);
}
```

### Program Content Generation
**Critical**: Formation program content is automatically generated from Module and Chapter hierarchy:
- Use `$formation->getGeneratedProgram()` to build program from modules/chapters
- Never manually maintain program text - always derive from structure
- Each module shows learning objectives and active chapters with durations

### Qualiopi Compliance Fields
Each entity (Formation, Module, Chapter) includes structured fields for:
- `operationalObjectives` / `learningObjectives` (JSON arrays)
- `evaluableObjectives` / `evaluationCriteria` (JSON arrays)  
- `evaluationMethods` / `assessmentMethods` (TEXT)
- `teachingMethods` (TEXT)
- `successIndicators` / `successCriteria` (JSON arrays)
- `targetAudience` / `accessModalities` / `handicapAccessibility` (TEXT)

These fields are required for French training quality certification.

### Stimulus Controller Patterns
- Target naming: `data-formation-filter-target="search"`
- Value passing: `data-formation-filter-url-value="{{ path('route') }}"`
- Event dispatching: `this.dispatch('resultsUpdated', { detail: data })`
- Debounced search with `setTimeout()` for performance
- **Modal Integration**: When creating modals in Twig, use `modal_controller.js` with `data-controller="modal"`, `data-modal-modal-id-value="modal-id"`, and actions `click->modal#open`/`click->modal#close`

### Collection Form Pattern
Use dedicated `CollectionController` for dynamic form fields:
```js
addItem(event) {
    const prototype = this.prototypeTarget.dataset.prototype
    const newItem = prototype.replace(new RegExp(this.prototypeNameValue, 'g'), this.indexValue)
    // Add to collection with remove button
}
```

### CRM Prospect Management Pattern
**Critical**: Prospects are automatically created from ContactRequest, SessionRegistration, and NeedsAnalysisRequest:
- `Prospect` entity tracks 7 statuses: new, contacted, qualified, proposal_sent, negotiation, converted, lost
- `ProspectNote` supports 6 types: note, call, email, meeting, task, reminder
- Use `ProspectService` for automatic prospect creation and deduplication
- Admin interface provides full CRM functionality with filtering and assignment

### Needs Analysis Pattern (Qualiopi 2.4)
**Critical**: Token-based secure forms for collecting training needs:
- `NeedsAnalysisRequest` with unique UUID tokens for security
- Company and Individual variants with specialized forms
- Email-based invitation system with expiration dates
- Admin can generate analysis links for prospects
- Comprehensive data collection for compliance reporting

### Repository Query Methods
Use descriptive method names like:
- `findCategoriesWithActiveFormations()`
- `createCatalogQueryBuilder($filters)`
- `findBySlugWithCategory($slug)`

### Template Organization
```
templates/
├── components/     # Reusable UI components
├── public/        # Public-facing templates
├── admin/         # Admin interface templates
└── emails/        # Email templates
```

## Integration Points

### Database Fixtures
- Load in dependency order via `getDependencies()` method
- Use Faker for realistic test data
- Reference constants like `Formation::LEVELS` for consistent data
- `AppFixtures` orchestrates all fixtures - acts as dependency coordinator
- Context-aware data generation (chapter titles match module topics)
- Comprehensive entity coverage: Training, CRM, Documents, Users, Services
- **30+ specialized fixture files**: Each entity has dedicated fixtures with realistic data
- Run with: `docker compose exec php php bin/console doctrine:fixtures:load`

### Asset Management
- Asset Mapper configuration in `importmap.php`
- Public entrypoint: `assets/public.js`
- Private/admin entrypoint: `assets/private.js`
- CSS imports: Bootstrap, FontAwesome, Tabler, custom styles

### Email System
- Templates in `templates/emails/`
- Symfony Mailer configuration in `config/packages/mailer.yaml`
- Email sending in specialized service classes

### Service Architecture
**Critical**: Comprehensive service layer for business logic:
- `ProspectManagementService` - Automated prospect creation and CRM workflows
- `NeedsAnalysisService` - Token-based analysis request management
- `DocumentService` - Document generation and management workflows
- `QualiopiValidationService` - Training quality compliance validation
- `DurationCalculationService` - Training duration management
- `DropoutPreventionService` - Student retention analytics
- `AuditLogService` - Compliance and change tracking
- Services handle complex business rules and cross-entity operations

### Document Generation System
**Critical**: PHPOffice integration for document generation:
- PHPWord and PHPSpreadsheet for programmatic document creation
- Document templates with metadata and versioning
- UI components for dynamic document layouts
- Export functionality for training materials and reports
- KnpSnappyBundle integration for PDF generation
- Comprehensive Document entity system: Document, DocumentCategory, DocumentMetadata, DocumentTemplate, DocumentType, DocumentUIComponent, DocumentUITemplate, DocumentVersion

## Key Files to Understand
- `src/Entity/Training/Formation.php` - Core training entity with validation & auto-generated program
- `src/Entity/Training/Module.php` - Learning module with Qualiopi fields
- `src/Entity/Training/Chapter.php` - Detailed chapter content structure
- `src/Entity/Prospect.php` - CRM prospect management with status tracking
- `src/Entity/NeedsAnalysisRequest.php` - Qualiopi 2.4 compliance for needs analysis
- `src/Controller/Public/FormationController.php` - Ajax filtering implementation
- `src/Controller/Admin/ProspectController.php` - CRM prospect management interface
- `assets/controllers/formation_filter_controller.js` - Frontend filtering with debounce
- `templates/base.html.twig` - Main layout structure
- `src/DataFixtures/AppFixtures.php` - Test data orchestration
- `importmap.php` - Asset management configuration
- `migrations/Version20250721094647.php` - Latest database schema
- `docs/IMPLEMENTATION_SUMMARY.md` - Complete implementation overview

## Testing & Quality
- PHPUnit tests in `tests/` directory
- Run with `docker compose exec php php bin/phpunit`
- Use fixtures for test data consistency
- Follow PSR standards and Symfony conventions
