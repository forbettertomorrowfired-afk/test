/**
 * AtomQuest — Core JavaScript
 * Weightage validation, notifications, role switcher, inline edit, utilities
 */

// ─── CSRF Token ─────────────────────────────────────────
function getCsrfToken() {
    // Prefer the meta tag (always injected in header for logged-in users)
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) return meta.getAttribute('content');
    // Fallback: hidden input inside a form (login page)
    const el = document.querySelector('input[name="_csrf"]');
    return el ? el.value : '';
}

// ─── Notifications ──────────────────────────────────────
function toggleNotifications() {
    const dd = document.getElementById('notifDropdown');
    if (!dd) return;
    dd.style.display = dd.style.display === 'none' ? 'block' : 'none';
    if (dd.style.display === 'block') loadNotifications();
}

function loadNotifications() {
    const list = document.getElementById('notifList');
    if (!list) return;
    fetch('/api/notifications.php')
        .then(r => r.json())
        .then(data => {
            if (!data.length) {
                list.innerHTML = '<div class="notif-item text-muted">No new notifications</div>';
                return;
            }
            list.innerHTML = data.map(n => `
                <a href="${n.link || '#'}" class="notif-item" style="display:block; text-decoration:none; color:var(--text-main);">
                    <div>${n.message}</div>
                    <div class="notif-time">${n.created_at}</div>
                </a>
            `).join('');
        })
        .catch(() => { list.innerHTML = '<div class="notif-item text-muted">Failed to load</div>'; });
}

// Close notifications on outside click
document.addEventListener('click', function(e) {
    const wrapper = document.querySelector('.notif-wrapper');
    const dd = document.getElementById('notifDropdown');
    if (wrapper && dd && !wrapper.contains(e.target)) {
        dd.style.display = 'none';
    }
});

// ─── Role Switcher ──────────────────────────────────────
function switchRole(userId) {
    if (!userId) return;
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/index.php?action=switch';
    const uid = document.createElement('input');
    uid.type = 'hidden'; uid.name = 'user_id'; uid.value = userId;
    form.appendChild(uid);
    const csrf = document.createElement('input');
    csrf.type = 'hidden'; csrf.name = '_csrf'; csrf.value = getCsrfToken();
    form.appendChild(csrf);
    document.body.appendChild(form);
    form.submit();
}

// ─── Weightage Tracker ──────────────────────────────────
function updateWeightageBar() {
    const inputs = document.querySelectorAll('.goal-weightage');
    const bar = document.querySelector('.weightage-bar-fill');
    const label = document.getElementById('weightageLabel');
    const countLabel = document.getElementById('goalCountLabel');
    const submitBtn = document.getElementById('submitBtn');
    const addBtn = document.getElementById('addGoalBtn');

    if (!bar || !label) return;

    let total = 0;
    let count = 0;
    inputs.forEach(input => {
        const row = input.closest('.goal-row');
        if (row && !row.classList.contains('deleted')) {
            total += parseInt(input.value) || 0;
            count++;
        }
    });

    bar.style.width = Math.min(total, 100) + '%';
    bar.className = 'weightage-bar-fill ' +
        (total === 100 ? 'ok' : total > 100 ? 'over' : 'under');
    label.textContent = total + '% / 100%';

    if (countLabel) countLabel.textContent = count + ' / 8 goals';
    if (submitBtn) submitBtn.disabled = (total !== 100 || count === 0);
    if (addBtn) addBtn.disabled = (count >= 8);
}

// ─── Goal Form Management ───────────────────────────────
var goalCounter = window.goalCounter || 0;

function addGoalRow() {
    const container = document.getElementById('goalsContainer');
    if (!container) return;

    const rows = container.querySelectorAll('.goal-row:not(.deleted)');
    if (rows.length >= 8) {
        alert('Maximum 8 goals allowed.');
        return;
    }

    goalCounter++;
    const row = document.createElement('div');
    row.className = 'goal-row';
    row.dataset.index = goalCounter;
    row.innerHTML = getGoalRowHTML(goalCounter);
    container.appendChild(row);
    updateWeightageBar();
    bindUomToggle(row);
}

function removeGoalRow(btn) {
    const row = btn.closest('.goal-row');
    if (!row) return;
    if (row.dataset.goalId) {
        // Existing goal — mark as deleted
        row.classList.add('deleted');
        row.style.display = 'none';
        let delInput = row.querySelector('.delete-flag');
        if (!delInput) {
            delInput = document.createElement('input');
            delInput.type = 'hidden';
            delInput.name = 'delete_goals[]';
            delInput.className = 'delete-flag';
            delInput.value = row.dataset.goalId;
            row.appendChild(delInput);
        }
    } else {
        row.remove();
    }
    updateWeightageBar();
}

function getGoalRowHTML(index, data) {
    const d = data || {};
    const thrustOptions = (window._thrustAreas || []).map(t =>
        `<option value="${t.id}" ${d.thrust_area_id == t.id ? 'selected' : ''}>${t.name}</option>`
    ).join('');

    const uomOptions = Object.entries(window._uomTypes || {}).map(([k, v]) =>
        `<option value="${k}" ${d.uom_type === k ? 'selected' : ''}>${v}</option>`
    ).join('');

    const isShared = d.is_shared ? 'shared' : '';
    const readonly = d.is_shared ? 'readonly' : '';
    const disabled = d.is_shared ? 'disabled' : '';

    return `
        <div class="goal-row-header">
            <span class="goal-row-number">Goal #${index}</span>
            ${d.is_shared ? '<span class="badge badge-info">Shared</span>' : ''}
            ${!d.is_shared ? `<button type="button" class="btn-remove" onclick="removeGoalRow(this)" title="Remove goal">✕</button>` : ''}
        </div>
        <input type="hidden" name="goals[${index}][id]" value="${d.id || ''}">
        <input type="hidden" name="goals[${index}][is_shared]" value="${d.is_shared ? '1' : '0'}">
        <input type="hidden" name="goals[${index}][shared_source_id]" value="${d.shared_source_id || ''}">
        <div class="goal-row-fields">
            <div class="form-group">
                <label>Thrust Area</label>
                <select name="goals[${index}][thrust_area_id]" class="form-control" required ${disabled}>
                    <option value="">Select...</option>
                    ${thrustOptions}
                </select>
                ${d.is_shared && d.thrust_area_id ? `<input type="hidden" name="goals[${index}][thrust_area_id]" value="${d.thrust_area_id}">` : ''}
            </div>
            <div class="form-group">
                <label>Goal Title</label>
                <input type="text" name="goals[${index}][title]" class="form-control" maxlength="255"
                       value="${(d.title || '').replace(/"/g, '&quot;')}" required ${readonly}>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="goals[${index}][description]" class="form-control" ${readonly}>${d.description || ''}</textarea>
            </div>
            <div class="form-group">
                <label>Unit of Measurement</label>
                <select name="goals[${index}][uom_type]" class="form-control uom-select" required ${disabled}
                        onchange="toggleTargetField(this)">
                    <option value="">Select...</option>
                    ${uomOptions}
                </select>
                ${d.is_shared && d.uom_type ? `<input type="hidden" name="goals[${index}][uom_type]" value="${d.uom_type}">` : ''}
            </div>
            <div class="form-group target-value-group" style="${d.uom_type === 'timeline' || d.uom_type === 'zero' ? 'display:none' : ''}">
                <label>Target Value</label>
                <input type="number" name="goals[${index}][target_value]" class="form-control"
                       step="0.01" min="0" value="${d.target_value || ''}" ${readonly}>
            </div>
            <div class="form-group target-date-group" style="${d.uom_type === 'timeline' ? '' : 'display:none'}">
                <label>Target Date</label>
                <input type="date" name="goals[${index}][target_date]" class="form-control"
                       value="${d.target_date || ''}" ${readonly}>
            </div>
            <div class="form-group">
                <label>Weightage (%)</label>
                <input type="number" name="goals[${index}][weightage]" class="form-control goal-weightage"
                       min="10" max="100" step="5" value="${d.weightage || 10}"
                       onchange="updateWeightageBar()" oninput="updateWeightageBar()">
            </div>
        </div>
    `;
}

function toggleTargetField(select) {
    const row = select.closest('.goal-row');
    if (!row) return;
    const uom = select.value;
    const valGroup = row.querySelector('.target-value-group');
    const dateGroup = row.querySelector('.target-date-group');
    if (valGroup) valGroup.style.display = (uom === 'timeline' || uom === 'zero') ? 'none' : '';
    if (dateGroup) dateGroup.style.display = (uom === 'timeline') ? '' : 'none';
}

function bindUomToggle(row) {
    const sel = row.querySelector('.uom-select');
    if (sel) toggleTargetField(sel);
}

// ─── Inline Edit ────────────────────────────────────────
function makeEditable(cell, field, recordId, type) {
    if (cell.querySelector('input, select')) return;
    const currentValue = cell.textContent.trim();
    cell.dataset.original = currentValue;

    let input;
    if (type === 'number') {
        input = document.createElement('input');
        input.type = 'number';
        input.value = currentValue;
        input.step = field === 'weightage' ? '5' : '0.01';
        if (field === 'weightage') { input.min = '10'; input.max = '100'; }
    } else {
        input = document.createElement('input');
        input.type = 'text';
        input.value = currentValue;
    }

    input.className = 'form-control';
    input.style.width = '100%';
    cell.textContent = '';
    cell.appendChild(input);
    input.focus();

    input.addEventListener('blur', () => saveInlineEdit(cell, field, recordId, input.value));
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') input.blur();
        if (e.key === 'Escape') { cell.textContent = cell.dataset.original; }
    });
}

function saveInlineEdit(cell, field, recordId, newValue) {
    const original = cell.dataset.original;
    if (newValue === original) { cell.textContent = original; return; }

    fetch('/api/inline_edit.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': getCsrfToken()
        },
        body: JSON.stringify({ id: recordId, field: field, value: newValue })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            cell.textContent = newValue;
            if (data.version) {
                const vInput = document.querySelector('input[name="version"]');
                if (vInput) vInput.value = data.version;
            }
        } else {
            alert(data.error || 'Save failed');
            cell.textContent = original;
        }
    })
    .catch(() => { cell.textContent = original; });
}

// ─── Confirm Dialog ─────────────────────────────────────
function confirmAction(message, formId) {
    if (confirm(message)) {
        document.getElementById(formId).submit();
    }
}

// ─── Init ───────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    updateWeightageBar();

    // Initialize existing goal rows
    document.querySelectorAll('.goal-row').forEach(row => bindUomToggle(row));
});
