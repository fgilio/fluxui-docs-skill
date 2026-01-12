---
name: fluxui-docs
description: >
  Livewire Flux UI component documentation lookup.
  Provides offline access to fluxui.dev documentation via CLI.
  Use for: component props, usage examples, code snippets, Livewire components.
  Keywords: Flux, Flux UI, fluxui, blade component.
user-invocable: true
disable-model-invocation: false
---

# Flux UI CLI

Offline Flux UI documentation with JSON output for Claude Code integration.

## Quick Reference

| Command | Purpose |
|---------|---------|
| `fluxui-docs docs` | List all components, layouts, guides |
| `fluxui-docs search <query>` | Fuzzy search documentation |
| `fluxui-docs show <name>` | Display full documentation |
| `fluxui-docs usages <component>` | Find where a component is used |
| `fluxui-docs discover` | List undocumented components |

## Commands

### fluxui-docs docs

List available documentation items.

```bash
fluxui-docs docs                          # List all
fluxui-docs docs --category=components    # Components only
fluxui-docs docs --json                   # JSON output
```

### fluxui-docs search

Search documentation by name or description.

```bash
fluxui-docs search button                 # Search for button
fluxui-docs search "date picker"          # Search phrase
fluxui-docs search input --json           # JSON output
```

### fluxui-docs show

Display full documentation for an item.

```bash
fluxui-docs show button                   # Show button docs
fluxui-docs show modal --section=props    # Show specific section
fluxui-docs show dropdown --json          # JSON output
```

### fluxui-docs usages

Find where a component is used in documentation examples. Works for any component, including undocumented ones like `flux:subheading`.

```bash
fluxui-docs usages subheading             # Find subheading usage
fluxui-docs usages modal.close            # Find sub-component usage
fluxui-docs usages button --json          # JSON output
```

Example output:
```
flux:subheading is used in:

  components/modal
    └─ Flyout
    └─ Floating flyout

Total: 1 page(s)
```

### fluxui-docs discover

List components found in code examples but without their own documentation page. Surfaces "hidden" components.

```bash
fluxui-docs discover                      # List all undocumented
fluxui-docs discover --json               # JSON output
```

Example output:
```
Sub-components (documented via parent):

  flux:modal.close
    → see: modal
    → used in: modal

  flux:modal.trigger
    → see: modal
    → used in: modal

Components in examples only (no dedicated docs):

  flux:subheading
    → used in: modal, card

  flux:spacer
    → used in: modal, dropdown

Summary:
  Sub-components: 2
  Undocumented: 2
```

## Usage Examples

```bash
# Find form components
fluxui-docs search form

# Get button variants
fluxui-docs show button

# List all components
fluxui-docs docs --category=components
```

## Data Location

Documentation is stored in `data/` directory (versioned in git):
- `data/components/` - Component JSON files
- `data/layouts/` - Layout JSON files
- `data/guides/` - Guide JSON files
- `data/index.json` - Search index
- `data/usages.json` - Component usage index (for usages/discover commands)

## Pro Components

Some components are marked as Pro-only (`[Pro]` badge, `"pro": true` in JSON). These require a Flux UI Pro license.
