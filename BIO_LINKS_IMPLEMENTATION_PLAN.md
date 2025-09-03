# Link in Bio Feature Implementation Plan

## Overview

Implement a link in bio feature for photographers to create and manage bio links pointing to their social networks or relevant links. Links belong to teams, and the feature will use Laravel Folio with Volt components for management.

## Database & Models

### 1. Create Bio Links Migration

- Create `bio_links` table with columns:
    - `id` (primary key)
    - `team_id` (foreign key to teams table)
    - `title` (string, link display name)
    - `url` (string, full URL)
    - `order` (integer, for sorting links)
    - `created_at` and `updated_at` timestamps

### 2. Create BioLink Model

- Create `BioLink` Eloquent model
- Add relationship to `Team` model

### 3. Update Team Model

- Add `bio_links` relationship method to `Team` model
- Ensure proper cascading deletes if team is deleted

## Routes & Views

### 4. Create Folio Management Page

- Create new Folio page at `/bio-links` for link management
- Use Volt component for interactive management interface
- Include authentication middleware to ensure only team members can manage

### 5. Update Handle Show Route

- Modify existing `handle.show` route to display bio links
- Add logic to fetch and display team's bio links on public profile
- Style the links appropriately for public viewing using Flux UI components.

### 6. Create Volt Component

- Build Volt component for managing bio links with:
    - Add new link functionality
    - Edit existing links
    - Delete links
    - Reorder links (drag & drop or manual ordering)
    - Form validation and error handling

## Additional Features

### 7. Navigation

- Add navigation link to bio links management page
- Place in appropriate menu (likely settings or team management section)

### 8. Validation

- Add comprehensive validation for:
    - URL format validation
    - Title length and required fields
    - Duplicate URL prevention within same team
    - Order field constraints

## Implementation Order

1. Database migration and models
2. Basic Folio page setup
3. Volt component with CRUD operations
4. Public display integration
5. Navigation and polish

## Notes

- Use `currentTeam()` helper to get user's current team context
- Ensure proper authorization checks for team membership
- Test with multiple teams to ensure proper isolation
