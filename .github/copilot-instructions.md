# EPROFOS AI Coding Agent Instructions

## Project Overview
EPROFOS is a Symfony 7.3/PHP 8.3 Learning Management System (LMS) for professional training with public catalog and admin interface. Built with Docker/FrankenPHP, PostgreSQL, Bootstrap 5, and Stimulus for interactivity. **Critical**: Must satisfy Qualiopi certification requirements for French training quality standards.

## Essential Architecture

### Core Entity Relationships
- `Formation` (training courses) → `Category` (technical/transversal)
- `Formation` → `Module` → `Chapter` (3-level pedagogical hierarchy)
- `Service` → `ServiceCategory` (conseil, accompagnement, certifications, sur-mesure)
- `ContactRequest` with specialized forms (quote, advice, info, quick registration)
- `NeedsAnalysisRequest` with company/individual variants

### Hierarchical Program Structure
**Critical Pattern**: Training content uses a 3-level hierarchy:
- **Formation**: Overall training course with Qualiopi-compliant fields
- **Module**: Learning modules within formations (2-5 per formation)
- **Chapter**: Detailed content within modules (3-8 per module)

Each level has dedicated fields for learning objectives, evaluation methods, and success criteria to meet Qualiopi requirements.

### Controller Structure
```
src/Controller/
├── Public/     # Public-facing controllers (FormationController, ContactController, etc.)
├── Admin/      # Admin interface controllers
```

**Key Pattern**: Public controllers use repository methods like `findCategoriesWithActiveFormations()`, `createCatalogQueryBuilder()` for filtering.

### Database & Migrations
- PostgreSQL with comprehensive constraints and indexes
- Migrations in `migrations/Version*.php` - run with `doctrine:migrations:migrate`
- Fixtures load realistic test data: `doctrine:fixtures:load`

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
- `evaluationMethods` / `assessmentMethods` (TEXT)
- `teachingMethods` (TEXT)
- `successCriteria` (JSON arrays)
- `resources` (JSON arrays)

These fields are required for French training quality certification.

### Stimulus Controller Patterns
- Target naming: `data-formation-filter-target="search"`
- Value passing: `data-formation-filter-url-value="{{ path('route') }}"`
- Event dispatching: `this.dispatch('resultsUpdated', { detail: data })`
- Debounced search with `setTimeout()` for performance

### Collection Form Pattern
Use dedicated `CollectionController` for dynamic form fields:
```js
addItem(event) {
    const prototype = this.prototypeTarget.dataset.prototype
    const newItem = prototype.replace(new RegExp(this.prototypeNameValue, 'g'), this.indexValue)
    // Add to collection with remove button
}
```

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

### Asset Management
- Asset Mapper configuration in `importmap.php`
- Public entrypoint: `assets/public.js`
- Private/admin entrypoint: `assets/private.js`
- CSS imports: Bootstrap, FontAwesome, Tabler, custom styles

### Email System
- Templates in `templates/emails/`
- Symfony Mailer configuration in `config/packages/mailer.yaml`
- Email sending in specialized service classes

## Key Files to Understand
- `src/Entity/Formation.php` - Core training entity with validation & auto-generated program
- `src/Entity/Module.php` - Learning module with Qualiopi fields
- `src/Entity/Chapter.php` - Detailed chapter content structure
- `src/Controller/Public/FormationController.php` - Ajax filtering implementation
- `assets/controllers/formation_filter_controller.js` - Frontend filtering with debounce
- `templates/base.html.twig` - Main layout structure
- `src/DataFixtures/AppFixtures.php` - Test data orchestration
- `importmap.php` - Asset management configuration

## Testing & Quality
- PHPUnit tests in `tests/` directory
- Run with `docker compose exec php php bin/phpunit`
- Use fixtures for test data consistency
- Follow PSR standards and Symfony conventions
