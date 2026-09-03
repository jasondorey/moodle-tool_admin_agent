<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <https://www.gnu.org/licenses/>.

/**
 * Resolve a core_ai provider instance into a chat client.
 *
 * @package   tool_adminagent
 * @copyright 2026 Jason Dorey
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_adminagent\ai;

/**
 * Pick an enabled core AI provider and wrap it.
 *
 * @package   tool_adminagent
 * @copyright 2026 Jason Dorey
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider_resolver {

    /**
     * First usable chat client, or null.
     *
     * @return client|null
     */
    public static function client(): ?client {
        if (self::testing_driver()) {
            return new fake([]);
        }
        $instance = self::instance();
        if (!$instance) {
            return null;
        }
        $providerclass = $instance->provider ?? $instance->providername ?? '';
        $config = self::instance_config($instance);
        $lower = strtolower($providerclass . json_encode($config));
        if (str_contains($lower, 'anthropic') || str_contains($lower, 'claude')) {
            return new anthropic($config);
        }
        if (str_contains($lower, 'gemini') || str_contains($lower, 'google')) {
            return new gemini($config);
        }
        return new openai($config);
    }

    /**
     * Whether automated tests should use the canned fake client.
     *
     * @return bool
     */
    public static function testing_driver(): bool {
        return defined('BEHAT_SITE_RUNNING') && BEHAT_SITE_RUNNING;
    }

    /**
     * Enabled provider instance.
     *
     * @return object|null
     */
    public static function instance(): ?object {
        try {
            if (class_exists(\core_ai\manager::class)) {
                $manager = \core\di::get(\core_ai\manager::class);
                if (method_exists($manager, 'get_provider_instances')) {
                    $instances = $manager->get_provider_instances(['enabled' => true]);
                    if ($instances) {
                        return reset($instances);
                    }
                }
            }
        } catch (\Throwable $e) {
            debugging('tool_adminagent provider resolve: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        return null;
    }

    /**
     * Flatten instance config.
     *
     * @param object $instance
     * @return array
     */
    public static function instance_config(object $instance): array {
        $config = [];
        if (!empty($instance->config)) {
            $decoded = is_string($instance->config) ? json_decode($instance->config, true) : (array) $instance->config;
            if (is_array($decoded)) {
                $config = $decoded;
            }
        }
        foreach (['apikey', 'api_key', 'endpoint', 'baseurl', 'url', 'model'] as $key) {
            if (isset($instance->$key) && $instance->$key !== '' && $instance->$key !== []) {
                $value = $instance->$key;
                if (!is_array($value) && !isset($config[$key])) {
                    $config[$key] = $value;
                }
            }
        }
        if (!empty($instance->actionconfig)) {
            $actions = is_string($instance->actionconfig) ? json_decode($instance->actionconfig, true) : (array) $instance->actionconfig;
            if (is_array($actions)) {
                $preferred = [];
                $fallback = [];
                foreach ($actions as $actionclass => $action) {
                    if (!is_array($action)) {
                        continue;
                    }
                    $settings = is_array($action['settings'] ?? null) ? $action['settings'] : $action;
                    if (!is_array($settings)) {
                        continue;
                    }
                    $istext = is_string($actionclass) && (
                        str_contains($actionclass, 'generate_text')
                        || str_contains($actionclass, 'summarise_text')
                        || str_contains($actionclass, 'explain_text')
                    );
                    if ($istext) {
                        $preferred = array_merge($preferred, $settings);
                    } else {
                        $fallback = array_merge($fallback, $settings);
                    }
                }
                foreach (array_merge($fallback, $preferred) as $key => $value) {
                    if (in_array($key, ['apikey', 'api_key', 'orgid'], true)) {
                        continue;
                    }
                    if ($value === '' || $value === null) {
                        continue;
                    }
                    $config[$key] = $value;
                }
            }
        }
        $config['_provider'] = $instance->provider ?? '';
        return $config;
    }
}
