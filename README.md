# Flux UI CLI

Self-contained CLI for accessing Flux UI documentation offline. No PHP required.

## Usage

```bash
# List all documentation
./flux docs

# Search for components
./flux search button

# Show component documentation
./flux show modal
```

## Installation

The `flux` binary is self-contained. You can:

1. Run directly: `~/.claude/skills/flux-ui/flux docs`
2. Symlink to PATH: `ln -sf ~/.claude/skills/flux-ui/flux ~/.local/bin/flux`

## Development

See [src/README.md](src/README.md) for building from source and updating documentation.
