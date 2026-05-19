/*!
 * ---------------------------------------------------------------------
 * SingleSignOn is a plugin which allows to use SSO for auth
 * ---------------------------------------------------------------------
 * Copyright (C) 2026 Edgard
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * ---------------------------------------------------------------------
 * @copyright Copyright © 2021 - 2026 Edgard
 * @license   http://www.gnu.org/licenses/gpl.txt GPLv3+
 * @link      https://github.com/edgardmessias/glpi-singlesignon/
 * ---------------------------------------------------------------------
 */

(function () {
    const tabsRoot = document.getElementById('sso-test-tabs');
    const contentRoot = document.getElementById('sso-test-tab-content');
    if (tabsRoot && contentRoot) {
        tabsRoot.addEventListener('click', function (event) {
            const button = event.target.closest('[data-sso-tab-target]');
            if (!button) {
                return;
            }

            const selector = button.getAttribute('data-sso-tab-target');
            if (!selector) {
                return;
            }

            tabsRoot.querySelectorAll('.nav-link').forEach(function (item) {
                item.classList.remove('active');
            });
            contentRoot.querySelectorAll('.tab-pane').forEach(function (pane) {
                pane.classList.remove('active', 'show');
            });

            button.classList.add('active');
            const target = contentRoot.querySelector(selector);
            if (target) {
                target.classList.add('active', 'show');
            }
        });
    }

    const copyButton = document.getElementById('sso-copy-all-tabs');
    const copySource = document.getElementById('sso-copy-all-source');
    if (!copyButton || !copySource) {
        return;
    }

    const originalButtonHtml = copyButton.innerHTML;
    let resetTimer = null;

    const showCopyStatus = function (label, isError) {
        if (resetTimer) {
            window.clearTimeout(resetTimer);
        }

        copyButton.classList.remove('btn-outline-secondary', 'btn-outline-danger', 'btn-outline-success');
        copyButton.classList.add(isError ? 'btn-outline-danger' : 'btn-outline-success');
        copyButton.innerHTML = `<i class="ti ${isError ? 'ti-alert-circle' : 'ti-check'}"></i><span>${label}</span>`;

        resetTimer = window.setTimeout(function () {
            copyButton.classList.remove('btn-outline-danger', 'btn-outline-success');
            copyButton.classList.add('btn-outline-secondary');
            copyButton.innerHTML = originalButtonHtml;
        }, 2000);
    };

    const copyToClipboard = function (text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text);
        }

        return new Promise(function (resolve, reject) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.setAttribute('readonly', 'readonly');
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();

            try {
                document.execCommand('copy');
                document.body.removeChild(textarea);
                resolve();
            } catch (error) {
                document.body.removeChild(textarea);
                reject(error);
            }
        });
    };

    copyButton.addEventListener('click', function () {
        copyToClipboard(copySource.value || '')
            .then(function () {
                showCopyStatus(copyButton.dataset.copySuccessLabel || 'Copied', false);
            })
            .catch(function () {
                showCopyStatus(copyButton.dataset.copyErrorLabel || 'Copy failed', true);
            });
    });
}());
