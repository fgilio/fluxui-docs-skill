---
name: flux-ui
description: >
  Livewire Flux UI component documentation lookup.
  Provides offline access to fluxui.dev documentation via CLI.
  Use for: component props, usage examples, code snippets, Livewire components.
  Trigger words: Flux, Flux UI, fluxui, blade component, Livewire component.
---

# Flux UI CLI

Offline Flux UI documentation with JSON output for Claude Code integration.

## Quick Reference

| Command | Purpose |
|---------|---------|
| `flux docs` | List all components, layouts, guides |
| `flux search <query>` | Fuzzy search documentation |
| `flux show <name>` | Display full documentation |

## Commands

### flux docs

List available documentation items.

```bash
flux docs                          # List all
flux docs --category=components    # Components only
flux docs --json                   # JSON output
```

### flux search

Search documentation by name or description.

```bash
flux search button                 # Search for button
flux search "date picker"          # Search phrase
flux search modal --limit 5        # Limit results
flux search input --json           # JSON output
```

### flux show

Display full documentation for an item.

```bash
flux show button                   # Show button docs
flux show modal --section=props    # Show specific section
flux show dropdown --json          # JSON output
```

## Usage Examples

```bash
# Find form components
flux search form

# Get button variants
flux show button

# List all components
flux docs --category=components
```

## Data Location

Documentation is stored in `data/` directory (versioned in git):
- `data/components/` - Component JSON files
- `data/layouts/` - Layout JSON files
- `data/guides/` - Guide JSON files
- `data/index.json` - Search index

## Pro Components

Some components are marked as Pro-only (`[Pro]` badge, `"pro": true` in JSON). These require a Flux UI Pro license.
