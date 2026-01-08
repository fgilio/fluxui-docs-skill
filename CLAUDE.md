Build: `cd src && ./fluxui-docs build`

## Reusing Build Files (saves ~5min)

Copy from another PHP CLI skill:
```bash
cp -f ~/.claude/skills/OTHER-SKILL/src/spc src/
mkdir -p src/buildroot/bin
cp -f ~/.claude/skills/OTHER-SKILL/src/buildroot/bin/micro.sfx src/buildroot/bin/
```
