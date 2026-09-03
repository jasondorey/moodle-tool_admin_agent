/**
 * Configuration UI for skills, placements and functions.
 *
 * @module     tool_adminagent/config
 * @copyright  2026 Jason Dorey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
    'core/ajax',
    'core/notification',
    'core/modal',
    'core/str',
    'core/templates'
], function(Ajax, Notification, Modal, Str, Templates) {
    'use strict';

    /**
     * Call a plugin web service.
     *
     * @param {string} methodname
     * @param {Object} args
     * @return {Promise}
     */
    const call = (methodname, args) => Ajax.call([{methodname, args}])[0];

    /**
     * Skill options embedded on the placements tab.
     *
     * @return {Object[]}
     */
    const skillOptions = () => {
        const el = document.querySelector('[data-region="skill-options"]');
        if (!el) {
            return [];
        }
        try {
            return JSON.parse(el.textContent || '[]');
        } catch (e) {
            return [];
        }
    };

    /**
     * Bind enabled toggles.
     */
    const bindToggles = () => {
        document.querySelectorAll(
            '[data-action="toggle-skill"], [data-action="toggle-placement"], [data-action="toggle-function"]'
        ).forEach((el) => {
            el.addEventListener('change', () => {
                const type = el.dataset.action.replace('toggle-', '');
                call('tool_adminagent_toggle_item', {
                    type,
                    id: parseInt(el.dataset.id, 10),
                    enabled: el.checked,
                }).catch(Notification.exception);
            });
        });
    };

    /**
     * Collect skill form fields.
     *
     * @param {Element} root
     * @return {Object}
     */
    const collectSkillForm = (root) => ({
        id: parseInt(root.querySelector('[name="id"]')?.value || 0, 10),
        shortname: root.querySelector('[name="shortname"]').value,
        name: root.querySelector('[name="name"]').value,
        description: root.querySelector('[name="description"]').value,
        instructions: root.querySelector('[name="instructions"]').value,
        mainfunctions: root.querySelector('[name="mainfunctions"]').value,
        otherfunctions: root.querySelector('[name="otherfunctions"]').value,
        enabled: root.querySelector('[name="enabled"]').checked,
    });

    /**
     * Skill editor modal.
     *
     * @param {Object} data
     * @return {Promise<void>}
     */
    const skillModal = async (data) => {
        data = data || {};
        const title = await Str.get_string(data.id ? 'editskill' : 'addskill', 'tool_adminagent');
        const {html} = await Templates.renderForPromise('tool_adminagent/skill_form', {
            id: data.id || 0,
            shortname: data.shortname || '',
            name: data.name || '',
            description: data.description || '',
            instructions: data.instructions || '',
            mainfunctions: data.mainfunctions || '',
            otherfunctions: data.otherfunctions || '',
            enabledchecked: !(data.enabled === '0' || data.enabled === false),
            hint: await Str.get_string('skillaihint', 'tool_adminagent'),
        });
        const modal = await Modal.create({title, body: html, large: true, show: true, removeOnClose: true});
        const labels = await Str.get_strings([
            {key: 'saveandpreview', component: 'tool_adminagent'},
            {key: 'saveandclose', component: 'tool_adminagent'},
        ]);
        setTimeout(() => {
            const footer = modal.getRoot()[0].querySelector('.modal-footer');
            if (!footer || footer.querySelector('[data-save-skill]')) {
                return;
            }
            const preview = document.createElement('button');
            preview.className = 'btn btn-secondary';
            preview.dataset.saveSkill = 'preview';
            preview.textContent = labels[0];
            const close = document.createElement('button');
            close.className = 'btn btn-primary';
            close.dataset.saveSkill = 'close';
            close.textContent = labels[1];
            const save = async (reload) => {
                const root = modal.getRoot()[0];
                try {
                    const saved = await call('tool_adminagent_save_skill', collectSkillForm(root));
                    if (reload) {
                        window.location.reload();
                        return;
                    }
                    const pane = root.querySelector('[data-region="preview"]');
                    pane.classList.remove('d-none');
                    pane.textContent = JSON.stringify(saved, null, 2);
                    root.querySelector('[name="id"]').value = saved.id;
                } catch (e) {
                    Notification.exception(e);
                }
            };
            preview.addEventListener('click', () => save(false));
            close.addEventListener('click', () => save(true));
            footer.appendChild(preview);
            footer.appendChild(close);
        }, 50);
    };

    /**
     * Placement editor modal.
     *
     * @param {Object} data
     * @return {Promise<void>}
     */
    const placementModal = async (data) => {
        data = data || {};
        const title = await Str.get_string(data.id ? 'editplacement' : 'addplacement', 'tool_adminagent');
        const loc = data.location || 'floating';
        const selected = String(data.skillids || '').split(',').filter(Boolean);
        const {html} = await Templates.renderForPromise('tool_adminagent/placement_form', {
            name: data.name || await Str.get_string('pluginname', 'tool_adminagent'),
            urlmasks: data.urlmasks || '/*',
            welcome: data.welcome || '',
            loch1: loc === 'h1',
            loch2: loc === 'h2',
            locusermenu: loc === 'usermenu',
            locfloating: loc === 'floating',
            enabledchecked: !(data.enabled === '0' || data.enabled === false),
            skills: skillOptions().map((skill) => ({
                id: skill.id,
                name: skill.name,
                checked: selected.includes(String(skill.id)),
            })),
        });
        const modal = await Modal.create({title, body: html, large: true, show: true, removeOnClose: true});
        const saveLabel = await Str.get_string('saveplacement', 'tool_adminagent');
        setTimeout(() => {
            const footer = modal.getRoot()[0].querySelector('.modal-footer');
            const btn = document.createElement('button');
            btn.className = 'btn btn-primary';
            btn.textContent = saveLabel;
            btn.addEventListener('click', async () => {
                const root = modal.getRoot()[0];
                const skillids = Array.from(root.querySelectorAll('[name="skillids"]:checked'))
                    .map((el) => parseInt(el.value, 10));
                try {
                    await call('tool_adminagent_save_placement', {
                        id: parseInt(data.id || 0, 10),
                        name: root.querySelector('[name="name"]').value,
                        urlmasks: root.querySelector('[name="urlmasks"]').value,
                        location: root.querySelector('[name="location"]:checked').value,
                        skillids,
                        welcome: root.querySelector('[name="welcome"]').value,
                        enabled: root.querySelector('[name="enabled"]').checked,
                    });
                    window.location.reload();
                } catch (e) {
                    Notification.exception(e);
                }
            });
            footer.appendChild(btn);
        }, 50);
    };

    /**
     * Bind add/edit/delete/import/filter actions.
     */
    const bindActions = () => {
        document.querySelector('[data-action="add-skill"]')?.addEventListener('click', () => skillModal({}));
        document.querySelector('[data-action="add-placement"]')?.addEventListener('click', () => placementModal({}));
        document.querySelectorAll('[data-action="edit-skill"]').forEach((el) => {
            el.addEventListener('click', (e) => {
                e.preventDefault();
                skillModal(el.dataset);
            });
        });
        document.querySelectorAll('[data-action="edit-placement"]').forEach((el) => {
            el.addEventListener('click', (e) => {
                e.preventDefault();
                placementModal(el.dataset);
            });
        });
        document.querySelectorAll('[data-action="delete-skill"], [data-action="delete-placement"]').forEach((el) => {
            el.addEventListener('click', async (e) => {
                e.preventDefault();
                const key = el.dataset.action === 'delete-skill' ? 'confirmdelskill' : 'confirmdelplacement';
                if (!confirm(await Str.get_string(key, 'tool_adminagent'))) {
                    return;
                }
                await call('tool_adminagent_delete_item', {
                    type: el.dataset.action.replace('delete-', ''),
                    id: parseInt(el.dataset.id, 10),
                });
                window.location.reload();
            });
        });
        const choose = document.querySelector('[data-action="choose-files"]');
        const input = document.querySelector('[data-action="skill-files"]');
        const drop = document.querySelector('[data-region="import-skills"]');
        const importFiles = async (files) => {
            const payloads = [];
            for (const file of files) {
                payloads.push(await file.text());
            }
            await call('tool_adminagent_import_skills', {payloads});
            window.location.reload();
        };
        choose?.addEventListener('click', () => input?.click());
        input?.addEventListener('change', () => importFiles(input.files));
        drop?.addEventListener('dragover', (e) => {
            e.preventDefault();
        });
        drop?.addEventListener('drop', (e) => {
            e.preventDefault();
            importFiles(e.dataTransfer.files);
        });
        document.querySelector('[data-action="filter-functions"]')?.addEventListener('input', (e) => {
            const q = e.target.value.toLowerCase();
            document.querySelectorAll('[data-region="functions-table"] tbody tr').forEach((tr) => {
                tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    };

    return {
        /**
         * AMD entry point.
         */
        init() {
            bindToggles();
            bindActions();
        }
    };
});
