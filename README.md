# Admin AI Agent (`tool_adminagent`)

Moodle 5.2+ admin tool that lets site admins and managers chat with an AI agent that can read and change the site through Moodle functions, with per-call approval.

**Release:** `1.0.0-alpha` (alpha — see [HANDOFF.md](HANDOFF.md) for scope and colleague onboarding)

**Repository (source of truth):** https://github.com/Everon-Learning-Solutions/moodle-tool_admin_agent

## Quick start

1. Clone the repo into your Moodle tree:

   ```bash
   git clone git@github.com:Everon-Learning-Solutions/moodle-tool_admin_agent.git admin/tool/adminagent
   ```

   On Moodle 5.2 with the public layout, the path is `{dirroot}/public/admin/tool/adminagent/`.

2. Visit **Site administration → Notifications** to install/upgrade the plugin.

3. Configure an **AI provider** under Site administration → AI → AI providers (`core_ai`).

4. Open **Plugins → Admin tools → AI agent** to review skills, placements, and functions.

5. Use the floating chat button on pages matched by your placement URL masks.

Works on **any** Moodle 5.2+ instance (local VM, colleague VM, or cloud-hosted test) — not tied to a specific host.

## Requirements

- Moodle 5.2 or later (`$plugin->requires = 2026042000`)
- An enabled `core_ai` provider instance
- Capabilities `tool/adminagent:manage` (configuration) and `tool/adminagent:use` (chat)

## Documentation

| Document | Purpose |
|----------|---------|
| [HANDOFF.md](HANDOFF.md) | Alpha scope, install, KB behaviour, tests, QA checklist |
| [CHANGELOG.md](CHANGELOG.md) | Release history |
| [upgrade.txt](upgrade.txt) | Moodle upgrade API notes |

## Development

Chat UI is React/ESM (`js/esm/src/`). After changing TypeScript:

```bash
npx grunt react --root=public/admin/tool/adminagent
```

Admin configuration pages use Mustache + AMD (`amd/src/config.js`).

## Tests

From Moodle root (after [PHPUnit init](https://moodledev.io/general/development/tools/phpunit)):

```bash
vendor/bin/phpunit public/admin/tool/adminagent/tests
php admin/tool/behat/cli/run.php --tags=@tool_adminagent
```

Behat uses a fake LLM — no live provider required.

## Knowledgebase (alpha)

Optional Moodle Docs search via `search_knowledgebase`. Not auto-populated on install; see [HANDOFF.md](HANDOFF.md) and [issue #1](https://github.com/Everon-Learning-Solutions/moodle-tool_admin_agent/issues/1).

## License

GNU GPL v3 or later — see [LICENSE](LICENSE).
