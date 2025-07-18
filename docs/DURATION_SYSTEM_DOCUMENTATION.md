# Duration Calculation and Synchronization System

## Overview

The EPROFOS platform implements a comprehensive duration calculation and synchronization system that manages learning time across a 4-level hierarchical structure. This system ensures accurate duration tracking and automatic propagation of changes throughout the educational content hierarchy.

## Architecture

### Entity Hierarchy

The duration system operates on a 4-level hierarchy:

```
Formation (hours)
├── Module (hours)
    ├── Chapter (minutes)
        ├── Course (minutes)
            ├── Exercise (estimatedDurationMinutes)
            └── QCM (timeLimitMinutes)
```

### Duration Units

- **Formation**: Hours (stored as `durationHours`)
- **Module**: Hours (stored as `durationHours`)
- **Chapter**: Minutes (stored as `durationMinutes`)
- **Course**: Minutes (stored as `durationMinutes`)
- **Exercise**: Minutes (stored as `estimatedDurationMinutes`)
- **QCM**: Minutes (stored as `timeLimitMinutes`)

### Bottom-Up Calculation Flow

The system calculates durations from the bottom up:

1. **Course Duration** = Base duration + Sum of all active exercises + Sum of all active QCMs
2. **Chapter Duration** = Sum of all active course durations
3. **Module Duration** = Sum of all active chapter durations (converted to hours)
4. **Formation Duration** = Sum of all active module durations

## Core Components

### 1. DurationCalculationService

**Location**: `src/Service/DurationCalculationService.php`

**Purpose**: Core service responsible for all duration calculations, caching, and unit conversions.

**Key Methods**:
- `calculateCourseDuration(Course $course): int` - Calculates total course duration
- `calculateChapterDuration(Chapter $chapter): int` - Calculates total chapter duration
- `calculateModuleDuration(Module $module): int` - Calculates total module duration
- `calculateFormationDuration(Formation $formation): int` - Calculates total formation duration
- `updateEntityDuration(object $entity): void` - Updates stored duration and propagates changes
- `getDurationStatistics(object $entity): array` - Returns duration statistics for analysis

**Features**:
- **Caching**: Uses Symfony Cache for performance optimization (1-hour TTL)
- **Unit Conversion**: Automatic conversion between minutes and hours
- **Logging**: Comprehensive logging for debugging and monitoring
- **Error Handling**: Graceful handling of missing entities and calculation errors

### 2. Entity Listeners

**Purpose**: Automatic duration synchronization when entities are created, updated, or deleted.

**Individual Listeners**:
- `CourseListener` - Handles course changes, updates chapter duration
- `ExerciseListener` - Handles exercise changes, updates course duration
- `QCMListener` - Handles QCM changes, updates course duration
- `ChapterListener` - Handles chapter changes, updates module duration
- `ModuleListener` - Handles module changes, updates formation duration

**Location**: `src/EventListener/`

**Events Handled**:
- `postPersist` - After entity creation
- `postUpdate` - After entity update
- `postRemove` - After entity deletion

### 3. Console Commands

#### Duration Synchronization Command

**Command**: `app:duration:sync`

**Purpose**: Batch synchronization of all duration calculations.

**Usage**:
```bash
# Sync all entities
php bin/console app:duration:sync

# Sync specific entity type
php bin/console app:duration:sync course

# Dry run (preview changes without applying)
php bin/console app:duration:sync --dry-run

# Force update all entities (even if no changes detected)
php bin/console app:duration:sync --force

# Clear cache before synchronization
php bin/console app:duration:sync --clear-cache

# Custom batch size
php bin/console app:duration:sync --batch-size=50
```

**Options**:
- `--dry-run`: Preview changes without applying them
- `--force`: Force update all entities regardless of calculated differences
- `--clear-cache`: Clear all duration caches before synchronization
- `--batch-size`: Number of entities to process per batch (default: 100)
- `--entity-id`: Sync specific entity by ID

**Entity Types**:
- `course` - Sync only courses
- `chapter` - Sync only chapters
- `module` - Sync only modules
- `formation` - Sync only formations
- (no type) - Sync all entities in bottom-up order

### 4. Database Optimization

**Migration**: `Version20250718194811.php`

**Indexes Added**:
- Duration fields for fast filtering and sorting
- Relationship indexes for efficient joins
- Order-based indexes for performance optimization

**Performance Impact**:
- Faster duration queries and calculations
- Improved hierarchical traversal performance
- Optimized batch processing

## Usage Examples

### Basic Duration Calculation

```php
// Inject the service
use App\Service\DurationCalculationService;

// Calculate course duration (including exercises and QCMs)
$courseDuration = $durationService->calculateCourseDuration($course);

// Calculate chapter duration (sum of all courses)
$chapterDuration = $durationService->calculateChapterDuration($chapter);

// Calculate module duration (sum of all chapters, converted to hours)
$moduleDuration = $durationService->calculateModuleDuration($module);

// Calculate formation duration (sum of all modules)
$formationDuration = $durationService->calculateFormationDuration($formation);
```

### Duration Statistics

```php
// Get detailed statistics for any entity
$stats = $durationService->getDurationStatistics($entity);

// Returns array with:
// - entity_type: Class name
// - entity_id: Entity ID
// - calculated_duration: Calculated duration
// - stored_duration: Currently stored duration
// - difference: Difference between calculated and stored
// - needs_update: Boolean indicating if update is needed
// - unit: Duration unit (minutes or hours)
```

### Manual Duration Update

```php
// Update entity duration and propagate changes
$durationService->updateEntityDuration($course);

// This will:
// 1. Calculate new duration
// 2. Update the entity's stored duration
// 3. Propagate changes to parent entities
// 4. Clear relevant caches
```

### Unit Conversions

```php
// Convert minutes to hours (rounded up by default)
$hours = $durationService->minutesToHours(150); // Returns 3

// Convert minutes to hours (normal rounding)
$hours = $durationService->minutesToHours(150, false); // Returns 2

// Convert hours to minutes
$minutes = $durationService->hoursToMinutes(2); // Returns 120
```

## Administrative Tools

### Twig Extensions

**Location**: `src/Twig/DurationExtension.php`

**Available Functions**:
- `format_duration(value, unit)` - Format duration for display
- `minutes_to_hours(minutes, roundUp)` - Convert minutes to hours
- `hours_to_minutes(hours)` - Convert hours to minutes
- `calculate_course_duration(course)` - Calculate course duration
- `calculate_chapter_duration(chapter)` - Calculate chapter duration
- `calculate_module_duration(module)` - Calculate module duration
- `calculate_formation_duration(formation)` - Calculate formation duration
- `duration_statistics(entity)` - Get duration statistics

### Admin Controllers

**Location**: `src/Controller/Admin/DurationManagementController.php`

**Features**:
- Duration statistics dashboard
- Batch synchronization interface
- Individual entity duration management
- Cache management tools

## Performance Considerations

### Caching Strategy

- **Cache Keys**: Prefixed with `duration_` + entity type + entity ID
- **TTL**: 1 hour (3600 seconds)
- **Cache Invalidation**: Automatic on entity updates
- **Cache Storage**: Uses Symfony's configured cache adapter

### Optimization Features

- **Batch Processing**: Processes entities in configurable batches
- **Transaction Management**: Uses database transactions for consistency
- **Memory Management**: Clears entity manager periodically during batch operations
- **Index Usage**: Optimized database queries with proper indexing

## Testing

### Test Suite

**Location**: `tests/Service/DurationCalculationServiceTest.php`

**Test Coverage**:
- Course duration calculation (including exercises and QCMs)
- Chapter duration calculation
- Module duration calculation
- Formation duration calculation
- Unit conversion methods
- Error handling scenarios

**Running Tests**:
```bash
# Run all duration tests
php bin/phpunit tests/Service/DurationCalculationServiceTest.php

# Run specific test
php bin/phpunit tests/Service/DurationCalculationServiceTest.php::testCalculateCourseDuration
```

## Monitoring and Debugging

### Logging

The system logs all duration operations with structured data:

```php
// Example log entries
$this->logger->info('Duration calculation started', [
    'entity_type' => 'Course',
    'entity_id' => $course->getId(),
    'operation' => 'calculate'
]);

$this->logger->info('Duration updated', [
    'entity_type' => 'Course',
    'entity_id' => $course->getId(),
    'old_duration' => $oldDuration,
    'new_duration' => $newDuration,
    'difference' => $difference
]);
```

### Error Handling

- **Graceful Degradation**: System continues to function even if some calculations fail
- **Error Logging**: All errors are logged with context
- **Rollback Support**: Database transactions ensure data consistency

### Performance Monitoring

- **Query Optimization**: Minimizes database queries through caching
- **Batch Processing**: Configurable batch sizes for large datasets
- **Memory Usage**: Monitors and manages memory consumption during bulk operations

## Configuration

### Cache Configuration

```yaml
# config/packages/cache.yaml
framework:
    cache:
        app: cache.adapter.redis  # Or your preferred adapter
        default_redis_provider: redis://localhost:6379
```

### Service Configuration

```yaml
# config/services.yaml
services:
    App\Service\DurationCalculationService:
        arguments:
            $entityManager: '@doctrine.orm.entity_manager'
            $cache: '@cache.app'
            $logger: '@logger'
```

## Troubleshooting

### Common Issues

1. **Cache Issues**:
   - Clear cache: `php bin/console cache:clear`
   - Clear duration cache: `php bin/console app:duration:sync --clear-cache`

2. **Performance Issues**:
   - Increase batch size: `--batch-size=200`
   - Run during off-peak hours
   - Monitor memory usage

3. **Data Inconsistency**:
   - Run full synchronization: `php bin/console app:duration:sync --force`
   - Check entity relationships
   - Verify database indexes

### Debug Commands

```bash
# Check current duration statistics
php bin/console app:duration:sync --dry-run

# Force recalculation of all durations
php bin/console app:duration:sync --force

# Sync specific entity
php bin/console app:duration:sync course --entity-id=123

# Clear all caches and resync
php bin/console app:duration:sync --clear-cache --force
```

## Future Enhancements

### Planned Features

1. **Real-time Updates**: WebSocket-based real-time duration updates
2. **Advanced Analytics**: Duration trend analysis and reporting
3. **Custom Duration Rules**: Configurable calculation rules per entity type
4. **API Endpoints**: REST API for duration management
5. **Async Processing**: Queue-based processing for large datasets

### Extensibility

The system is designed to be easily extensible:

- **New Entity Types**: Add new listeners and calculation methods
- **Custom Calculation Rules**: Implement custom duration calculation logic
- **Additional Storage**: Support for different cache backends
- **Integration Points**: Hooks for external systems and notifications

## Security Considerations

- **Data Validation**: All duration inputs are validated
- **Access Control**: Duration management requires appropriate permissions
- **Audit Trail**: All duration changes are logged
- **Transaction Safety**: Database transactions ensure data consistency

## Conclusion

The Duration Calculation and Synchronization System provides a robust, scalable solution for managing learning time across the EPROFOS platform. With automatic synchronization, comprehensive caching, and powerful administrative tools, it ensures accurate duration tracking while maintaining excellent performance.

For additional support or questions, please refer to the test suite or contact the development team.
