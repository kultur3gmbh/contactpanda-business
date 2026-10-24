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
})();
