# ContactPanda Business repository instructions

## Scope

This repository contains the ContactPanda Business WordPress plugin in `contactpanda-business/`.

## Product invariants

- ContactPanda Business requires ContactPanda Basic, but never changes source-system records.
- Project and correction records live in dedicated Business tables.
- Database upgrades must be non-destructive, repeatable, and prefix-safe.
- Destructive operations require capability checks, nonces, explicit confirmation, and soft deletion where practical.
- Source strings are English and use the `contactpanda-business` text domain.
- Keep CSS and JavaScript in dedicated asset files.
- Run `./tools/check.sh` before handoff. Build ZIP files only for an explicitly requested release.
