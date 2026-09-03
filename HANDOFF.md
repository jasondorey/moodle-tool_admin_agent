# Handoff guide — Admin AI Agent (`tool_adminagent`) 1.0.0-alpha

This document freezes what **1.0.0-alpha** means for Everon colleagues taking over the plugin. It applies whether you install on a **local VM**, a **colleague-owned Moodle**, or a **cloud-hosted test instance** — not only the original development VM.

## Source of truth

| Item | Location |
|------|----------|
| **Git repository** | https://github.com/Everon-Learning-Solutions/moodle-tool_admin_agent (private) |
| **Default branch** | `main` |
| **Release label** | `1.0.0-alpha` (`version.php` → `$plugin->release`) |
| **Plugin version (DB)** | `2026090302` |
| **Moodle requirement** | 5.2+ (`$plugin->requires = 2026042000`) |

Clone and install from GitHub. Do not treat any single developer VM as the canonical deployment target.

```bash
git clone git@github.com:Everon-Learning-Solutions/moodle-tool_admin_agent.git
# Copy or symlink into Moodle:
#   {dirroot}/public/admin/tool/adminagent/   (Moodle 5.2 public layout)
```

Then visit **Site administration → Notifications** to run the upgrade.

## What “alpha” means

**In scope for 1.0.0-alpha**

- Chat widget (overlay, sidebar, fullscreen) for users with `tool/adminagent:use`
- Admin configuration: skills, placements, function catalog (Mustache + AMD)
- AI agent loop via `core_ai` provider credentials (OpenAI, Anthropic, Gemini, or fake driver for tests)
- Dynamic function discovery + per-call approve/deny (and auto-approve modes)
- Bundled admin tools (~150) plus a large catalog of Moodle web service functions (site-dependent after sync)
- Conversation persistence, previous-chat menu, header UX refresh
- Knowledgebase **table + search tool** (`search_knowledgebase`) — see [Knowledgebase](#knowledgebase) below
- PHPUnit unit tests and Behat scenarios under `tests/` (require Moodle test env setup)

**Explicitly out of scope / not production-ready**

- Individual certification of every catalog function (~900+ entries) — not smoke-tested end-to-end
- Automatic, version-aware Moodle Docs sync on client sites — tracked in [GitHub issue #1](https://github.com/Everon-Learning-Solutions/moodle-tool_admin_agent/issues/1)
- Moodle.org plugin directory submission
- Maturity above `MATURITY_ALPHA`

**Expectation for colleagues:** treat this as an internal alpha for evaluation and iteration on **your own** Moodle 5.2+ instance, not as a supported client deliverable without further hardening.

## Install on your own instance (VM or cloud)

### Requirements

1. **Moodle 5.2 or later** with the standard public directory layout (`public/admin/tool/...`).
2. **An enabled `core_ai` provider** in Site administration → AI → AI providers (the plugin borrows credentials; it runs its own tool-calling loop).
3. **Capabilities:** by default, managers have `tool/adminagent:manage` and `tool/adminagent:use`. Adjust role assignments as needed.
4. **PHP extensions / stack** per normal Moodle 5.2 requirements.

### Steps

1. Clone the repo (see above) into `admin/tool/adminagent`.
2. Run the Moodle upgrade (notifications page or `php admin/cli/upgrade.php --non-interactive`).
3. Configure the plugin: **Site administration → Plugins → Admin tools → AI agent**.
4. Ensure at least one **placement** is enabled (a default floating placement is seeded on install).
5. Open any page matching the placement URL masks and use the floating chat button.

### Rebuilding the chat UI (only if you change TypeScript)

Sources live in `js/esm/src/`. From Moodle root:

```bash
npx grunt react --root=public/admin/tool/adminagent
```

Committed `js/esm/build/` artifacts are included so a git clone works without a local Node build unless you edit TS.

### Optional: seed knowledgebase (manual)

The KB is **not** populated automatically on install. To load the bundled 5.2 docs export:

```bash
php admin/tool/adminagent/cli/import_moodle_docs.php \
  --purge --file=admin/tool/adminagent/fixtures/moodle_docs_kb.json
```

Run as the web server user if your environment requires it (e.g. `sudo -u www-data ...`).

## Knowledgebase

**Current behaviour**

- Table: `tool_adminagent_kb` (chunked doc passages).
- Tool: `search_knowledgebase` — the **model** calls it when it needs Moodle documentation; nothing is injected automatically every turn.
- Fixture: `fixtures/moodle_docs_kb.json` — ~77 Moodle **5.2** admin doc articles (dev seed), not auto-imported on install.
- Search is keyword-based (SQL `LIKE`), not embeddings.
- **No detection** of the site’s Moodle version when searching; corpus is whatever was imported.

**Product direction (not implemented)**

See [issue #1: Version-aware, self-updating Moodle Docs knowledgebase](https://github.com/Everon-Learning-Solutions/moodle-tool_admin_agent/issues/1):

- Auto-provision KB on install and refresh when Moodle is upgraded (e.g. 5.2 → 5.3).
- Vendor-hosted versioned JSON feed + bundled fallback (not client-side scrape of docs.moodle.org — Cloudflare blocks unattended PHP/curl on many hosts).

## Architecture (30-second map)

| Area | Location |
|------|----------|
| Chat UI (React) | `js/esm/src/ChatApp.tsx`, components, `styles.css` |
| Admin config (AMD) | `amd/src/config.js`, `index.php`, Mustache templates |
| Agent loop | `classes/local/agent.php` |
| Function catalog | `classes/local/catalog.php`, `fixtures/functions.json` |
| Execution | `classes/local/executor.php`, `classes/local/bundled/*` |
| Web services (AJAX) | `classes/external/*`, `db/services.php` |
| AI providers | `classes/ai/*` |
| KB | `classes/local/knowledgebase.php`, `cli/import_moodle_docs.php` |

## Running tests

Tests run **from the Moodle root**, not from the plugin directory alone. Each Moodle install needs a **one-time PHPUnit init**:

```bash
php admin/tool/phpunit/cli/init.php
```

Then:

```bash
vendor/bin/phpunit public/admin/tool/adminagent/tests
```

Behat (uses fake LLM — no live provider):

```bash
php admin/tool/behat/cli/init.php   # once per install, if not already done
php admin/tool/behat/cli/run.php --tags=@tool_adminagent
```

### Reference VM test run (2026-09-03)

Attempted on the original development VM (`172.16.232.131`):

| Suite | Result | Notes |
|-------|--------|-------|
| PHPUnit | **Not executed** | Moodle reported PHPUnit env “initialised for different version”; `admin/tool/phpunit/cli/init.php --force` failed (composer self-update permission denied on that host). |
| Behat | **Not run** | Blocked on same test-environment setup. |

**Action for colleagues:** on a **clean** Moodle 5.2+ clone (local VM or cloud), run PHPUnit/Behat init as a user that can write Moodle’s `composer.phar` and dataroot test tables, then run the commands above and record pass/fail in this table or in CI.

## Manual QA checklist (recommended once per environment)

- [ ] Floating button visible; overlay opens
- [ ] Send message; fake or live provider responds
- [ ] Tool call appears; Approve / Deny works
- [ ] Auto-approve menu (None / Read-only / Safe / All)
- [ ] Header menu: New chat, Previous chats, Delete chat
- [ ] Layout switch: overlay / sidebar / fullscreen
- [ ] Admin: Skills, Placements, Functions tabs load
- [ ] Primary-colour header readable (light/dark primary themes)

## Access and secrets (do not commit)

- GitHub org membership for `Everon-Learning-Solutions`
- AI provider API keys → configured in Moodle **core_ai**, not in this repo
- VM/cloud host credentials → per-environment, not in repo

## Support and next issues

- KB auto-update: [#1](https://github.com/Everon-Learning-Solutions/moodle-tool_admin_agent/issues/1)
- Questions: use GitHub Issues on the repo above.

---

*Plugin component:* `tool_adminagent` · *Release:* `1.0.0-alpha` · *Copyright:* Everon Learning Solutions / Jason Dorey (GPL v3+)
