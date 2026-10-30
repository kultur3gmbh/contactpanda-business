(function () {
	'use strict';
	function escapeHelpdeskHtml(value) { var div = document.createElement('div'); div.textContent = String(value == null ? '' : value); return div.innerHTML; }

	document.addEventListener('click', function (event) {
		var toggle = event.target.closest('[data-contactpanda-helpdesk-toggle]');
		if (!toggle) return;
		var panel = document.getElementById('contactpanda-' + toggle.getAttribute('data-contactpanda-helpdesk-toggle'));
		if (!panel) return;
		event.preventDefault();
		panel.hidden = !panel.hidden;
		if (toggle.getAttribute('data-contactpanda-helpdesk-toggle') === 'new-person') {
			var form = toggle.closest('form');
			var contact = form ? form.querySelector('[data-contactpanda-helpdesk-contact]') : null;
			var email = panel.querySelector('input[name="new_email"]');
			if (!panel.hidden && contact) {
				contact.value = '0';
				resetHelpdeskContext(form);
			}
			if (email) email.required = !panel.hidden;
			if (!panel.hidden) {
				var first = panel.querySelector('input[name="new_first_name"]');
				if (first) first.focus();
			}
		}
	});

	function resetHelpdeskContext(form, message) {
		if (!form) return;
		var related = form.querySelector('[data-contactpanda-helpdesk-related]');
		var summary = form.querySelector('[data-contactpanda-helpdesk-context-summary]');
		var typeSelect = form.querySelector('[data-contactpanda-helpdesk-related-type]');
		var counts = form.querySelector('[data-contactpanda-helpdesk-counts]');
		var preview = form.querySelector('[data-contactpanda-helpdesk-preview]');
		var previewPanel = form.querySelector('[data-contactpanda-helpdesk-relation-preview]');
		if (related) {
			while (related.options.length > 1) related.remove(1);
			related.selectedIndex = 0;
			related.disabled = true;
		}
		if (summary) summary.textContent = message || summary.getAttribute('data-default-label') || '';
		if (typeSelect) { typeSelect.innerHTML = '<option value="">' + (typeSelect.dataset.allLabel || 'All related records') + '</option>'; typeSelect.disabled = true; }
		if (counts) counts.innerHTML = '';
		if (preview) preview.disabled = true;
		if (previewPanel) { previewPanel.hidden = true; previewPanel.textContent = ''; }
		form._contactPandaRelations = [];
	}

	function loadHelpdeskCustomerContext(form, contactId) {
		var related = form.querySelector('[data-contactpanda-helpdesk-related]');
		var summary = form.querySelector('[data-contactpanda-helpdesk-context-summary]');
		if (!related || !summary || !contactId || contactId === '0') {
			resetHelpdeskContext(form);
			return;
		}
		resetHelpdeskContext(form, form.getAttribute('data-context-loading') || '');
		form.dataset.contextContactId = contactId;
		var body = new window.URLSearchParams();
		body.set('action', 'contactpanda_pro_support_customer_context');
		body.set('nonce', form.getAttribute('data-context-nonce') || '');
		body.set('contact_id', contactId);
		window.fetch(form.getAttribute('data-context-url') || window.ajaxurl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		}).then(function (response) {
			return response.json();
		}).then(function (response) {
			if (form.dataset.contextContactId !== contactId) return;
			if (!response || !response.success || !response.data) throw new Error();
			form._contactPandaRelations = response.data.relations || [];
			var typeSelect = form.querySelector('[data-contactpanda-helpdesk-related-type]');
			var seenTypes = {};
			form._contactPandaRelations.forEach(function (relation) {
				var option = document.createElement('option');
				option.value = String(relation.type || '') + '|' + encodeURIComponent(String(relation.id || ''));
				option.textContent = relation.label || relation.id || '';
				option.dataset.type = relation.type || '';
				option.dataset.details = relation.details || relation.label || '';
				related.appendChild(option);
				seenTypes[relation.type || 'other'] = true;
			});
			if (typeSelect) {
				Object.keys(seenTypes).sort().forEach(function (type) { var option = document.createElement('option'); option.value = type; option.textContent = (response.data.type_labels || {})[type] || type.charAt(0).toUpperCase() + type.slice(1); typeSelect.appendChild(option); });
				typeSelect.disabled = false;
			}
			var counts = form.querySelector('[data-contactpanda-helpdesk-counts]');
			if (counts) counts.innerHTML = Object.keys(response.data.counts || {}).map(function (type) { return '<span><strong>' + Number(response.data.counts[type] || 0) + '</strong> ' + escapeHelpdeskHtml((response.data.type_labels || {})[type] || type) + '</span>'; }).join('');
			related.disabled = false;
			summary.textContent = response.data.summary || '';
		}).catch(function () {
			if (form.dataset.contextContactId !== contactId) return;
			resetHelpdeskContext(form, form.getAttribute('data-context-error') || '');
		});
	}

	document.addEventListener('change', function (event) {
		if (!event.target.matches('[data-contactpanda-helpdesk-contact]')) return;
		var option = event.target.options[event.target.selectedIndex];
		var form = event.target.closest('form');
		if (!form || !option) return;
		var summary = form.querySelector('[data-contactpanda-helpdesk-person-summary]');
		var newPerson = form.querySelector('#contactpanda-new-person');
		var name = option.getAttribute('data-name') || '';
		var email = option.getAttribute('data-email') || '';
		if (summary) {
			summary.textContent = event.target.value !== '0'
				? [name, email].filter(Boolean).join(' — ')
				: (summary.getAttribute('data-default-label') || '');
		}
		if (newPerson && event.target.value !== '0') {
			newPerson.hidden = true;
			newPerson.querySelectorAll('input').forEach(function (input) { input.value = ''; input.required = false; input.setCustomValidity(''); });
		}
		loadHelpdeskCustomerContext(form, event.target.value);
	});

	document.addEventListener('input', function (event) {
		if (!event.target.matches('[data-contactpanda-helpdesk-person-search]')) return;
		var form = event.target.closest('form'); var select = form ? form.querySelector('[data-contactpanda-helpdesk-contact]') : null; if (!select) return;
		var query = event.target.value.trim().toLowerCase();
		Array.prototype.forEach.call(select.options, function (option, index) { if (index === 0) return; option.hidden = !!query && option.text.toLowerCase().indexOf(query) === -1; });
		var firstVisible = Array.prototype.find.call(select.options, function (option, index) { return index > 0 && !option.hidden; });
		if (query && firstVisible) { select.value = firstVisible.value; select.dispatchEvent(new Event('change', {bubbles: true})); }
	});

	document.addEventListener('change', function (event) {
		if (event.target.matches('[data-contactpanda-helpdesk-related-type]')) {
			var form = event.target.closest('form'); var related = form.querySelector('[data-contactpanda-helpdesk-related]'); var type = event.target.value;
			Array.prototype.forEach.call(related.options, function (option, index) { option.hidden = index > 0 && !!type && option.dataset.type !== type; });
			related.value = ''; related.dispatchEvent(new Event('change', {bubbles: true}));
		}
		if (event.target.matches('[data-contactpanda-helpdesk-related]')) {
			var form = event.target.closest('form'); var option = event.target.options[event.target.selectedIndex]; var button = form.querySelector('[data-contactpanda-helpdesk-preview]'); var panel = form.querySelector('[data-contactpanda-helpdesk-relation-preview]');
			if (button) button.disabled = !event.target.value;
			if (panel) { panel.hidden = true; panel.textContent = option ? (option.dataset.details || '') : ''; }
		}
	});

	document.addEventListener('click', function (event) {
		var button = event.target.closest('[data-contactpanda-helpdesk-preview]'); if (!button) return; event.preventDefault();
		var panel = button.closest('form').querySelector('[data-contactpanda-helpdesk-relation-preview]'); if (panel) panel.hidden = !panel.hidden;
	});

	document.addEventListener('submit', function (event) {
		var form = event.target.closest('[data-contactpanda-helpdesk-create]');
		if (!form) return;
		var contact = form.querySelector('[data-contactpanda-helpdesk-contact]');
		if (contact && contact.value !== '0') return;
		var panel = form.querySelector('#contactpanda-new-person');
		if (!panel) return;
		panel.hidden = false;
		var first = panel.querySelector('input[name="new_first_name"]');
		var last = panel.querySelector('input[name="new_last_name"]');
		var email = panel.querySelector('input[name="new_email"]');
		if (email) email.required = true;
		if (first) first.setCustomValidity((first.value.trim() || (last && last.value.trim())) ? '' : (form.getAttribute('data-name-required') || 'Please enter a name.'));
		if (!form.checkValidity()) {
			event.preventDefault();
			form.reportValidity();
		}
	});

	document.addEventListener('input', function (event) {
		if (!event.target.matches('[name="new_first_name"], [name="new_last_name"]')) return;
		var panel = event.target.closest('#contactpanda-new-person');
		if (!panel) return;
		var first = panel.querySelector('input[name="new_first_name"]');
		var last = panel.querySelector('input[name="new_last_name"]');
		if (first && (first.value.trim() || (last && last.value.trim()))) first.setCustomValidity('');
	});

	function closeModal(modal) {
		if (!modal) return;
		modal.hidden = true;
		document.documentElement.classList.remove('contactpanda-pro-modal-open');
	}

	function openModal(modal, record) {
		if (!modal) return;
		var form = modal.querySelector('form');
		if (form) {
			form.reset();
			var newPerson = form.querySelector('#contactpanda-new-person');
			if (newPerson) {
				newPerson.hidden = true;
				newPerson.querySelectorAll('input').forEach(function (input) { input.required = false; input.setCustomValidity(''); });
			}
			var personSummary = form.querySelector('[data-contactpanda-helpdesk-person-summary]');
			if (personSummary) personSummary.textContent = personSummary.getAttribute('data-default-label') || '';
			resetHelpdeskContext(form);
			Object.keys(record || {}).forEach(function (key) {
				var field = form.elements.namedItem(key);
				if (!field) return;
				if (field.type === 'checkbox') field.checked = !!record[key];
				else field.value = record[key] === null ? '' : record[key];
			});
		}
		modal.hidden = false;
		document.documentElement.classList.add('contactpanda-pro-modal-open');
		var focus = modal.querySelector('input:not([type="hidden"]), select, textarea, button');
		if (focus) window.setTimeout(function () { focus.focus(); }, 20);
	}

	document.addEventListener('click', function (event) {
		var opener = event.target.closest('[data-contactpanda-pro-modal-open]');
		if (opener) {
			event.preventDefault();
			var modal = document.getElementById(opener.getAttribute('data-contactpanda-pro-modal-open'));
			var record = {};
			try { record = JSON.parse(opener.getAttribute('data-contactpanda-pro-record') || '{}'); } catch (ignore) {}
			openModal(modal, record);
			return;
		}
		var closer = event.target.closest('[data-contactpanda-pro-modal-close]');
		if (closer) {
			event.preventDefault();
			closeModal(closer.closest('.contactpanda-pro-modal'));
		}
	});

	document.addEventListener('keydown', function (event) {
		if (event.key !== 'Escape') return;
		closeModal(document.querySelector('.contactpanda-pro-modal:not([hidden])'));
	});

	/* Native selects stay accessible; typing while focused jumps to matching people. */
	document.addEventListener('keydown', function (event) {
		if (!event.target.matches('.contactpanda-pro-search-select') || event.ctrlKey || event.metaKey || event.altKey) return;
		if (event.key.length !== 1) return;
		var select = event.target;
		select.dataset.search = (select.dataset.search || '') + event.key.toLowerCase();
		window.clearTimeout(select._contactPandaSearchTimer);
		select._contactPandaSearchTimer = window.setTimeout(function () { select.dataset.search = ''; }, 900);
		for (var i = 0; i < select.options.length; i += 1) {
			if (select.options[i].text.toLowerCase().indexOf(select.dataset.search) !== -1) {
				select.selectedIndex = i;
				break;
			}
		}
	});

	var invoiceBuilder = document.querySelector('[data-contactpanda-invoice-builder]');
	document.addEventListener('click', function (event) {
		var add = event.target.closest('[data-contactpanda-add-support-point]');
		if (add) {
			var list = document.querySelector('[data-contactpanda-support-points]'); var first = list && list.querySelector('[data-contactpanda-support-point]');
			if (!list || !first) return;
			var clone = first.cloneNode(true); clone.querySelectorAll('input,textarea').forEach(function (field) { field.value = ''; });
			var remove = document.createElement('button'); remove.type = 'button'; remove.className = 'button-link-delete'; remove.setAttribute('data-contactpanda-remove-support-point', '1'); remove.innerHTML = '<span class="dashicons dashicons-trash" aria-hidden="true"></span> Remove point'; clone.appendChild(remove); list.insertBefore(clone, add); clone.querySelector('input').focus();
		}
		var removePoint = event.target.closest('[data-contactpanda-remove-support-point]'); if (removePoint) removePoint.closest('[data-contactpanda-support-point]').remove();
	});

	if (invoiceBuilder) {
		var invoicePage = invoiceBuilder.querySelector('[data-contactpanda-invoice-page]');
		var invoiceBlocksInput = invoiceBuilder.querySelector('[data-contactpanda-invoice-blocks]');
		var inspector = invoiceBuilder.querySelector('[data-contactpanda-invoice-inspector-fields]');
		var noSelection = invoiceBuilder.querySelector('[data-contactpanda-invoice-no-selection]');
		var tableSettings = invoiceBuilder.querySelector('[data-contactpanda-invoice-table-settings]');
		var structureSettings = null;
		var invoiceI18n = window.contactPandaProInvoice || {};
		var millimetreScale = 3;
		var blocks = [];
		var selectedBlockId = '';
		var activeInspectorTab = 'content';
		var pageSize = {width: 210, height: 297};
		var pointerOperation = null;

		function label(key, fallback) { return invoiceI18n[key] || fallback; }
		function number(value, fallback) { value = Number(value); return Number.isFinite(value) ? value : fallback; }
		function clamp(value, minimum, maximum) { return Math.min(maximum, Math.max(minimum, value)); }
		function roundMm(value) { return Math.round(value * 10) / 10; }
		function snap(value) {
			var control = invoiceBuilder.elements.snap_to_grid;
			if (!control || !control.checked) return roundMm(value);
			var size = Math.max(1, number(invoiceBuilder.elements.grid_size_mm && invoiceBuilder.elements.grid_size_mm.value, 5));
			return roundMm(Math.round(value / size) * size);
		}

		function installBuilderControls() {
			structureSettings = inspector.querySelector('[data-contactpanda-invoice-structure-settings]');
			if (window.ContactPandaAdminUI && window.ContactPandaAdminUI.enhance) window.ContactPandaAdminUI.enhance(inspector);
			var imageMount = inspector.querySelector('[data-contactpanda-invoice-image-picker]');
			if (imageMount && window.ContactPandaAdminUI && window.ContactPandaAdminUI.createImagePicker) {
				imageMount.appendChild(window.ContactPandaAdminUI.createImagePicker({dropLabel: label('chooseImage', 'Choose image'), removeLabel: label('removeImage', 'Remove image'), onChange: function (image) {
					var block = selectedBlock(); if (!block) return;
					block.image_id = image ? image.id : 0; block.image_url = image ? image.url : ''; renderInvoiceBlocks();
				}}));
			}
			var focusStage = inspector.querySelector('[data-contactpanda-invoice-focus-stage]');
			if (focusStage) focusStage.addEventListener('pointerdown', function (event) {
				var block = selectedBlock(); if (!block || !block.image_url) return; var bounds = focusStage.getBoundingClientRect();
				block.focus_x = Math.round(clamp((event.clientX - bounds.left) / bounds.width * 100, 0, 100)); block.focus_y = Math.round(clamp((event.clientY - bounds.top) / bounds.height * 100, 0, 100));
				renderInvoiceBlocks(); showInspector(); event.preventDefault();
			});
		}

		function blockTypeLabel(type) {
			var choice = invoiceBuilder.querySelector('[data-contactpanda-invoice-add="' + type + '"]');
			var text = choice ? choice.querySelector('span:last-child') : null;
			return text ? text.textContent.trim() : type;
		}

		function setInspectorTab(tab) {
			activeInspectorTab = ['content', 'layout', 'design'].indexOf(tab) === -1 ? 'content' : tab;
			inspector.querySelectorAll('[data-contactpanda-invoice-inspector-tab]').forEach(function (button) {
				var active = button.getAttribute('data-contactpanda-invoice-inspector-tab') === activeInspectorTab;
				button.classList.toggle('is-active', active);
				button.setAttribute('aria-selected', active ? 'true' : 'false');
			});
			inspector.querySelectorAll('[data-contactpanda-invoice-inspector-panel]').forEach(function (panel) {
				var active = panel.getAttribute('data-contactpanda-invoice-inspector-panel') === activeInspectorTab;
				panel.hidden = !active;
				panel.classList.toggle('is-active', active);
			});
		}

		function makeId(type) {
			return type + '-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 7);
		}

		function defaultContent(type) {
			var defaults = {
				logo: '', sender: '', recipient: '', document_meta: '', heading: label('documentHeading', 'Document'), text: label('text', 'Text'),
				items: '', totals: '', payment: '', payment_qr: '', separator: '', footer: '', page_number: '1 / 1', spacer: ''
			};
			return defaults[type] || '';
		}

		function optionLabels(group) { return invoiceI18n[group] || {}; }
		function fieldRecord(key, width) { return {key: key, label: optionLabels('dataFields')[key] || key, width: width || 100, align: 'left', show_label: false, prefix: '', suffix: '', line_break_after: true}; }
		function columnRecord(key, width) { return {key: key, label: optionLabels('columnFields')[key] || key, width: width || 15, align: key === 'sku' || key === 'description' ? 'left' : 'right'}; }
		function summaryRecord(key, emphasis) { return {key: key, label: optionLabels('summaryFields')[key] || key, emphasis: emphasis || 'normal'}; }
		function defaultFields(type) {
			var map = {
				sender: ['company_name', 'company_address', 'company_tax_number'],
				recipient: ['customer_name', 'customer_company', 'customer_address', 'shipping_address'],
				document_meta: ['document_number', 'document_date', 'due_date', 'order_number'],
				payment: ['payment_method', 'payment_information']
			};
			return (map[type] || []).map(function (key) { return fieldRecord(key); });
		}
		function defaultColumns() { return [['description', 36], ['quantity', 10], ['unit_price', 14], ['tax', 10], ['total', 16]].map(function (item) { return columnRecord(item[0], item[1]); }); }
		function defaultSummaryRows() { return [summaryRecord('subtotal'), summaryRecord('tax_total'), summaryRecord('grand_total', 'strong')]; }
		function isFieldBlock(type) { return ['sender', 'recipient', 'document_meta', 'payment'].indexOf(type) !== -1; }
		function ensureBlockStructure(block) {
			if (isFieldBlock(block.type) && (!Array.isArray(block.fields) || !block.fields.length)) block.fields = defaultFields(block.type);
			if (!Array.isArray(block.table_columns) || !block.table_columns.length) block.table_columns = defaultColumns();
			if (!Array.isArray(block.summary_rows) || !block.summary_rows.length) block.summary_rows = defaultSummaryRows();
			if (!block.group_by) block.group_by = 'none';
			block.show_subtotals = !!block.show_subtotals;
			if (!block.tax_display) block.tax_display = 'combined';
			block.show_qr_details = !!block.show_qr_details;
			if (!block.separator_style) block.separator_style = 'solid';
			if (!block.separator_color) block.separator_color = '#1d2327';
			if (!block.separator_width_mm) block.separator_width_mm = 0.3;
			if (Array.isArray(block.fields)) block.fields = block.fields.map(function (field) {
				field.prefix = field.prefix || '';
				field.suffix = field.suffix || '';
				if (field.line_break_after === undefined) field.line_break_after = true;
				return field;
			});
			return block;
		}

		function createBlock(type, x, y) {
			return ensureBlockStructure({ id: makeId(type), type: type, content: defaultContent(type), data_field: '', width: '100', x_mm: snap(number(x, 15)), y_mm: snap(number(y, 15)), width_mm: type === 'logo' || type === 'payment_qr' ? 55 : 90, height_mm: type === 'items' ? 50 : (type === 'logo' || type === 'payment_qr' ? 35 : (type === 'separator' ? 6 : 20)), font_size: type === 'heading' ? 20 : (type === 'footer' ? 8 : 10), font_weight: type === 'heading' ? 'bold' : 'normal', align: type === 'totals' ? 'right' : (type === 'page_number' || type === 'footer' ? 'center' : 'left'), text_color: '#1d2327', background_color: 'rgba(255,255,255,0)', padding: 8, padding_mm: 2, border_width_mm: 0, border_radius_mm: 0, border_color: '#d9dfe6', image_id: 0, image_url: '', image_fit: 'contain', focus_x: 50, focus_y: 50, table_header_color: '#4253b5', table_odd_color: '#ffffff', table_even_color: '#f3f5f7', tax_display: 'combined', show_qr_details: false, separator_style: 'solid', separator_color: '#1d2327', separator_width_mm: 0.3 });
		}

		function selectedBlock() {
			return blocks.find(function (block) { return block.id === selectedBlockId; }) || null;
		}

		function syncInvoiceInput() {
			invoiceBlocksInput.value = JSON.stringify(blocks);
		}

		function escapeHtml(value) {
			return String(value || '').replace(/[&<>"']/g, function (character) {
				return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[character];
			});
		}

		function safeRichText(value) {
			var template = document.createElement('template');
			template.innerHTML = String(value || '');
			var allowed = ['BR', 'STRONG', 'B', 'EM', 'I', 'U'];
			Array.prototype.slice.call(template.content.querySelectorAll('*')).forEach(function (node) {
				if (allowed.indexOf(node.tagName) === -1) node.replaceWith(document.createTextNode(node.textContent || ''));
				else Array.prototype.slice.call(node.attributes).forEach(function (attribute) { node.removeAttribute(attribute.name); });
			});
			return template.innerHTML.replace(/\n/g, '<br>');
		}

		function selectOptions(options, selected, excluded) {
			return Object.keys(options).filter(function (key) { return !excluded || excluded.indexOf(key) === -1 || key === selected; }).map(function (key) {
				return '<option value="' + escapeHtml(key) + '"' + (key === selected ? ' selected' : '') + '>' + escapeHtml(options[key]) + '</option>';
			}).join('');
		}

		function rowButtons(kind, index, length) {
			return '<span class="contactpanda-invoice-structure-actions"><button type="button" class="contactpanda-pro-icon-button" data-invoice-structure-move="up" data-structure-kind="' + kind + '" data-structure-index="' + index + '"' + (index === 0 ? ' disabled' : '') + ' aria-label="' + escapeHtml(label('moveUp', 'Move up')) + '"><span class="dashicons dashicons-arrow-up-alt2"></span></button><button type="button" class="contactpanda-pro-icon-button" data-invoice-structure-move="down" data-structure-kind="' + kind + '" data-structure-index="' + index + '"' + (index === length - 1 ? ' disabled' : '') + ' aria-label="' + escapeHtml(label('moveDown', 'Move down')) + '"><span class="dashicons dashicons-arrow-down-alt2"></span></button><button type="button" class="contactpanda-pro-icon-button is-danger" data-invoice-structure-remove data-structure-kind="' + kind + '" data-structure-index="' + index + '" aria-label="' + escapeHtml(label('remove', 'Remove')) + '"><span class="dashicons dashicons-trash"></span></button></span>';
		}

		function addControl(kind, options, used, buttonLabel) {
			var available = Object.keys(options).filter(function (key) { return used.indexOf(key) === -1; });
			if (!available.length) return '';
			return '<div class="contactpanda-invoice-structure-add"><select data-invoice-structure-add-select="' + kind + '">' + available.map(function (key) { return '<option value="' + escapeHtml(key) + '">' + escapeHtml(options[key]) + '</option>'; }).join('') + '</select><button type="button" class="button" data-invoice-structure-add="' + kind + '"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span><span>' + escapeHtml(buttonLabel) + '</span></button></div>';
		}

		function renderStructureInspector(block) {
			if (!structureSettings) return;
			var html = '';
			if (isFieldBlock(block.type)) {
				var fields = block.fields || [];
				html = '<h4>' + escapeHtml(label('blockContents', 'Contents in this block')) + '</h4><div class="contactpanda-invoice-structure-list">' + fields.map(function (field, index) {
					return '<article class="contactpanda-invoice-structure-row" draggable="true" data-structure-draggable="fields" data-structure-index="' + index + '"><span class="contactpanda-invoice-structure-drag dashicons dashicons-move" aria-hidden="true"></span><div class="contactpanda-invoice-structure-main"><select data-structure-kind="fields" data-structure-index="' + index + '" data-structure-property="key">' + selectOptions(optionLabels('dataFields'), field.key) + '</select><input type="text" value="' + escapeHtml(field.label) + '" data-structure-kind="fields" data-structure-index="' + index + '" data-structure-property="label" aria-label="' + escapeHtml(label('fieldLabel', 'Label')) + '"></div><div class="contactpanda-invoice-structure-affixes"><label><span>' + escapeHtml(label('prefix', 'Text before')) + '</span><input type="text" value="' + escapeHtml(field.prefix) + '" data-structure-kind="fields" data-structure-index="' + index + '" data-structure-property="prefix"></label><label><span>' + escapeHtml(label('suffix', 'Text after')) + '</span><input type="text" value="' + escapeHtml(field.suffix) + '" data-structure-kind="fields" data-structure-index="' + index + '" data-structure-property="suffix"></label></div><div class="contactpanda-invoice-structure-meta"><label><span>' + escapeHtml(label('fieldWidth', 'Width (%)')) + '</span><input type="number" min="10" max="100" value="' + number(field.width, 100) + '" data-structure-kind="fields" data-structure-index="' + index + '" data-structure-property="width"></label><label><span>' + escapeHtml(label('alignment', 'Alignment')) + '</span><select data-structure-kind="fields" data-structure-index="' + index + '" data-structure-property="align"><option value="left"' + (field.align === 'left' ? ' selected' : '') + '>' + escapeHtml(label('left', 'Left')) + '</option><option value="center"' + (field.align === 'center' ? ' selected' : '') + '>' + escapeHtml(label('centered', 'Centered')) + '</option><option value="right"' + (field.align === 'right' ? ' selected' : '') + '>' + escapeHtml(label('right', 'Right')) + '</option></select></label><label class="contactpanda-invoice-inline-check"><input type="checkbox"' + (field.show_label ? ' checked' : '') + ' data-structure-kind="fields" data-structure-index="' + index + '" data-structure-property="show_label"><span>' + escapeHtml(label('showLabel', 'Show label')) + '</span></label><label class="contactpanda-invoice-inline-check"><input type="checkbox"' + (field.line_break_after ? ' checked' : '') + ' data-structure-kind="fields" data-structure-index="' + index + '" data-structure-property="line_break_after"><span>' + escapeHtml(label('lineBreakAfter', 'New line after field')) + '</span></label>' + rowButtons('fields', index, fields.length) + '</div></article>';
				}).join('') + '</div>' + addControl('fields', optionLabels('dataFields'), fields.map(function (field) { return field.key; }), label('addField', 'Add field'));
			} else if (block.type === 'items') {
				var columns = block.table_columns || [];
				html = '<h4>' + escapeHtml(label('tableColumns', 'Columns in this table')) + '</h4><div class="contactpanda-invoice-structure-list">' + columns.map(function (column, index) {
					return '<article class="contactpanda-invoice-structure-row"><div class="contactpanda-invoice-structure-main"><select data-structure-kind="table_columns" data-structure-index="' + index + '" data-structure-property="key">' + selectOptions(optionLabels('columnFields'), column.key) + '</select><input type="text" value="' + escapeHtml(column.label) + '" data-structure-kind="table_columns" data-structure-index="' + index + '" data-structure-property="label" aria-label="' + escapeHtml(label('fieldLabel', 'Label')) + '"></div><div class="contactpanda-invoice-structure-meta"><label><span>' + escapeHtml(label('fieldWidth', 'Width (%)')) + '</span><input type="number" min="5" max="70" value="' + number(column.width, 15) + '" data-structure-kind="table_columns" data-structure-index="' + index + '" data-structure-property="width"></label><select data-structure-kind="table_columns" data-structure-index="' + index + '" data-structure-property="align" aria-label="' + escapeHtml(label('alignment', 'Alignment')) + '"><option value="left"' + (column.align === 'left' ? ' selected' : '') + '>' + escapeHtml(label('left', 'Left')) + '</option><option value="center"' + (column.align === 'center' ? ' selected' : '') + '>' + escapeHtml(label('centered', 'Centered')) + '</option><option value="right"' + (column.align === 'right' ? ' selected' : '') + '>' + escapeHtml(label('right', 'Right')) + '</option></select>' + rowButtons('table_columns', index, columns.length) + '</div></article>';
				}).join('') + '</div>' + addControl('table_columns', optionLabels('columnFields'), columns.map(function (column) { return column.key; }), label('addColumn', 'Add column')) + '<div class="contactpanda-invoice-group-controls"><label><span>' + escapeHtml(label('groupItems', 'Group positions by')) + '</span><select data-invoice-block-option="group_by"><option value="none"' + (block.group_by === 'none' ? ' selected' : '') + '>' + escapeHtml(label('noGrouping', 'No grouping')) + '</option><option value="category"' + (block.group_by === 'category' ? ' selected' : '') + '>' + escapeHtml(label('category', 'Category')) + '</option><option value="tax"' + (block.group_by === 'tax' ? ' selected' : '') + '>' + escapeHtml(label('taxRate', 'Tax rate')) + '</option></select></label><label class="contactpanda-invoice-inline-check"><input type="checkbox" data-invoice-block-option="show_subtotals"' + (block.show_subtotals ? ' checked' : '') + '><span>' + escapeHtml(label('showSubtotals', 'Show subtotal for each group')) + '</span></label></div>';
			} else if (block.type === 'totals') {
				var rows = block.summary_rows || [];
				html = '<h4>' + escapeHtml(label('summaryRows', 'Rows in this total')) + '</h4><div class="contactpanda-invoice-structure-list">' + rows.map(function (row, index) {
					return '<article class="contactpanda-invoice-structure-row"><div class="contactpanda-invoice-structure-main"><select data-structure-kind="summary_rows" data-structure-index="' + index + '" data-structure-property="key">' + selectOptions(optionLabels('summaryFields'), row.key) + '</select><input type="text" value="' + escapeHtml(row.label) + '" data-structure-kind="summary_rows" data-structure-index="' + index + '" data-structure-property="label" aria-label="' + escapeHtml(label('fieldLabel', 'Label')) + '"></div><div class="contactpanda-invoice-structure-meta"><select data-structure-kind="summary_rows" data-structure-index="' + index + '" data-structure-property="emphasis"><option value="normal"' + (row.emphasis !== 'strong' ? ' selected' : '') + '>' + escapeHtml(label('normalEmphasis', 'Normal emphasis')) + '</option><option value="strong"' + (row.emphasis === 'strong' ? ' selected' : '') + '>' + escapeHtml(label('strongEmphasis', 'Strong emphasis')) + '</option></select>' + rowButtons('summary_rows', index, rows.length) + '</div></article>';
				}).join('') + '</div>' + addControl('summary_rows', optionLabels('summaryFields'), rows.map(function (row) { return row.key; }), label('addTotal', 'Add total row')) + '<div class="contactpanda-invoice-group-controls"><label><span>' + escapeHtml(label('taxDisplay', 'Tax display')) + '</span><select data-invoice-block-option="tax_display"><option value="combined"' + (block.tax_display === 'combined' ? ' selected' : '') + '>' + escapeHtml(label('taxCombined', 'Combined tax total')) + '</option><option value="by_rate"' + (block.tax_display === 'by_rate' ? ' selected' : '') + '>' + escapeHtml(label('taxByRate', 'Separate by tax rate')) + '</option></select></label></div>';
			} else if (block.type === 'payment_qr') {
				html = '<label class="contactpanda-invoice-inline-check"><input type="checkbox" data-invoice-block-option="show_qr_details"' + (block.show_qr_details ? ' checked' : '') + '><span>' + escapeHtml(label('showQrDetails', 'Show readable bank and payment details beside the QR code')) + '</span></label>';
			} else if (block.type === 'separator') {
				html = '<div class="contactpanda-invoice-group-controls"><label><span>' + escapeHtml(label('lineStyle', 'Line style')) + '</span><select data-invoice-block-option="separator_style"><option value="solid"' + (block.separator_style === 'solid' ? ' selected' : '') + '>' + escapeHtml(label('solid', 'Solid')) + '</option><option value="dashed"' + (block.separator_style === 'dashed' ? ' selected' : '') + '>' + escapeHtml(label('dashed', 'Dashed')) + '</option><option value="dotted"' + (block.separator_style === 'dotted' ? ' selected' : '') + '>' + escapeHtml(label('dotted', 'Dotted')) + '</option><option value="double"' + (block.separator_style === 'double' ? ' selected' : '') + '>' + escapeHtml(label('double', 'Double')) + '</option></select></label><label><span>' + escapeHtml(label('lineWidth', 'Line width (mm)')) + '</span><input type="number" min="0.1" max="5" step="0.1" value="' + number(block.separator_width_mm, 0.3) + '" data-invoice-block-option="separator_width_mm"></label><label><span>' + escapeHtml(label('lineColor', 'Line color')) + '</span><input type="color" value="' + escapeHtml(block.separator_color) + '" data-invoice-block-option="separator_color"></label></div>';
			}
			structureSettings.innerHTML = html;
			structureSettings.hidden = !html;
		}

		function placeholderSample(key) {
			var samples = {company_name: 'Example Ltd.', company_address: 'Example Street 1 · 1010 Vienna', company_tax_number: 'ATU12345678', company_email: 'office@example.com', company_phone: '+43 1 234 56 78', company_website: 'www.example.com', company_register: 'FN 123456a', company_bank: 'IBAN AT00 0000 0000 0000 0000', company_legal: 'Example Ltd. · Vienna', customer_name: 'Max Example', customer_company: 'Customer Ltd.', customer_address: 'Customer Street 5 · 1020 Vienna', shipping_address: 'Delivery Street 7 · 1030 Vienna', customer_email: 'max@example.com', customer_phone: '+43 1 234 56 78', document_number: 'INV-2026-001', document_date: '27.10.2026', due_date: '10.11.2026', order_number: '#1042', order_date: '26.10.2026', payment_method: optionLabels('dataFields').payment_method, payment_information: optionLabels('dataFields').payment_information, currency: 'EUR', order_discount: '10,00 €', order_shipping: '6,90 €', order_tax: '31,67 €', order_total: '196,90 €'};
			return samples[key] || optionLabels('dataFields')[key] || key;
		}

		function renderBlockContent(block) {
			if (block.type === 'items') {
				var samples = {sku: ['A-100', 'B-200'], description: [invoicePage.dataset.serviceLabel, invoicePage.dataset.additionalItemLabel], quantity: ['1', '2'], unit: ['Stk.', 'Std.'], unit_price: ['100,00', '50,00'], discount: ['—', '10%'], tax: ['20%', '20%'], total: ['100,00', '90,00']};
				var columns = block.table_columns || defaultColumns();
				var heading = columns.map(function (column) { return '<th style="width:' + number(column.width, 15) + '%;text-align:' + escapeHtml(column.align || 'left') + '">' + escapeHtml(column.label) + '</th>'; }).join('');
				var row = function (rowIndex) { return columns.map(function (column) { return '<td style="text-align:' + escapeHtml(column.align || 'left') + '">' + escapeHtml((samples[column.key] || ['—', '—'])[rowIndex]) + '</td>'; }).join(''); };
				var groupLabel = block.group_by === 'category' ? label('category', 'Category') : (block.group_by === 'tax' ? label('taxRate', 'Tax rate') : '');
				var groupRow = groupLabel ? '<tr class="contactpanda-invoice-table-group"><td colspan="' + columns.length + '">' + escapeHtml(groupLabel) + ': ' + escapeHtml(block.group_by === 'tax' ? '20%' : invoicePage.dataset.serviceLabel) + '</td></tr>' : '';
				var subtotalRow = block.show_subtotals ? '<tr class="contactpanda-invoice-table-subtotal"><td colspan="' + Math.max(1, columns.length - 1) + '">' + escapeHtml((optionLabels('summaryFields').subtotal || label('subtotal', 'Subtotal'))) + '</td><td>190,00</td></tr>' : '';
				return '<table><thead style="background:' + block.table_header_color + '"><tr>' + heading + '</tr></thead><tbody>' + groupRow + '<tr style="background:' + block.table_odd_color + '">' + row(0) + '</tr><tr style="background:' + block.table_even_color + '">' + row(1) + '</tr>' + subtotalRow + '</tbody></table>';
			}
			if (isFieldBlock(block.type)) return '<span class="contactpanda-invoice-field-grid">' + (block.fields || []).map(function (field) { return '<span class="contactpanda-invoice-data-field" style="flex-basis:' + number(field.width, 100) + '%;text-align:' + escapeHtml(field.align || 'left') + '">' + (field.show_label ? '<small>' + escapeHtml(field.label) + '</small>' : '') + '<span>' + escapeHtml(field.prefix || '') + escapeHtml(placeholderSample(field.key)) + escapeHtml(field.suffix || '') + '</span></span>' + (field.line_break_after ? '<span class="contactpanda-invoice-field-break"></span>' : ''); }).join('') + '</span>';
			if (block.type === 'totals') return '<span class="contactpanda-invoice-summary">' + (block.summary_rows || defaultSummaryRows()).map(function (row) { if (row.key === 'tax_total' && block.tax_display === 'by_rate') return '<span class="contactpanda-invoice-summary-row"><span>' + escapeHtml(row.label) + ' 10%</span><span>5,00 €</span></span><span class="contactpanda-invoice-summary-row"><span>' + escapeHtml(row.label) + ' 20%</span><span>26,67 €</span></span>'; return '<span class="contactpanda-invoice-summary-row' + (row.emphasis === 'strong' ? ' is-strong' : '') + '"><span>' + escapeHtml(row.label) + '</span><span>' + escapeHtml(row.key === 'grand_total' ? '190,00 €' : row.key === 'tax_total' ? '31,67 €' : '158,33 €') + '</span></span>'; }).join('') + '</span>';
			if (block.type === 'payment_qr') return '<span class="contactpanda-invoice-qr-preview"><span class="dashicons dashicons-visibility"></span><strong>SEPA / EPC QR</strong><small>INV-2026-001 · 190,00 EUR</small>' + (block.show_qr_details ? '<span class="contactpanda-invoice-qr-details">IBAN AT00 0000 0000 0000 0000<br>BIC EXAMPLEAT<br>Reference INV-2026-001</span>' : '') + '</span>';
			if (block.type === 'logo') {
				if (block.image_url) return '<img src="' + escapeHtml(block.image_url) + '" alt="">';
				return '<span class="dashicons dashicons-format-image"></span> ' + escapeHtml(label('chooseImage', 'Choose image'));
			}
			if (block.type === 'spacer') return '<span class="contactpanda-invoice-spacer-line"></span>';
			if (block.type === 'separator') return '<span class="contactpanda-invoice-separator" style="border-top:' + number(block.separator_width_mm, 0.3) + 'mm ' + escapeHtml(block.separator_style || 'solid') + ' ' + escapeHtml(block.separator_color || '#1d2327') + '"></span>';
			if (block.data_field) return escapeHtml(placeholderSample(block.data_field));
			return safeRichText(block.content || '');
		}

		function renderInvoiceBlocks() {
			invoicePage.innerHTML = '';
			if (!blocks.length) invoicePage.innerHTML = '<p class="contactpanda-invoice-empty">' + (invoicePage.getAttribute('data-empty-label') || '') + '</p>';
			blocks.forEach(function (block) {
				var element = document.createElement('div');
				element.className = 'contactpanda-invoice-canvas-block' + (block.id === selectedBlockId ? ' is-selected' : '');
				element.dataset.blockId = block.id;
				element.style.left = number(block.x_mm, 15) * millimetreScale + 'px';
				element.style.top = number(block.y_mm, 15) * millimetreScale + 'px';
				element.style.width = number(block.width_mm, 90) * millimetreScale + 'px';
				element.style.height = number(block.height_mm, 20) * millimetreScale + 'px';
				element.style.fontSize = block.font_size + 'pt';
				element.style.fontWeight = block.font_weight || 'normal';
				element.style.textAlign = block.align;
				element.style.color = block.text_color;
				element.style.backgroundColor = block.background_color;
				element.style.padding = number(block.padding_mm, 2) * millimetreScale + 'px';
				element.style.borderWidth = number(block.border_width_mm, 0) * millimetreScale + 'px';
				element.style.borderColor = block.border_color || '#d9dfe6';
				element.style.borderRadius = number(block.border_radius_mm, 0) * millimetreScale + 'px';
				element.innerHTML = '<span class="contactpanda-invoice-drag-handle dashicons dashicons-move" aria-hidden="true"></span><span class="contactpanda-invoice-block-actions"><button type="button" data-contactpanda-canvas-duplicate aria-label="' + label('duplicate', 'Duplicate') + '"><span class="dashicons dashicons-admin-page"></span></button><button type="button" data-contactpanda-canvas-delete aria-label="' + label('delete', 'Delete') + '"><span class="dashicons dashicons-trash"></span></button></span><span class="contactpanda-invoice-block-content">' + renderBlockContent(block) + '</span><span class="contactpanda-invoice-resize-handle" aria-hidden="true"></span>';
				var image = element.querySelector('img'); if (image) { image.style.objectFit = block.image_fit || 'contain'; image.style.objectPosition = number(block.focus_x, 50) + '% ' + number(block.focus_y, 50) + '%'; }
				invoicePage.appendChild(element);
			});
			syncInvoiceInput();
		}

		function updatePageClass() {
			var format = invoiceBuilder.elements.page_format.value || 'a4';
			var orientation = invoiceBuilder.elements.orientation.value || 'portrait';
			var dimensions = {a4: [210, 297], letter: [215.9, 279.4], legal: [215.9, 355.6]}[format] || [210, 297];
			pageSize = {width: orientation === 'landscape' ? dimensions[1] : dimensions[0], height: orientation === 'landscape' ? dimensions[0] : dimensions[1]};
			invoicePage.className = 'contactpanda-invoice-page is-' + format + ' is-' + orientation;
			invoicePage.style.width = pageSize.width * millimetreScale + 'px'; invoicePage.style.height = pageSize.height * millimetreScale + 'px';
			var gridSize = Math.max(1, number(invoiceBuilder.elements.grid_size_mm && invoiceBuilder.elements.grid_size_mm.value, 5)) * millimetreScale;
			invoicePage.style.setProperty('--contactpanda-invoice-grid', gridSize + 'px');
			invoicePage.style.setProperty('--contactpanda-invoice-center-x', pageSize.width * millimetreScale / 2 + 'px');
			invoicePage.style.setProperty('--contactpanda-invoice-center-y', pageSize.height * millimetreScale / 2 + 'px');
			['top', 'right', 'bottom', 'left'].forEach(function (side) { invoicePage.style.setProperty('--contactpanda-invoice-margin-' + side, Math.max(0, number(invoiceBuilder.elements['margin_' + side].value, 15)) * millimetreScale + 'px'); });
		}

		function showInspector() {
			var block = selectedBlock();
			inspector.hidden = !block;
			noSelection.hidden = !!block;
			if (!block) { if (structureSettings) structureSettings.hidden = true; return; }
			ensureBlockStructure(block);
			var selectionTitle = invoiceBuilder.querySelector('[data-contactpanda-invoice-selection-title]');
			if (selectionTitle) selectionTitle.textContent = blockTypeLabel(block.type);
			inspector.querySelectorAll('[data-contactpanda-block-field]').forEach(function (field) {
				field.value = block[field.getAttribute('data-contactpanda-block-field')] === undefined ? '' : block[field.getAttribute('data-contactpanda-block-field')];
				if (field.type === 'hidden' && field.dataset.contactpandaColorReady === '1') field.dispatchEvent(new Event('input', {bubbles: true}));
			});
			tableSettings.hidden = block.type !== 'items';
			var contentField = inspector.querySelector('[data-contactpanda-block-field="content"]');
			if (contentField) contentField.closest('label').hidden = ['heading', 'text', 'footer'].indexOf(block.type) === -1;
			var dataSource = inspector.querySelector('[data-contactpanda-invoice-data-source]');
			if (dataSource) dataSource.hidden = ['heading', 'text', 'footer'].indexOf(block.type) === -1;
			var orderHint = inspector.querySelector('[data-contactpanda-invoice-order-hint]');
			if (orderHint) orderHint.hidden = !isFieldBlock(block.type) && ['items', 'totals', 'payment_qr'].indexOf(block.type) === -1;
			var typography = inspector.querySelector('[data-contactpanda-invoice-typography]');
			if (typography) typography.hidden = ['logo', 'spacer', 'payment_qr', 'separator'].indexOf(block.type) !== -1;
			var alignment = inspector.querySelector('[data-contactpanda-invoice-alignment]');
			if (alignment) alignment.hidden = ['logo', 'spacer', 'payment_qr', 'separator'].indexOf(block.type) !== -1;
			renderStructureInspector(block);
			var imageSettings = inspector.querySelector('[data-contactpanda-invoice-image-settings]');
			if (imageSettings) imageSettings.hidden = block.type !== 'logo';
			var imagePicker = inspector.querySelector('.contactpanda-image-picker');
			if (imagePicker && imagePicker.contactPandaSetImages && imagePicker.dataset.currentImageUrl !== (block.image_url || '')) {
				imagePicker.dataset.currentImageUrl = block.image_url || '';
				imagePicker.contactPandaSetImages(block.image_url ? [{id: block.image_id, url: block.image_url, alt: ''}] : []);
			}
			var focusStage = inspector.querySelector('[data-contactpanda-invoice-focus-stage]');
			if (focusStage) { focusStage.hidden = !block.image_url; if (block.image_url) { focusStage.querySelector('img').src = block.image_url; focusStage.querySelector('span').style.left = number(block.focus_x, 50) + '%'; focusStage.querySelector('span').style.top = number(block.focus_y, 50) + '%'; } }
			setInspectorTab(activeInspectorTab);
		}

		function selectBlock(id) {
			selectedBlockId = id;
			renderInvoiceBlocks();
			showInspector();
		}

		function addBlock(type, x, y) {
			var block = createBlock(type, x, y);
			blocks.push(block);
			activeInspectorTab = 'content';
			selectBlock(block.id);
		}

		function loadTemplate(template) {
			template = template || {};
			invoiceBuilder.elements.template_id.value = template.template_id || '';
			invoiceBuilder.elements.name.value = template.name || '';
			if (invoiceBuilder.elements.document_type) invoiceBuilder.elements.document_type.value = template.document_type || 'invoice';
			invoiceBuilder.elements.page_format.value = template.page_format || 'a4';
			invoiceBuilder.elements.orientation.value = template.orientation || 'portrait';
			invoiceBuilder.elements.default.value = template.default ? '1' : '0';
			invoiceBuilder.elements.grid_size_mm.value = template.grid_size_mm || 5;
			invoiceBuilder.elements.snap_to_grid.checked = template.snap_to_grid !== false;
			['top', 'right', 'bottom', 'left'].forEach(function (side) { invoiceBuilder.elements['margin_' + side].value = template.margins && template.margins[side] !== undefined ? template.margins[side] : 15; });
			blocks = Array.isArray(template.blocks) ? template.blocks.map(ensureBlockStructure) : [];
			selectedBlockId = blocks.length ? blocks[0].id : '';
			activeInspectorTab = 'content';
			var builderTitle = document.querySelector('[data-contactpanda-invoice-builder-title]');
			builderTitle.textContent = template.template_id ? (builderTitle.getAttribute('data-edit-label') || builderTitle.textContent) : (builderTitle.getAttribute('data-create-label') || builderTitle.textContent);
			updatePageClass(); renderInvoiceBlocks(); showInspector();
		}

		document.addEventListener('click', function (event) {
			var richButton = event.target.closest('[data-contactpanda-rich-tag]');
			if (richButton && inspector.contains(richButton)) {
				event.preventDefault();
				var richBlock = selectedBlock(); var richField = inspector.querySelector('[data-contactpanda-block-field="content"]');
				if (!richBlock || !richField) return;
				var tag = richButton.getAttribute('data-contactpanda-rich-tag'); var start = richField.selectionStart || 0; var end = richField.selectionEnd || start;
				var current = richField.value || ''; var opening = '<' + tag + '>'; var closing = '</' + tag + '>';
				richField.value = current.slice(0, start) + opening + current.slice(start, end) + closing + current.slice(end);
				richBlock.content = richField.value; renderInvoiceBlocks(); richField.focus(); richField.setSelectionRange(start + opening.length, end + opening.length); return;
			}
			var inspectorTab = event.target.closest('[data-contactpanda-invoice-inspector-tab]');
			if (inspectorTab && inspector.contains(inspectorTab)) { event.preventDefault(); setInspectorTab(inspectorTab.getAttribute('data-contactpanda-invoice-inspector-tab')); return; }
			var add = event.target.closest('[data-contactpanda-invoice-add]');
			if (add) { event.preventDefault(); addBlock(add.getAttribute('data-contactpanda-invoice-add'), 15, 15); return; }
			var canvasDelete = event.target.closest('[data-contactpanda-canvas-delete]');
			if (canvasDelete) { event.preventDefault(); event.stopPropagation(); var deleteBlock = canvasDelete.closest('.contactpanda-invoice-canvas-block'); blocks = blocks.filter(function (block) { return block.id !== deleteBlock.dataset.blockId; }); selectedBlockId = ''; renderInvoiceBlocks(); showInspector(); return; }
			var canvasDuplicate = event.target.closest('[data-contactpanda-canvas-duplicate]');
			if (canvasDuplicate) { event.preventDefault(); event.stopPropagation(); var sourceBlock = blocks.find(function (block) { return block.id === canvasDuplicate.closest('.contactpanda-invoice-canvas-block').dataset.blockId; }); if (!sourceBlock) return; var canvasCopy = JSON.parse(JSON.stringify(sourceBlock)); canvasCopy.id = makeId(canvasCopy.type); canvasCopy.x_mm = snap(number(canvasCopy.x_mm, 15) + 5); canvasCopy.y_mm = snap(number(canvasCopy.y_mm, 15) + 5); blocks.push(canvasCopy); selectBlock(canvasCopy.id); return; }
			var canvasBlock = event.target.closest('.contactpanda-invoice-canvas-block');
			if (canvasBlock && invoicePage.contains(canvasBlock)) { event.preventDefault(); if (selectedBlockId !== canvasBlock.dataset.blockId) activeInspectorTab = 'content'; selectBlock(canvasBlock.dataset.blockId); return; }
			var edit = event.target.closest('[data-contactpanda-invoice-template-edit]');
			if (edit) {
				event.preventDefault();
				var template = {};
				try { template = JSON.parse(edit.getAttribute('data-contactpanda-invoice-template-edit') || '{}'); } catch (ignore) {}
				loadTemplate(template);
				invoiceBuilder.scrollIntoView({ behavior: 'smooth', block: 'start' });
				return;
			}
			if (event.target.closest('[data-contactpanda-invoice-template-new]')) {
				event.preventDefault(); invoiceBuilder.reset(); invoiceBuilder.elements.template_id.value = ''; blocks = []; selectedBlockId = '';
				var newBuilderTitle = document.querySelector('[data-contactpanda-invoice-builder-title]');
				newBuilderTitle.textContent = newBuilderTitle.getAttribute('data-create-label') || newBuilderTitle.textContent; updatePageClass(); renderInvoiceBlocks(); showInspector();
				return;
			}
			if (event.target.closest('[data-contactpanda-invoice-remove]')) {
				event.preventDefault(); blocks = blocks.filter(function (block) { return block.id !== selectedBlockId; }); selectedBlockId = ''; renderInvoiceBlocks(); showInspector(); return;
			}
			if (event.target.closest('[data-contactpanda-invoice-duplicate]')) {
				event.preventDefault(); var source = selectedBlock(); if (!source) return; var copy = JSON.parse(JSON.stringify(source)); copy.id = makeId(copy.type); copy.x_mm = snap(number(copy.x_mm, 15) + 5); copy.y_mm = snap(number(copy.y_mm, 15) + 5); blocks.push(copy); selectBlock(copy.id);
			}
			var addStructure = event.target.closest('[data-invoice-structure-add]');
			if (addStructure) {
				event.preventDefault(); var addBlockTarget = selectedBlock(); if (!addBlockTarget) return;
				var addKind = addStructure.getAttribute('data-invoice-structure-add'); var addSelect = structureSettings.querySelector('[data-invoice-structure-add-select="' + addKind + '"]'); if (!addSelect || !addSelect.value) return;
				if (addKind === 'fields') addBlockTarget.fields.push(fieldRecord(addSelect.value));
				if (addKind === 'table_columns') addBlockTarget.table_columns.push(columnRecord(addSelect.value));
				if (addKind === 'summary_rows') addBlockTarget.summary_rows.push(summaryRecord(addSelect.value, addSelect.value === 'grand_total' ? 'strong' : 'normal'));
				renderInvoiceBlocks(); showInspector(); return;
			}
			var removeStructure = event.target.closest('[data-invoice-structure-remove]');
			if (removeStructure) {
				event.preventDefault(); var removeBlockTarget = selectedBlock(); if (!removeBlockTarget) return;
				var removeKind = removeStructure.getAttribute('data-structure-kind'); var removeIndex = Number(removeStructure.getAttribute('data-structure-index')); if (!Array.isArray(removeBlockTarget[removeKind])) return;
				removeBlockTarget[removeKind].splice(removeIndex, 1); renderInvoiceBlocks(); showInspector(); return;
			}
			var moveStructure = event.target.closest('[data-invoice-structure-move]');
			if (moveStructure) {
				event.preventDefault(); var moveBlockTarget = selectedBlock(); if (!moveBlockTarget) return;
				var moveKind = moveStructure.getAttribute('data-structure-kind'); var moveIndex = Number(moveStructure.getAttribute('data-structure-index')); var moveDirection = moveStructure.getAttribute('data-invoice-structure-move') === 'up' ? -1 : 1; var moveTarget = moveIndex + moveDirection;
				if (!Array.isArray(moveBlockTarget[moveKind]) || moveTarget < 0 || moveTarget >= moveBlockTarget[moveKind].length) return;
				var moved = moveBlockTarget[moveKind].splice(moveIndex, 1)[0]; moveBlockTarget[moveKind].splice(moveTarget, 0, moved); renderInvoiceBlocks(); showInspector(); return;
			}
		});

		inspector.addEventListener('change', function (event) {
			if (event.target.hasAttribute('data-structure-kind') || event.target.hasAttribute('data-invoice-block-option')) {
				event.target.dispatchEvent(new Event('input', {bubbles: true})); return;
			}
			if (!event.target.matches('[data-contactpanda-insert-field]') || !event.target.value) return;
			var block = selectedBlock(); if (!block) return; block.content = (block.content || '') + event.target.value; event.target.value = ''; renderInvoiceBlocks(); showInspector();
		});

		inspector.addEventListener('input', function (event) {
			var structureKind = event.target.getAttribute('data-structure-kind');
			if (structureKind) {
				var structureBlock = selectedBlock(); var structureIndex = Number(event.target.getAttribute('data-structure-index')); var structureProperty = event.target.getAttribute('data-structure-property');
				if (!structureBlock || !Array.isArray(structureBlock[structureKind]) || !structureBlock[structureKind][structureIndex]) return;
				var structureValue = event.target.type === 'checkbox' ? event.target.checked : (event.target.type === 'number' ? Number(event.target.value) : event.target.value);
				structureBlock[structureKind][structureIndex][structureProperty] = structureValue;
				if (structureProperty === 'key') {
					var sourceLabels = structureKind === 'fields' ? optionLabels('dataFields') : (structureKind === 'table_columns' ? optionLabels('columnFields') : optionLabels('summaryFields'));
					structureBlock[structureKind][structureIndex].label = sourceLabels[structureValue] || structureValue;
				}
				renderInvoiceBlocks(); if (structureProperty === 'key') showInspector(); return;
			}
			var blockOption = event.target.getAttribute('data-invoice-block-option');
			if (blockOption) { var optionBlock = selectedBlock(); if (!optionBlock) return; optionBlock[blockOption] = event.target.type === 'checkbox' ? event.target.checked : event.target.value; renderInvoiceBlocks(); return; }
			var property = event.target.getAttribute('data-contactpanda-block-field');
			var block = selectedBlock();
			if (!property || !block) return;
			block[property] = event.target.type === 'number' ? Number(event.target.value) : event.target.value;
			renderInvoiceBlocks();
		});

		inspector.addEventListener('dragstart', function (event) {
			var row = event.target.closest('[data-structure-draggable]');
			if (!row || !structureSettings.contains(row)) return;
			event.dataTransfer.setData('text/contactpanda-structure', JSON.stringify({kind: row.getAttribute('data-structure-draggable'), index: Number(row.getAttribute('data-structure-index'))}));
			event.dataTransfer.effectAllowed = 'move';
			row.classList.add('is-dragging');
		});
		inspector.addEventListener('dragend', function (event) {
			var row = event.target.closest('[data-structure-draggable]');
			if (row) row.classList.remove('is-dragging');
		});
		inspector.addEventListener('dragover', function (event) {
			if (event.target.closest('[data-structure-draggable]')) { event.preventDefault(); event.dataTransfer.dropEffect = 'move'; }
		});
		inspector.addEventListener('drop', function (event) {
			var target = event.target.closest('[data-structure-draggable]');
			if (!target) return;
			var payload = {}; try { payload = JSON.parse(event.dataTransfer.getData('text/contactpanda-structure') || '{}'); } catch (ignore) {}
			var targetKind = target.getAttribute('data-structure-draggable'), targetIndex = Number(target.getAttribute('data-structure-index'));
			var current = selectedBlock(); if (!current || payload.kind !== targetKind || !Array.isArray(current[targetKind]) || payload.index === targetIndex) return;
			event.preventDefault(); var movedField = current[targetKind].splice(payload.index, 1)[0]; if (payload.index < targetIndex) targetIndex -= 1; current[targetKind].splice(targetIndex, 0, movedField); renderInvoiceBlocks(); showInspector();
		});

		invoiceBuilder.addEventListener('change', function (event) {
			if (event.target.name === 'page_format' || event.target.name === 'orientation' || event.target.name === 'grid_size_mm' || event.target.name.indexOf('margin_') === 0) { updatePageClass(); renderInvoiceBlocks(); }
		});

		document.querySelectorAll('[data-contactpanda-invoice-add]').forEach(function (choice) {
			choice.addEventListener('dragstart', function (event) { event.dataTransfer.setData('text/contactpanda-invoice-type', choice.getAttribute('data-contactpanda-invoice-add')); });
		});
		invoicePage.addEventListener('dragover', function (event) { event.preventDefault(); });
		invoicePage.addEventListener('drop', function (event) {
			event.preventDefault(); var type = event.dataTransfer.getData('text/contactpanda-invoice-type'); if (!type) return; var bounds = invoicePage.getBoundingClientRect(); addBlock(type, (event.clientX - bounds.left) / millimetreScale, (event.clientY - bounds.top) / millimetreScale);
		});

		invoicePage.addEventListener('pointerdown', function (event) {
			var element = event.target.closest('.contactpanda-invoice-canvas-block'); if (!element || event.target.closest('button')) return;
			var block = blocks.find(function (item) { return item.id === element.dataset.blockId; }); if (!block) return;
			var resizing = event.target.classList.contains('contactpanda-invoice-resize-handle'); selectedBlockId = block.id; renderInvoiceBlocks(); showInspector();
			pointerOperation = {id: block.id, resizing: resizing, startX: event.clientX, startY: event.clientY, x: number(block.x_mm, 0), y: number(block.y_mm, 0), width: number(block.width_mm, 90), height: number(block.height_mm, 20)};
			element = invoicePage.querySelector('[data-block-id="' + block.id + '"]'); if (element && element.setPointerCapture) element.setPointerCapture(event.pointerId); event.preventDefault();
		});
		invoicePage.addEventListener('pointermove', function (event) {
			if (!pointerOperation) return; var block = blocks.find(function (item) { return item.id === pointerOperation.id; }); if (!block) return;
			var dx = (event.clientX - pointerOperation.startX) / millimetreScale; var dy = (event.clientY - pointerOperation.startY) / millimetreScale;
			if (pointerOperation.resizing) { block.width_mm = clamp(snap(pointerOperation.width + dx), 10, pageSize.width - number(block.x_mm, 0)); block.height_mm = clamp(snap(pointerOperation.height + dy), 6, pageSize.height - number(block.y_mm, 0)); }
			else { block.x_mm = clamp(snap(pointerOperation.x + dx), 0, pageSize.width - number(block.width_mm, 10)); block.y_mm = clamp(snap(pointerOperation.y + dy), 0, pageSize.height - number(block.height_mm, 6)); }
			var element = invoicePage.querySelector('[data-block-id="' + block.id + '"]');
			if (element) { element.style.left = block.x_mm * millimetreScale + 'px'; element.style.top = block.y_mm * millimetreScale + 'px'; element.style.width = block.width_mm * millimetreScale + 'px'; element.style.height = block.height_mm * millimetreScale + 'px'; }
			['x_mm', 'y_mm', 'width_mm', 'height_mm'].forEach(function (property) { var field = inspector.querySelector('[data-contactpanda-block-field="' + property + '"]'); if (field) field.value = block[property]; });
			syncInvoiceInput();
		});
		invoicePage.addEventListener('pointerup', function () { if (pointerOperation) { pointerOperation = null; renderInvoiceBlocks(); showInspector(); } });
		invoicePage.addEventListener('pointercancel', function () { pointerOperation = null; });

		invoiceBuilder.addEventListener('submit', syncInvoiceInput);
		installBuilderControls();
		var initialTemplate = {};
		try { initialTemplate = JSON.parse(invoiceBuilder.getAttribute('data-contactpanda-initial-template') || '{}'); } catch (ignore) {}
		loadTemplate(initialTemplate);
	}
}());
