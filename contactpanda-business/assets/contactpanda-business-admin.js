(() => {
	'use strict';
	let dragged = null;
	let draggedRecently = false;

	document.addEventListener('click', (event) => {
		if (event.target.closest('button,a,input,select,textarea,form') && event.target.closest('[data-cpb-task-card]')) return;
		const opener = event.target.closest('[data-cpb-open]');
		if (opener) {
			if (draggedRecently && opener.matches('[data-cpb-task-card]')) return;
			const modal = document.getElementById(opener.dataset.cpbOpen);
			if (modal) { modal.hidden = false; modal.querySelector('input,select,textarea,button')?.focus(); }
		}
		if (event.target.closest('[data-cpb-close]') || event.target.classList.contains('cpb-modal')) {
			event.target.closest('.cpb-modal')?.setAttribute('hidden', '');
		}
	});
	document.querySelectorAll('[data-cpb-confirm]').forEach((form) => form.addEventListener('submit', (event) => {
		if (!window.confirm(form.dataset.cpbConfirm || 'Continue?')) event.preventDefault();
	}));
	document.addEventListener('keydown', (event) => {
		const card = event.target.closest('[data-cpb-task-card]');
		if (card && (event.key === 'Enter' || event.key === ' ')) { event.preventDefault(); card.click(); }
		if (event.key === 'Escape') document.querySelectorAll('.cpb-modal:not([hidden])').forEach((modal) => modal.setAttribute('hidden', ''));
	});
	const taskToOpen = new URLSearchParams(window.location.search).get('open_task');
	if (taskToOpen) {
		const modal = document.getElementById(`cpb-task-${String(taskToOpen).replace(/\D/g, '')}`);
		if (modal) {
			modal.hidden = false;
			const cleanUrl = new URL(window.location.href);
			cleanUrl.searchParams.delete('open_task');
			window.history.replaceState({}, '', cleanUrl.toString());
		}
	}

	document.querySelectorAll('[data-cpb-inline-task-open]').forEach((button) => {
		button.addEventListener('click', () => {
			const form = document.getElementById(button.dataset.cpbInlineTaskOpen);
			if (!form) return;
			form.hidden = false;
			button.hidden = true;
			form.querySelector('textarea')?.focus();
		});
	});
	document.querySelectorAll('[data-cpb-inline-task-cancel]').forEach((button) => {
		button.addEventListener('click', () => {
			const form = button.closest('.cpb-inline-task-form');
			if (!form) return;
			form.hidden = true;
			form.reset();
			const trigger = document.querySelector(`[data-cpb-inline-task-open="${form.id}"]`);
			if (trigger) trigger.hidden = false;
		});
	});

	document.querySelectorAll('[data-cpb-task-files]').forEach((input) => {
		const form = input.closest('form');
		const selection = form?.querySelector('[data-cpb-file-selection]');
		const submit = form?.querySelector('[data-cpb-file-submit]');
		input.addEventListener('change', () => {
			const files = Array.from(input.files || []);
			if (selection) selection.textContent = files.length ? files.map((file) => file.name).join(', ') : (window.contactPandaBusiness?.selectFiles || 'Select one or more files.');
			if (submit) submit.disabled = files.length === 0;
		});
	});

	document.querySelectorAll('[data-cpb-import-apply-form][data-cpb-import-repeat-warning]').forEach((form) => {
		form.addEventListener('submit', (event) => {
			if (!form.querySelector('[data-cpb-confirm-repeat]')?.checked || !window.confirm(form.dataset.cpbImportRepeatWarning)) event.preventDefault();
		});
	});

	document.querySelectorAll('[data-cpb-participant-picker]').forEach((picker) => {
		const search = picker.querySelector('[data-cpb-participant-search]');
		const options = Array.from(picker.querySelectorAll('[data-cpb-participant-option]'));
		const summary = picker.querySelector('[data-cpb-participant-summary]');
		const normalize = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase();
		const updateSummary = () => {
			const selected = options.filter((option) => option.querySelector('input[type="checkbox"]')?.checked);
			summary.textContent = selected.length ? selected.map((option) => option.querySelector('label span')?.textContent.trim()).join(', ') : (window.contactPandaBusiness?.selectParticipants || 'Select participants');
		};
		search?.addEventListener('input', () => {
			const needle = normalize(search.value.trim());
			options.forEach((option) => { option.hidden = !!needle && !option.dataset.search.includes(needle); });
		});
		options.forEach((option) => option.querySelector('input[type="checkbox"]')?.addEventListener('change', updateSummary));
		updateSummary();
	});

	document.addEventListener('dragstart', (event) => {
		if (event.target.closest('button,a,input,select,textarea,form')) { event.preventDefault(); return; }
		dragged = event.target.closest('[data-cpb-task]');
		if (dragged) {
			draggedRecently = true;
			dragged.classList.add('is-dragging');
			if (event.dataTransfer) {
				event.dataTransfer.effectAllowed = 'move';
				event.dataTransfer.setData('text/plain', dragged.dataset.cpbTask || '');
			}
		}
	});
	document.addEventListener('dragend', () => { dragged?.classList.remove('is-dragging'); dragged = null; window.setTimeout(() => { draggedRecently = false; }, 150); });
	document.querySelectorAll('[data-cpb-column]').forEach((column) => {
		column.addEventListener('dragover', (event) => { if (dragged || event.dataTransfer?.types.includes('text/plain')) { event.preventDefault(); if (event.dataTransfer) event.dataTransfer.dropEffect = 'move'; column.classList.add('is-drop-target'); } });
		column.addEventListener('dragleave', () => column.classList.remove('is-drop-target'));
		column.addEventListener('drop', async (event) => {
			event.preventDefault(); column.classList.remove('is-drop-target');
			const taskId = String(dragged?.dataset.cpbTask || event.dataTransfer?.getData('text/plain') || '').replace(/\D/g, '');
			if (!taskId || !window.contactPandaBusiness) return;
			try {
				const body = new URLSearchParams({ action: 'contactpanda_business_move_task', nonce: contactPandaBusiness.nonce, task_id: taskId, column: column.dataset.cpbColumn });
				const response = await fetch(contactPandaBusiness.ajaxUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body });
				const result = await response.json();
				if (!response.ok || !result.success) throw new Error(result?.data?.message || 'move_failed');
				const card = dragged || document.querySelector(`[data-cpb-task="${taskId}"]`);
				if (card) column.querySelector('.cpb-column-body')?.appendChild(card);
				window.location.reload();
			} catch (error) {
				window.alert(window.contactPandaBusiness.moveFailed || 'The task could not be moved.');
			}
		});
	});

	const board = document.querySelector('[data-cpb-project]');
	if (board) {
		const areaInputs = Array.from(document.querySelectorAll('[data-cpb-filter-area]'));
		const assignee = document.querySelector('[data-cpb-filter-assignee]');
		const storageKey = `contactpanda-business-board-${board.dataset.cpbProject}`;
		const applyFilters = () => {
			const areas = areaInputs.filter((input) => input.checked).map((input) => input.value);
			const assigned = assignee?.value || 'all';
			board.querySelectorAll('[data-cpb-task]').forEach((card) => {
				const areaVisible = areas.includes(card.dataset.cpbArea || '0');
				const assigneeVisible = assigned === 'all' || (assigned === 'me' && card.dataset.cpbAssignee === String(window.contactPandaBusiness?.currentUserId || 0)) || card.dataset.cpbAssignee === assigned;
				card.hidden = !(areaVisible && assigneeVisible);
			});
			board.querySelectorAll('[data-cpb-column]').forEach((column) => {
				const count = Array.from(column.querySelectorAll('[data-cpb-task]')).filter((card) => !card.hidden).length;
				const badge = column.querySelector(':scope > header > span'); if (badge) badge.textContent = String(count);
			});
			try { localStorage.setItem(storageKey, JSON.stringify({ areas, assigned })); } catch (error) { /* Storage can be unavailable. */ }
		};
		try {
			const saved = JSON.parse(localStorage.getItem(storageKey) || 'null');
			if (saved?.areas) areaInputs.forEach((input) => { input.checked = saved.areas.includes(input.value); });
			if (saved?.assigned && assignee) assignee.value = saved.assigned;
		} catch (error) { /* Ignore malformed saved filters. */ }
		areaInputs.forEach((input) => input.addEventListener('change', applyFilters)); assignee?.addEventListener('change', applyFilters);
		document.querySelector('[data-cpb-reset-filters]')?.addEventListener('click', () => { areaInputs.forEach((input) => { input.checked = true; }); if (assignee) assignee.value = 'all'; applyFilters(); });
		applyFilters();
	}

	document.querySelectorAll('[data-cpb-import-project]').forEach((project) => {
		const row = project.closest('tr'); const area = row?.querySelector('[data-cpb-import-area]');
		const filterAreas = () => { if (!area) return; Array.from(area.options).forEach((option) => { option.hidden = !!option.dataset.project && option.dataset.project !== project.value; }); if (area.selectedOptions[0]?.hidden) area.value = '0'; };
		project.addEventListener('change', filterAreas); filterAreas();
	});

	document.querySelectorAll('[data-cpb-import-bulk]').forEach((bulk) => {
		const form = bulk.closest('form');
		const rows = Array.from(form?.querySelectorAll('[data-cpb-import-row]') || []);
		const selections = rows.map((row) => row.querySelector('[data-cpb-import-select]')).filter(Boolean);
		const selectAll = form?.querySelector('[data-cpb-import-select-all]');
		const count = bulk.querySelector('[data-cpb-import-selected-count]');
		const project = bulk.querySelector('[data-cpb-import-bulk-project]');
		const area = bulk.querySelector('[data-cpb-import-bulk-area]');
		const assignee = bulk.querySelector('[data-cpb-import-bulk-assignee]');
		const priority = bulk.querySelector('[data-cpb-import-bulk-priority]');
		const visibility = bulk.querySelector('[data-cpb-import-bulk-visibility]');
		const action = bulk.querySelector('[data-cpb-import-bulk-action]');
		const dueMode = bulk.querySelector('[data-cpb-import-bulk-due-mode]');
		const due = bulk.querySelector('[data-cpb-import-bulk-due]');
		const updateSelection = () => {
			const selected = selections.filter((input) => input.checked).length;
			if (count) count.textContent = String(selected);
			if (selectAll) {
				selectAll.checked = selected === selections.length && selections.length > 0;
				selectAll.indeterminate = selected > 0 && selected < selections.length;
			}
		};
		const filterBulkAreas = () => {
			if (!area || !project) return;
			const hasProject = project.value !== '';
			area.disabled = !hasProject;
			Array.from(area.options).forEach((option) => {
				option.hidden = !!option.dataset.project && (!hasProject || option.dataset.project !== project.value);
			});
			if (!hasProject || area.selectedOptions[0]?.hidden) area.value = '';
		};
		const setSelect = (field, value) => {
			if (!field || value === '') return;
			const option = Array.from(field.options).find((candidate) => candidate.value === value && !candidate.hidden && !candidate.disabled);
			if (option) field.value = value;
		};
		selectAll?.addEventListener('change', () => {
			selections.forEach((input) => { input.checked = selectAll.checked; });
			updateSelection();
		});
		selections.forEach((input) => input.addEventListener('change', updateSelection));
		project?.addEventListener('change', filterBulkAreas);
		dueMode?.addEventListener('change', () => {
			if (!due) return;
			due.disabled = dueMode.value !== 'set';
			if (dueMode.value !== 'set') due.value = '';
		});
		bulk.querySelector('[data-cpb-import-apply-bulk]')?.addEventListener('click', () => {
			rows.forEach((row) => {
				if (!row.querySelector('[data-cpb-import-select]')?.checked) return;
				const rowProject = row.querySelector('[data-cpb-import-project]');
				const rowArea = row.querySelector('[data-cpb-import-area]');
				setSelect(rowProject, project?.value || '');
				if (project?.value !== '') rowProject?.dispatchEvent(new Event('change'));
				setSelect(rowArea, area?.value || '');
				setSelect(row.querySelector('[data-cpb-import-assignee]'), assignee?.value || '');
				setSelect(row.querySelector('[data-cpb-import-priority]'), priority?.value || '');
				setSelect(row.querySelector('[data-cpb-import-visibility]'), visibility?.value || '');
				setSelect(row.querySelector('[data-cpb-import-action]'), action?.value || '');
				const rowDue = row.querySelector('[data-cpb-import-due]');
				if (rowDue && dueMode?.value === 'clear') rowDue.value = '';
				if (rowDue && dueMode?.value === 'set' && due?.value) rowDue.value = due.value;
			});
		});
		filterBulkAreas();
		updateSelection();
	});
})();
