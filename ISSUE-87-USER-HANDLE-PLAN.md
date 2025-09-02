# Issue #87: User Handle Implementation Plan

## Overview

Implement user handle feature for allowing URLs like `https://app.picstome.com/@handle`.

## Implementation Steps

1. **Add 'handle' column to teams table via migration**

1.5. **Generate unique handle on user registration**

- Create service/method to generate handle from user name/email
- Ensure uniqueness by appending numbers if needed
- Set handle on team creation during registration

    1.6. **Generate handles for existing teams**

- Create artisan command to backfill handles for existing teams
- Use same generation logic as registration
- Run command after migration
- Create migration to add unique string column 'handle' to teams table
- Each user has a personal team, so handle is stored per team

2. **Update Team model to include handle field and validation**
    - Add fillable/handle to Team model
    - Add validation rules for handle (unique, alphanumeric, etc.)

3. **Create route for /{handle} to show team name**
    - Add route in web.php for `/{handle}` pattern
    - Route should match @ followed by handle

4. **Implement controller logic to find team by handle and display team name**
    - Create controller method to find team by handle
    - Display team name in a simple view
    - Handle 404 if not found

5. **Add handle editing to branding page**
    - Add form field for handle in branding settings
    - Validate uniqueness on update
    - Update team handle via Livewire or controller

## Notes

- Handle should be unique per team
- Consider slug generation from team name if not provided
- Test handle routes and edge cases
