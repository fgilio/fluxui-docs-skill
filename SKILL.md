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
| `~/.claude/skills/fluxui-docs/fluxui-docs usages <component>` | Find where a component is used |
| `~/.claude/skills/fluxui-docs/fluxui-docs discover` | List undocumented components |

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

### fluxui-docs usages

Find where a component is used in documentation examples. Works for any component, including undocumented ones like `flux:subheading`.

```bash
~/.claude/skills/fluxui-docs/fluxui-docs usages subheading             # Find subheading usage
~/.claude/skills/fluxui-docs/fluxui-docs usages modal.close            # Find sub-component usage
~/.claude/skills/fluxui-docs/fluxui-docs usages button --json          # JSON output
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
~/.claude/skills/fluxui-docs/fluxui-docs discover                      # List all undocumented
~/.claude/skills/fluxui-docs/fluxui-docs discover --json               # JSON output
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
- `data/usages.json` - Component usage index (for usages/discover commands)

## Pro Components

Some components are marked as Pro-only (`[Pro]` badge, `"pro": true` in JSON). These require a Flux UI Pro license.
