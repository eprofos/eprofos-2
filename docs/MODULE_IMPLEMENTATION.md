# Module Implementation Documentation

## Overview
This document describes the implementation of the Module entity and its management system in the EPROFOS platform. The Module system is designed to create a hierarchical structure for training content: Formation → Module → Chapter → Course/Exercise/QCM.

## Entity Structure

### Module Entity
The Module entity represents a learning module within a formation and includes:

**Core Properties:**
- `id`: Unique identifier
- `title`: Module title
- `slug`: URL-friendly identifier
- `description`: Module description
- `durationHours`: Duration in hours
- `orderIndex`: Position within the formation
- `isActive`: Status flag
- `createdAt`/`updatedAt`: Timestamps

**Qualiopi-Compliant Properties:**
- `learningObjectives`: Specific learning objectives for the module
- `prerequisites`: Knowledge/skills required before starting
- `evaluationMethods`: How learning outcomes are assessed
- `teachingMethods`: Pedagogical approaches used
- `resources`: Educational resources and materials
- `successCriteria`: Measurable success indicators

**Relationships:**
- `formation`: Many-to-One relationship with Formation
- `chapters`: One-to-Many relationship with Chapter (ordered by orderIndex)

### Chapter Entity (Basic Structure)
A basic Chapter entity has been created to support the Module structure:
- `id`, `title`, `slug`, `description`
- `durationMinutes`: Chapter duration in minutes
- `orderIndex`: Position within the module
- `isActive`: Status flag
- `module`: Many-to-One relationship with Module

## Repository Features

### ModuleRepository
- `findByFormationOrdered()`: Get modules for a formation in order
- `findActiveByFormation()`: Get active modules for a formation
- `findBySlugWithFormation()`: Find module by slug with formation data
- `findWithChapterCount()`: Get modules with chapter count
- `getNextOrderIndex()`: Get next available order index
- `updateOrderIndexes()`: Update module order positions

## Admin Interface

### ModuleController
Full CRUD operations for modules:
- **Index**: List modules (optionally filtered by formation)
- **New**: Create new module
- **Show**: Display module details
- **Edit**: Modify existing module
- **Delete**: Remove module
- **Toggle Active**: Activate/deactivate module
- **Reorder**: Change module order within formation

### Form System
**ModuleType Form** includes:
- Basic information (title, slug, description)
- Formation selection
- Duration and order settings
- Qualiopi-compliant fields (objectives, evaluation methods, etc.)
- Collection fields for arrays (objectives, resources, success criteria)

### Templates
- **index.html.twig**: Module listing with formation filtering
- **new.html.twig**: Module creation form
- **show.html.twig**: Module details display
- **edit.html.twig**: Module editing form
- Support for drag-and-drop reordering when filtering by formation

## Data Fixtures

### ModuleFixtures
Generates realistic module data:
- Context-aware module titles based on formation type
- Randomized but relevant learning objectives
- Appropriate evaluation and teaching methods
- Realistic resources and success criteria
- Proper order indexing within formations

## Navigation Integration

The admin sidebar has been updated to include:
- "Modules" link in the Formations dropdown
- "Ajouter un module" quick action
- Proper separation with dividers

## Database Migration

A migration file has been created to:
- Create the `module` table with all necessary columns
- Create the `chapter` table (basic structure)
- Set up foreign key relationships
- Add proper indexes for performance

## Qualiopi Compliance

The Module entity includes all fields required by Qualiopi 2.5:
- **Learning Objectives**: Specific, measurable objectives
- **Prerequisites**: Required knowledge/skills
- **Evaluation Methods**: Assessment approaches
- **Teaching Methods**: Pedagogical techniques
- **Resources**: Educational materials
- **Success Criteria**: Measurable success indicators

## Usage Examples

### Creating a Module
```php
$module = new Module();
$module->setTitle('Module d\'introduction');
$module->setSlug('module-introduction');
$module->setDescription('Module d\'introduction aux concepts fondamentaux');
$module->setDurationHours(8);
$module->setOrderIndex(1);
$module->setFormation($formation);
$module->setLearningObjectives(['Maîtriser les concepts de base', 'Appliquer les techniques']);
$module->setEvaluationMethods('QCM et exercices pratiques');
```

### Querying Modules
```php
// Get all modules for a formation in order
$modules = $moduleRepository->findByFormationOrdered($formationId);

// Get active modules only
$activeModules = $moduleRepository->findActiveByFormation($formationId);

// Get module with chapter count
$modulesWithCount = $moduleRepository->findWithChapterCount();
```

## Next Steps

To complete the hierarchical structure:
1. **Chapter Entity**: Enhance with Qualiopi-compliant fields
2. **Course Entity**: Create for individual courses within chapters
3. **Exercise Entity**: Create for practical exercises
4. **QCM Entity**: Create for multiple-choice questions
5. **Admin Interfaces**: Create management interfaces for all entities
6. **Public Interface**: Create learner-facing interfaces

## File Structure

```
src/
├── Entity/
│   ├── Module.php
│   └── Chapter.php (basic)
├── Repository/
│   ├── ModuleRepository.php
│   └── ChapterRepository.php
├── Controller/Admin/
│   └── ModuleController.php
├── Form/
│   └── ModuleType.php
└── DataFixtures/
    └── ModuleFixtures.php

templates/admin/
└── modules/
    ├── index.html.twig
    ├── new.html.twig
    ├── show.html.twig
    └── edit.html.twig

migrations/
└── Version20250715100000.php
```

This implementation provides a solid foundation for the Module system while maintaining Qualiopi compliance and providing a user-friendly admin interface.
