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
    if (window._ssoMappingRowActionsRegistered) {
        return;
    }
    window._ssoMappingRowActionsRegistered = true;

    document.addEventListener('click', function (event) {
        const button = event.target.closest('[data-remove-row]');
        if (!button) {
            return;
        }

        const row = button.closest('tr[data-row]');
        if (!row) {
            return;
        }

        if (button.dataset.undo === '1') {
            const deleteFlag = row.querySelector('.sso-delete-flag');
            if (deleteFlag) {
                deleteFlag.value = '0';
            }

            row.style.opacity = '';
            row.style.pointerEvents = '';
            button.style.pointerEvents = '';
            button.style.opacity = '';
            delete button.dataset.undo;
            button.classList.remove('btn-warning');
            button.classList.add('btn-outline-danger');
            button.innerHTML = button.dataset.originalHtml || button.innerHTML;
            delete button.dataset.originalHtml;
            return;
        }

        if (row.dataset.saved === '1') {
            const deleteFlag = row.querySelector('.sso-delete-flag');
            if (deleteFlag) {
                deleteFlag.value = '1';
            }

            const table = row.closest('table');
            const restoreLabel = table?.dataset.restoreLabel || 'Restore';
            button.dataset.originalHtml = button.innerHTML;
            row.style.opacity = '0.4';
            row.style.pointerEvents = 'none';
            button.style.pointerEvents = 'auto';
            button.style.opacity = '1';
            button.dataset.undo = '1';
            button.classList.remove('btn-outline-danger');
            button.classList.add('btn-warning');
            button.innerHTML = `<i class="ti ti-rotate"></i> ${restoreLabel}`;
            return;
        }

        row.remove();
    });
}());
