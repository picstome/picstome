---
name: tallstackui-development
description: >-
  Develops UIs with TallStackUI components. Activates when creating buttons, forms, modals,
  inputs, tables, charts, date pickers, or UI components; replacing HTML elements with TallStackUI;
  working with x-* components, soft customization, or when the user mentions TallStackUI,
  component library, UI components, form fields, or asks about available components.
---

# TallStackUI Development

## When to Apply

Activate this skill when:

- Creating new UI components or pages
- Working with forms, modals, or interactive elements
- Styling components with TallStackUI patterns
- Checking available TallStackUI components

## Documentation

Use the package docs under `vendor/tallstackui/tallstackui/.ai/` or `search-docs` for component-specific patterns.

## Basic Usage

TallStackUI is a Blade component library for Livewire built with Tailwind CSS and Alpine.js.

Use TallStackUI components when available. The project wrappers in `resources/views/components/ts-*` keep legacy prop names and brand styling for migrated screens.

<code-snippet name="Basic Button" lang="blade">
<x-button text="Click me" color="primary" />
</code-snippet>

## Available Components

Available: accordion, alert, avatar, badge, banner, breadcrumbs, button, calendar, card, carousel, chart, checkbox, command palette, date, dialog, dropdown, editor, error, gallery, icon, input, layout, link, list, modal, qr code, radio, rating, select, sidebar, signature, slide, spinner, stats, step, tab, table, theme switch, time, timeline, toast, toggle, tooltip, upload.

## Common Patterns

### Form Fields

<code-snippet name="Form Field" lang="blade">
<x-input wire:model="email" label="Email" type="email" />
<x-error property="email" />
</code-snippet>

### Named Modals

<code-snippet name="Named Modal" lang="blade">
<x-modal id="edit-user" title="Edit user">
    ...
</x-modal>

<x-button text="Open" x-on:click="$tsui.open.modal('edit-user')" />
</code-snippet>

From Livewire, use the `InteractsWithTallStackModals` trait (`openModal()` / `closeModal()`).

### Tables

<code-snippet name="Table" lang="blade">
<x-table :headers="$headers" :rows="$rows" :sort="$sort" paginate />
</code-snippet>

## Verification

1. Check component renders correctly
2. Test interactive states
3. Verify mobile responsiveness

## Common Pitfalls

- Not checking if a TallStackUI component exists before creating custom implementations
- Forgetting to use `search-docs` or the vendor `.ai` docs for component-specific documentation
- Not following existing project patterns for TallStackUI usage
