# Fixtures Documentation - Updated for Prospect Unification

## Overview

The fixtures have been updated to work seamlessly with the new **Prospect Unification** system. This ensures that every customer interaction automatically creates prospects through the `ProspectManagementService`.

## Updated Fixtures

### 1. AppFixtures
- **Purpose**: Main orchestrator for loading all fixtures in correct order
- **Changes**: 
  - Added `UserFixtures` and `ProspectFixtures` dependencies
  - Updated loading order to create base prospects before interaction-specific entities
  - Ensures proper dependency chain

### 2. ProspectFixtures
- **Purpose**: Creates independent prospects with notes (sales team direct entries)
- **Changes**:
  - Now implements `DependentFixtureInterface`
  - Depends on `UserFixtures` for prospect assignment
  - Creates 30 base prospects with various statuses and priorities
  - **Data Created**: 30 prospects with associated notes

### 3. ContactRequestFixtures
- **Purpose**: Creates contact requests and automatically generates prospects
- **Changes**:
  - Added `ProspectManagementService` injection
  - After creating contact requests, uses the service to create prospects
  - Includes logging for debugging prospect creation
  - **Data Created**: 8 contact requests → 8 prospects automatically

### 4. SessionFixtures
- **Purpose**: Creates training sessions with registrations and prospects
- **Changes**:
  - Added `ProspectManagementService` injection
  - After creating session registrations, uses the service to create prospects
  - Includes logging for debugging prospect creation
  - **Data Created**: ~32 sessions with ~293 registrations → 293 prospects automatically

### 5. NeedsAnalysisFixtures
- **Purpose**: Creates needs analysis requests and prospects
- **Changes**:
  - Added `ProspectManagementService` injection
  - After creating needs analysis requests, uses the service to create prospects
  - Fixed method call to use `getRecipientEmail()` instead of `getContactEmail()`
  - **Data Created**: 25 needs analysis requests → 25 prospects automatically

## Loading Process

The fixtures now follow this sequence:

1. **Base Entities**: Categories, Services, Formations, Users
2. **Independent Prospects**: Created by `ProspectFixtures` (30 prospects)
3. **Interaction Entities + Prospect Creation**:
   - Contact Requests (8) → Prospects (8)
   - Session Registrations (293) → Prospects (293)
   - Needs Analysis (25) → Prospects (25)

## Total Data Created

After running `doctrine:fixtures:load`:

- **356 total prospects**
- **326 prospects** created through interactions (contact requests, sessions, needs analysis)
- **30 prospects** created independently (sales team entries)
- **All prospects properly linked** to their source interactions

## Prospect Sources Distribution

- `session_registration`: 293 prospects
- `needs_analysis`: 25 prospects
- Direct prospects: 30 prospects (various sources like phone_call, email_campaign, trade_show, etc.)
- `quote_request`: 3 prospects
- `information_request`: 2 prospects
- `consultation_request`: 2 prospects
- `quick_registration`: 1 prospect

## Benefits

1. **Realistic Testing Data**: Complete prospect ecosystem with relationships
2. **Service Integration Testing**: Validates `ProspectManagementService` works correctly
3. **Unified Lead Management**: All customer touchpoints create prospects automatically
4. **Lead Scoring Validation**: Prospects have appropriate scores based on interaction types
5. **Timeline Validation**: Activity timelines show all related interactions

## Running the Fixtures

```bash
# Load all fixtures (purges database first)
docker compose exec php php bin/console doctrine:fixtures:load --no-interaction

# Check results
docker compose exec php php bin/console app:prospect-summary
```

## Error Handling

The fixtures include comprehensive error handling:
- **Service injection failures** are logged but don't break fixture loading
- **Prospect creation errors** are logged with context for debugging
- **Database constraint violations** are handled gracefully
- **Missing relationships** are validated before prospect creation

## Testing Integration

The updated fixtures provide excellent data for testing:
- **Lead scoring calculations**
- **Activity timeline displays**
- **Prospect dashboard functionality**
- **Sales team workflows**
- **Automated prospect creation from forms**

This ensures the development and staging environments have realistic, comprehensive data that mirrors the production prospect unification workflow.
