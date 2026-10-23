(() => {
	'use strict';

	document.addEventListener('click', (event) => {
		const opener = event.target.closest('[data-cpb-open]');
		if (opener) {
			const modal = document.getElementById(opener.dataset.cpbOpen);
			if (modal) { modal.hidden = false; modal.querySelector('input,select,textarea,button')?.focus(); }
		}
		if (event.target.closest('[data-cpb-close]') || event.target.classList.contains('cpb-modal')) {
			event.target.closest('.cpb-modal')?.setAttribute('hidden', '');
		}
		const toggle = event.target.closest('[data-cpb-view]');
		if (toggle) {
			const list = document.querySelector('.cpb-list');
			const board = document.querySelector('.cpb-board');
			const showList = toggle.dataset.cpbView === 'list';
			if (list && board) { list.hidden = !showList; board.hidden = showList; }
			document.querySelectorAll('[data-cpb-view]').forEach((button) => button.setAttribute('aria-pressed', String(button === toggle)));
		}
	});

	let dragged = null;
	document.addEventListener('dragstart', (event) => {
		dragged = event.target.closest('[data-cpb-task]');
		if (dragged) dragged.classList.add('is-dragging');
	});
	document.addEventListener('dragend', () => { dragged?.classList.remove('is-dragging'); dragged = null; });
	document.querySelectorAll('[data-cpb-column]').forEach((column) => {
		column.addEventListener('dragover', (event) => { if (dragged) { event.preventDefault(); column.classList.add('is-drop-target'); } });
		column.addEventListener('dragleave', () => column.classList.remove('is-drop-target'));
		column.addEventListener('drop', async (event) => {
			event.preventDefault(); column.classList.remove('is-drop-target');
			if (!dragged || !window.contactPandaBusiness) return;
			const body = new URLSearchParams({ action: 'contactpanda_business_move_task', nonce: contactPandaBusiness.nonce, task_id: dragged.dataset.cpbTask, column: column.dataset.cpbColumn });
			const response = await fetch(contactPandaBusiness.ajaxUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body });
			const result = await response.json();
			if (result.success) { column.querySelector('.cpb-column-body')?.appendChild(dragged); window.location.reload(); }
		});
	});
})();
