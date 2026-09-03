# Changelog

All notable changes to **Admin AI Agent** (`tool_adminagent`) are documented here.

Format follows [Keep a Changelog](https://keepachangelog.com/). Versioning uses the plugin `$plugin->release` string in `version.php`.

## [1.0.0-alpha] — 2026-09-03

### Added

- Initial alpha release for **Moodle 5.2+**.
- Floating / sidebar / fullscreen chat widget (React ESM).
- AI agent loop using `core_ai` provider credentials with tool-calling, approval, and auto-approve modes.
- Function catalog synced from Moodle web services plus ~150 bundled admin tools (`fixtures/functions.json`).
- Dynamic function discovery (`search_tools`) for large catalogs.
- Skills and placements admin UI; default placement and skills seeded on install.
- Conversation persistence, previous-chat menu, delete chat.
- Branded chat header (“Admin Agent”), primary-colour header with contrast-aware icons.
- Modern chat UX refresh (typography, bubbles, composer, tool cards).
- Knowledgebase table (`tool_adminagent_kb`), keyword search, `search_knowledgebase` tool.
- CLI import for Moodle Docs (`cli/import_moodle_docs.php`) and seed fixture `fixtures/moodle_docs_kb.json` (Moodle 5.2 docs, manual import).
- PHPUnit tests (`tests/*`) and Behat features (`@tool_adminagent`).
- Privacy API provider for exported user/conversation data.

### Changed

- User-visible chat messages no longer append `[Current page: …]`; page URL is injected into LLM context only.
- Copy action on assistant messages uses an icon instead of text.

### Known limitations (alpha)

- Knowledgebase is not auto-populated or version-synced on install — see GitHub issue #1.
- Not every catalog function is individually tested.
- `MATURITY_ALPHA` — not intended for unsupported production use without further review.

[1.0.0-alpha]: https://github.com/Everon-Learning-Solutions/moodle-tool_admin_agent/releases/tag/v1.0.0-alpha
