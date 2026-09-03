# Admin AI agent (`tool_adminagent`)

Moodle 5.2+ admin tool that lets site admins and managers chat with an AI agent that can read and change the site through Moodle functions, with per-call approval.

## Requirements

- Moodle 5.2 or later (`$plugin->requires = 2026042000`)
- An enabled `core_ai` provider instance (credentials are borrowed; the plugin runs its own tool-calling loop)
- Capabilities `tool/adminagent:manage` (configuration) and `tool/adminagent:use` (chat)

## Install

Copy this directory to `admin/tool/adminagent/` (on Moodle 5.2 public layout: `{dirroot}/public/admin/tool/adminagent/`), then visit notifications to install.

Chat is a Moodle 5.2 React/ESM mount (`js/esm/src/ChatApp.tsx`). After changing TypeScript sources, rebuild with:

```
npx grunt react --root=public/admin/tool/adminagent
```

Admin configuration pages remain Mustache + AMD (`amd/src/config.js`).

## Tests

From the Moodle root, with this plugin installed:

```
vendor/bin/phpunit public/admin/tool/adminagent/tests
php admin/tool/behat/cli/run.php --tags=@tool_adminagent
```

Behat uses a canned fake LLM so acceptance tests never call a live provider.
