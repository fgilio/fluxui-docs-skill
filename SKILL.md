---
name: fluxui-docs
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
| `~/.claude/skills/fluxui-docs/fluxui-docs docs` | List all components, layouts, guides |
| `~/.claude/skills/fluxui-docs/fluxui-docs search <query>` | Fuzzy search documentation |
| `~/.claude/skills/fluxui-docs/fluxui-docs show <name>` | Display full documentation |

## Commands

### fluxui-docs docs

List available documentation items.

```bash
~/.claude/skills/fluxui-docs/fluxui-docs docs                          # List all
~/.claude/skills/fluxui-docs/fluxui-docs docs --category=components    # Components only
~/.claude/skills/fluxui-docs/fluxui-docs docs --json                   # JSON output
```

### fluxui-docs search

Search documentation by name or description.

```bash
~/.claude/skills/fluxui-docs/fluxui-docs search button                 # Search for button
~/.claude/skills/fluxui-docs/fluxui-docs search "date picker"          # Search phrase
~/.claude/skills/fluxui-docs/fluxui-docs search input --json           # JSON output
```

### fluxui-docs show

Display full documentation for an item.

```bash
~/.claude/skills/fluxui-docs/fluxui-docs show button                   # Show button docs
~/.claude/skills/fluxui-docs/fluxui-docs show modal --section=props    # Show specific section
~/.claude/skills/fluxui-docs/fluxui-docs show dropdown --json          # JSON output
```

## Usage Examples

```bash
# Find form components
~/.claude/skills/fluxui-docs/fluxui-docs search form

# Get button variants
~/.claude/skills/fluxui-docs/fluxui-docs show button

# List all components
~/.claude/skills/fluxui-docs/fluxui-docs docs --category=components
```

## Data Location

Documentation is stored in `data/` directory (versioned in git):
- `data/components/` - Component JSON files
- `data/layouts/` - Layout JSON files
- `data/guides/` - Guide JSON files
- `data/index.json` - Search index

## Pro Components

Some components are marked as Pro-only (`[Pro]` badge, `"pro": true` in JSON). These require a Flux UI Pro license.
