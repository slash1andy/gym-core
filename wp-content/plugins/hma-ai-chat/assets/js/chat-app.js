/**
 * HMA AI Chat - Client-side chat application
 *
 * @package
 */

(function () {
	'use strict';

	if (typeof hmaAiChat === 'undefined') {
		// eslint-disable-next-line no-console -- Required for debugging missing config.
		console.error('hmaAiChat configuration not found');
		return;
	}

	const config = hmaAiChat;

	// Wire the localized REST nonce into apiFetch before any request fires.
	// Without this, non-admin (edit_posts) users hit rest_cookie_invalid_nonce
	// 403s on every message/action/heartbeat.
	if (
		typeof wp !== 'undefined' &&
		wp.apiFetch &&
		typeof wp.apiFetch.createNonceMiddleware === 'function' &&
		config.nonce
	) {
		wp.apiFetch.use(wp.apiFetch.createNonceMiddleware(config.nonce));
	}

	let currentConversationId = null;
	let currentAgent = null;

	/**
	 * Initialize the chat application.
	 */
	function initChat() {
		const container = document.getElementById('hma-ai-chat-container');
		if (!container) {
			return;
		}

		renderChatPanel(container);
		attachEventListeners();
		loadPendingActions();
	}

	/**
	 * Render the main chat panel.
	 *
	 * @param {HTMLElement} container The chat container element.
	 */
	function renderChatPanel(container) {
		const agents = config.agents || [];

		container.innerHTML = `
			<div class="hma-ai-chat-panel">
				<div class="hma-ai-chat-header">
					<h2 class="hma-ai-chat-title">${config.strings.selectAgent}</h2>
					<div class="hma-ai-chat-controls">
						<select id="hma-agent-selector" class="hma-ai-chat-agent-selector" aria-label="Select an agent">
							<option value="">${config.strings.selectAgent}</option>
							${agents
								.map(
									(agent) => `
								<option value="${agent.slug}">${agent.icon} ${agent.name}</option>
							`
								)
								.join('')}
						</select>
					</div>
				</div>

				<div
					id="hma-messages"
					class="hma-ai-chat-messages"
					role="log"
					aria-live="polite"
					aria-relevant="additions"
					aria-atomic="false"
				></div>

				<div class="hma-ai-chat-input-area">
					<textarea
						id="hma-message-input"
						class="hma-ai-chat-input"
						placeholder="${config.strings.typingPlaceholder}"
						aria-label="Message input"
						rows="2"
						disabled
					></textarea>
					<button
						id="hma-send-btn"
						class="hma-ai-chat-send-btn"
						aria-label="Send message"
						disabled
					>${config.strings.sendButton}</button>
				</div>
			</div>

			<div id="hma-pending-actions" class="hma-ai-pending-actions" style="display: none;">
				<div class="hma-ai-pending-actions-header">
					<h3 class="hma-ai-pending-actions-title">${config.strings.pendingActions}</h3>
					<div id="hma-bulk-bar" class="hma-ai-bulk-bar" style="display: none;">
						<label class="hma-ai-bulk-select-all">
							<input type="checkbox" id="hma-select-all" aria-label="Select all actions" />
							<span>${config.strings.selectAll || 'Select All'}</span>
						</label>
						<button class="hma-ai-action-btn hma-ai-action-approve hma-ai-bulk-btn" id="hma-bulk-approve">
							${config.strings.bulkApprove || 'Approve Selected'}
						</button>
						<button class="hma-ai-action-btn hma-ai-action-reject hma-ai-bulk-btn" id="hma-bulk-reject">
							${config.strings.bulkReject || 'Reject Selected'}
						</button>
					</div>
				</div>
				<div id="hma-actions-list"></div>
			</div>
		`;
	}

	/**
	 * Attach event listeners.
	 */
	function attachEventListeners() {
		const agentSelector = document.getElementById('hma-agent-selector');
		const messageInput = document.getElementById('hma-message-input');
		const sendBtn = document.getElementById('hma-send-btn');

		agentSelector.addEventListener('change', handleAgentChange);
		messageInput.addEventListener('keydown', handleMessageKeydown);
		sendBtn.addEventListener('click', handleSendMessage);
	}

	/**
	 * Handle agent selection change.
	 *
	 * @param {Event} e The change event.
	 */
	function handleAgentChange(e) {
		currentAgent = e.target.value;
		const messageInput = document.getElementById('hma-message-input');
		const sendBtn = document.getElementById('hma-send-btn');

		if (currentAgent) {
			messageInput.disabled = false;
			sendBtn.disabled = false;
			messageInput.focus();
			currentConversationId = null;
			clearMessages();
		} else {
			messageInput.disabled = true;
			sendBtn.disabled = true;
		}
	}

	/**
	 * Handle enter key in message input.
	 *
	 * @param {KeyboardEvent} e The keydown event.
	 */
	function handleMessageKeydown(e) {
		if (e.key === 'Enter' && !e.shiftKey) {
			e.preventDefault();
			handleSendMessage();
		}
	}

	/**
	 * Handle send message.
	 */
	async function handleSendMessage() {
		const messageInput = document.getElementById('hma-message-input');
		const message = messageInput.value.trim();

		if (!message || !currentAgent) {
			return;
		}

		const sendBtn = document.getElementById('hma-send-btn');

		// Disable input and button while sending.
		messageInput.disabled = true;
		sendBtn.disabled = true;

		// Add user message to display.
		addMessage('user', message);
		messageInput.value = '';

		// Show typing indicator while waiting.
		const typingId = showTypingIndicator();

		try {
			// Send message to server.
			const response = await wp.apiFetch({
				url: config.apiUrl + 'message',
				method: 'POST',
				data: {
					agent: currentAgent,
					message,
					conversation_id: currentConversationId,
				},
			});

			removeTypingIndicator(typingId);

			if (response.success) {
				currentConversationId = response.conversation_id;
				addMessage('assistant', response.response, response.tool_calls);
			} else {
				addMessage('assistant', config.strings.errorMessage);
			}
		} catch (error) {
			removeTypingIndicator(typingId);

			// eslint-disable-next-line no-console -- User-facing error logging.
			console.error('Error sending message:', error);
			// wp.apiFetch throws the parsed JSON body for non-2xx responses.
			const errorDetail = (typeof error === 'object' && error !== null)
				? (error.message || error.code || JSON.stringify(error))
				: String(error);
			addMessage('assistant', '**Error:** ' + errorDetail);
		}

		// Re-enable input and button.
		messageInput.disabled = false;
		sendBtn.disabled = false;
		messageInput.focus();
	}

	/**
	 * Add a message to the display.
	 *
	 * @param {string} role      The message role (user or assistant).
	 * @param {string} content   The message content.
	 * @param {Array=} toolCalls Optional list of tool calls executed for this turn.
	 *                           Each entry: {name, input, output, is_error, pending}.
	 */
	function addMessage(role, content, toolCalls) {
		const messagesContainer = document.getElementById('hma-messages');
		const messageDiv = document.createElement('div');
		const timestamp = new Date().toLocaleTimeString([], {
			hour: '2-digit',
			minute: '2-digit',
		});

		// Render markdown for assistant messages, escape user messages.
		const rendered = role === 'assistant'
			? renderMarkdown(content)
			: escapeHtml(content);

		const toolCallsHtml = (role === 'assistant' && Array.isArray(toolCalls) && toolCalls.length > 0)
			? renderToolCalls(toolCalls)
			: '';

		messageDiv.className = `hma-ai-message ${role}`;
		messageDiv.innerHTML = `
			<div class="hma-ai-message-bubble">${rendered}</div>
			${toolCallsHtml}
			<div class="hma-ai-message-timestamp">${timestamp}</div>
		`;

		messagesContainer.appendChild(messageDiv);
		messagesContainer.scrollTop = messagesContainer.scrollHeight;
	}

	/**
	 * Render the tool-call audit block for an assistant turn.
	 *
	 * Each call is collapsed by default. Click to expand and see the
	 * literal input/output that was sent to / received from the tool.
	 *
	 * @param {Array} toolCalls List of {name, input, output, is_error, pending}.
	 * @returns {string} HTML for the tool-call block.
	 */
	function renderToolCalls(toolCalls) {
		const items = toolCalls.map((call) => {
			const name = escapeHtml(call.name || 'unknown_tool');
			let badge;
			let badgeClass;
			if (call.is_error) {
				badge = 'error';
				badgeClass = 'error';
			} else if (call.pending) {
				badge = 'queued for approval';
				badgeClass = 'pending';
			} else {
				badge = 'ok';
				badgeClass = 'ok';
			}

			const input = escapeHtml(JSON.stringify(call.input ?? {}, null, 2));
			const output = escapeHtml(JSON.stringify(call.output ?? null, null, 2));

			return `
				<details class="hma-ai-tool-call">
					<summary>
						<span class="hma-ai-tool-call-name">${name}</span>
						<span class="hma-ai-tool-call-badge hma-ai-tool-call-badge--${badgeClass}">${badge}</span>
					</summary>
					<div class="hma-ai-tool-call-body">
						<div class="hma-ai-tool-call-section">
							<div class="hma-ai-tool-call-label">Input</div>
							<pre>${input}</pre>
						</div>
						<div class="hma-ai-tool-call-section">
							<div class="hma-ai-tool-call-label">Output</div>
							<pre>${output}</pre>
						</div>
					</div>
				</details>
			`;
		}).join('');

		const summary = toolCalls.length === 1
			? '1 tool call'
			: `${toolCalls.length} tool calls`;

		return `
			<details class="hma-ai-tool-calls" open>
				<summary class="hma-ai-tool-calls-summary">🔧 ${summary}</summary>
				<div class="hma-ai-tool-calls-list">${items}</div>
			</details>
		`;
	}

	/**
	 * Clear all messages.
	 */
	function clearMessages() {
		const messagesContainer = document.getElementById('hma-messages');
		messagesContainer.innerHTML = '';
	}

	/**
	 * Load and display pending actions.
	 */
	async function loadPendingActions() {
		// Check if user has permissions to approve actions.
		if ( ! config.canManageActions ) {
			return;
		}

		try {
			const response = await wp.apiFetch({
				url: config.apiUrl + 'pending-actions',
				method: 'GET',
			});

			if (response && response.length > 0) {
				renderPendingActions(response);
			}
		} catch (error) {
			// Silent failure — pending actions are non-critical.
			// eslint-disable-next-line no-console -- Debug-level logging for non-critical feature.
			console.debug('Could not load pending actions:', error);
		}
	}

	/**
	 * Render pending actions panel with three-path approval flow.
	 *
	 * Each action shows three buttons:
	 * 1. Approve — immediate execution.
	 * 2. Approve with Changes — opens textarea for staff instructions.
	 * 3. Reject — opens optional reason textarea.
	 *
	 * @param {Array} actions Array of pending action objects.
	 */
	function renderPendingActions(actions) {
		const panel = document.getElementById('hma-pending-actions');
		const list = document.getElementById('hma-actions-list');

		if (!panel || !list) {
			return;
		}

		// Show bulk bar when there are multiple actions.
		const bulkBar = document.getElementById('hma-bulk-bar');
		if (bulkBar) {
			bulkBar.style.display = actions.length >= 2 ? 'flex' : 'none';
		}

		list.innerHTML = actions
			.map(
				(action) => `
			<div class="hma-ai-pending-action-item" data-action-id="${action.id}">
				<div class="hma-ai-action-checkbox">
					<input type="checkbox" class="hma-ai-action-select" data-action-id="${action.id}"
						aria-label="Select action ${action.id}" />
				</div>
				<div class="hma-ai-action-info">
					<div class="hma-ai-action-agent">${escapeHtml(action.agent)}</div>
					<div class="hma-ai-action-type">${escapeHtml(action.action_type)}</div>
					${action.action_data && action.action_data.summary ? `<div class="hma-ai-action-summary">${escapeHtml(action.action_data.summary)}</div>` : ''}
					<div class="hma-ai-action-time">${formatDate(action.created_at)}</div>
				</div>
				<div class="hma-ai-action-controls">
					<button class="hma-ai-action-btn hma-ai-action-approve" data-action-id="${action.id}"
						aria-label="Approve action ${action.id}">
						${config.strings.approve}
					</button>
					<button class="hma-ai-action-btn hma-ai-action-approve-changes" data-action-id="${action.id}"
						aria-label="Approve action ${action.id} with changes">
						${config.strings.approveWithChanges}
					</button>
					<button class="hma-ai-action-btn hma-ai-action-reject" data-action-id="${action.id}"
						aria-label="Reject action ${action.id}">
						${config.strings.reject}
					</button>
				</div>
				<div class="hma-ai-action-changes-form" id="hma-changes-form-${action.id}" style="display:none;">
					<textarea class="hma-ai-action-textarea" id="hma-changes-text-${action.id}"
						placeholder="${config.strings.changesPlaceholder}"
						aria-label="Change instructions for action ${action.id}"
						rows="3"></textarea>
					<div class="hma-ai-action-form-buttons">
						<button class="hma-ai-action-btn hma-ai-action-submit-changes" data-action-id="${action.id}">
							${config.strings.submitChanges}
						</button>
						<button class="hma-ai-action-btn hma-ai-action-cancel" data-action-id="${action.id}" data-form="changes">
							${config.strings.cancel}
						</button>
					</div>
				</div>
				<div class="hma-ai-action-reject-form" id="hma-reject-form-${action.id}" style="display:none;">
					<textarea class="hma-ai-action-textarea" id="hma-reject-text-${action.id}"
						placeholder="${config.strings.rejectReasonPlaceholder}"
						aria-label="Rejection reason for action ${action.id}"
						rows="2"></textarea>
					<div class="hma-ai-action-form-buttons">
						<button class="hma-ai-action-btn hma-ai-action-submit-reject" data-action-id="${action.id}">
							${config.strings.submitRejection}
						</button>
						<button class="hma-ai-action-btn hma-ai-action-cancel" data-action-id="${action.id}" data-form="reject">
							${config.strings.cancel}
						</button>
					</div>
				</div>
			</div>
		`
			)
			.join('');

		// Approve — immediate execution.
		list.querySelectorAll('.hma-ai-action-approve').forEach((btn) => {
			btn.addEventListener('click', (e) =>
				approveAction(e.target.dataset.actionId)
			);
		});

		// Approve with Changes — show textarea.
		list.querySelectorAll('.hma-ai-action-approve-changes').forEach((btn) => {
			btn.addEventListener('click', (e) => {
				const id = e.target.dataset.actionId;
				hideAllForms(id);
				const form = document.getElementById(`hma-changes-form-${id}`);
				if (form) {
					form.style.display = 'block';
					form.querySelector('textarea').focus();
				}
			});
		});

		// Reject — show reason textarea.
		list.querySelectorAll('.hma-ai-action-reject').forEach((btn) => {
			btn.addEventListener('click', (e) => {
				const id = e.target.dataset.actionId;
				hideAllForms(id);
				const form = document.getElementById(`hma-reject-form-${id}`);
				if (form) {
					form.style.display = 'block';
					form.querySelector('textarea').focus();
				}
			});
		});

		// Submit changes.
		list.querySelectorAll('.hma-ai-action-submit-changes').forEach((btn) => {
			btn.addEventListener('click', (e) => {
				const id = e.target.dataset.actionId;
				const textarea = document.getElementById(`hma-changes-text-${id}`);
				if (textarea && textarea.value.trim()) {
					approveWithChanges(id, textarea.value.trim());
				}
			});
		});

		// Submit rejection.
		list.querySelectorAll('.hma-ai-action-submit-reject').forEach((btn) => {
			btn.addEventListener('click', (e) => {
				const id = e.target.dataset.actionId;
				const textarea = document.getElementById(`hma-reject-text-${id}`);
				rejectAction(id, textarea ? textarea.value.trim() : '');
			});
		});

		// Cancel buttons.
		list.querySelectorAll('.hma-ai-action-cancel').forEach((btn) => {
			btn.addEventListener('click', (e) => {
				const id = e.target.dataset.actionId;
				hideAllForms(id);
			});
		});

		// Bulk select-all checkbox.
		const selectAll = document.getElementById('hma-select-all');
		if (selectAll) {
			selectAll.checked = false;
			selectAll.addEventListener('change', () => {
				list.querySelectorAll('.hma-ai-action-select').forEach((cb) => {
					cb.checked = selectAll.checked;
				});
			});
		}

		// Bulk approve.
		const bulkApproveBtn = document.getElementById('hma-bulk-approve');
		if (bulkApproveBtn) {
			// Remove old listener by replacing the node.
			const newBtn = bulkApproveBtn.cloneNode(true);
			bulkApproveBtn.parentNode.replaceChild(newBtn, bulkApproveBtn);
			newBtn.addEventListener('click', () => bulkAction('approve'));
		}

		// Bulk reject.
		const bulkRejectBtn = document.getElementById('hma-bulk-reject');
		if (bulkRejectBtn) {
			const newBtn = bulkRejectBtn.cloneNode(true);
			bulkRejectBtn.parentNode.replaceChild(newBtn, bulkRejectBtn);
			newBtn.addEventListener('click', () => bulkAction('reject'));
		}

		panel.style.display = 'block';
	}

	/**
	 * Hide all expanded forms for a given action.
	 *
	 * @param {string} actionId The action ID.
	 */
	function hideAllForms(actionId) {
		const changesForm = document.getElementById(`hma-changes-form-${actionId}`);
		const rejectForm = document.getElementById(`hma-reject-form-${actionId}`);
		if (changesForm) {
			changesForm.style.display = 'none';
		}
		if (rejectForm) {
			rejectForm.style.display = 'none';
		}
	}

	/**
	 * Approve an action for immediate execution.
	 *
	 * @param {string} actionId The action ID to approve.
	 */
	async function approveAction(actionId) {
		try {
			const response = await wp.apiFetch({
				url: config.apiUrl + `actions/${actionId}/approve`,
				method: 'POST',
			});

			showActionNotice(actionId, config.strings.actionApproved, 'success');
			loadPendingActions();
		} catch (error) {
			// eslint-disable-next-line no-console -- User-facing error logging.
			console.error('Error approving action:', error);
			showActionNotice(actionId, config.strings.errorMessage, 'error');
		}
	}

	/**
	 * Approve an action with staff-directed changes.
	 *
	 * @param {string} actionId     The action ID.
	 * @param {string} instructions Staff instructions for changes.
	 */
	async function approveWithChanges(actionId, instructions) {
		try {
			const response = await wp.apiFetch({
				url: config.apiUrl + `actions/${actionId}/approve-with-changes`,
				method: 'POST',
				data: { instructions },
			});

			showActionNotice(actionId, config.strings.actionApprovedChanges, 'success');
			loadPendingActions();
		} catch (error) {
			// eslint-disable-next-line no-console -- User-facing error logging.
			console.error('Error approving with changes:', error);
			showActionNotice(actionId, config.strings.errorMessage, 'error');
		}
	}

	/**
	 * Reject an action.
	 *
	 * @param {string} actionId The action ID to reject.
	 * @param {string} reason   Optional rejection reason.
	 */
	async function rejectAction(actionId, reason) {
		try {
			const response = await wp.apiFetch({
				url: config.apiUrl + `actions/${actionId}/reject`,
				method: 'POST',
				data: { reason: reason || '' },
			});

			showActionNotice(actionId, config.strings.actionRejected, 'success');
			loadPendingActions();
		} catch (error) {
			// eslint-disable-next-line no-console -- User-facing error logging.
			console.error('Error rejecting action:', error);
			showActionNotice(actionId, config.strings.errorMessage, 'error');
		}
	}

	/**
	 * Show a temporary notice for an action result.
	 *
	 * @param {string} actionId The action ID.
	 * @param {string} message  The notice message.
	 * @param {string} type     Notice type (success or error).
	 */
	function showActionNotice(actionId, message, type) {
		const item = document.querySelector(
			`.hma-ai-pending-action-item[data-action-id="${actionId}"]`
		);
		if (!item) {
			return;
		}

		const notice = document.createElement('div');
		notice.className = `hma-ai-action-notice hma-ai-action-notice-${type}`;
		notice.textContent = message;
		item.appendChild(notice);

		setTimeout(() => notice.remove(), 3000);
	}

	/**
	 * Get selected action IDs from checkboxes.
	 *
	 * @return {number[]} Array of selected action IDs.
	 */
	function getSelectedIds() {
		const checkboxes = document.querySelectorAll(
			'.hma-ai-action-select:checked'
		);
		return Array.from(checkboxes).map((cb) =>
			parseInt(cb.dataset.actionId, 10)
		);
	}

	/**
	 * Execute a bulk approve or reject operation.
	 *
	 * @param {string} operation Either 'approve' or 'reject'.
	 */
	async function bulkAction(operation) {
		const ids = getSelectedIds();
		if (ids.length === 0) {
			return;
		}

		try {
			const response = await wp.apiFetch({
				url: config.apiUrl + 'actions/bulk',
				method: 'POST',
				data: {
					action_ids: ids,
					operation,
				},
			});

			if (response.success) {
				loadPendingActions();
			}
		} catch (error) {
			// eslint-disable-next-line no-console -- User-facing error logging.
			console.error('Bulk action error:', error);
		}
	}

	/**
	 * Show a typing indicator in the messages area.
	 *
	 * @return {string} The indicator element ID for removal.
	 */
	function showTypingIndicator() {
		const messagesContainer = document.getElementById('hma-messages');
		const id = 'hma-typing-' + Date.now();
		const div = document.createElement('div');
		div.id = id;
		div.className = 'hma-ai-message assistant';
		// aria-hidden so the live region doesn't announce "dot dot dot" — the
		// actual reply that follows is announced via additions on #hma-messages.
		div.setAttribute('aria-hidden', 'true');
		div.innerHTML = `
			<div class="hma-ai-message-bubble hma-ai-typing-bubble">
				<span class="hma-ai-typing-dot"></span>
				<span class="hma-ai-typing-dot"></span>
				<span class="hma-ai-typing-dot"></span>
			</div>
		`;
		messagesContainer.setAttribute('aria-busy', 'true');
		messagesContainer.appendChild(div);
		messagesContainer.scrollTop = messagesContainer.scrollHeight;
		return id;
	}

	/**
	 * Remove a typing indicator by ID.
	 *
	 * @param {string} id The indicator element ID.
	 */
	function removeTypingIndicator(id) {
		const el = document.getElementById(id);
		if (el) el.remove();
		const messagesContainer = document.getElementById('hma-messages');
		if (messagesContainer) {
			messagesContainer.setAttribute('aria-busy', 'false');
		}
	}

	/**
	 * Render a subset of Markdown to HTML for assistant messages.
	 *
	 * Supports: headings, bold, italic, inline code, code blocks,
	 * unordered/ordered lists, horizontal rules, links, and paragraphs.
	 *
	 * @param {string} text Raw markdown text.
	 * @return {string} HTML string.
	 */
	function renderMarkdown(text) {
		if (!text) return '';

		var s = escapeHtml(text);

		// Code blocks (``` ... ```)
		s = s.replace(/```(\w*)\n([\s\S]*?)```/g, function(_, lang, code) {
			return '<pre><code' + (lang ? ' class="language-' + lang + '"' : '') + '>' + code.trim() + '</code></pre>';
		});

		// Inline code (`...`)
		s = s.replace(/`([^`]+)`/g, '<code>$1</code>');

		// Headings (### ... at start of line)
		s = s.replace(/^### (.+)$/gm, '<h4>$1</h4>');
		s = s.replace(/^## (.+)$/gm, '<h3>$1</h3>');
		s = s.replace(/^# (.+)$/gm, '<h3>$1</h3>');

		// Horizontal rule
		s = s.replace(/^---$/gm, '<hr>');

		// Bold + italic (use word-boundary-aware patterns to avoid matching list markers)
		s = s.replace(/\*\*\*(.+?)\*\*\*/g, '<strong><em>$1</em></strong>');
		s = s.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
		s = s.replace(/(?<![\\*\w])\*([^*\n]+?)\*(?!\*)/g, '<em>$1</em>');

		// Unordered lists — consecutive lines starting with - or *
		s = s.replace(/((?:^[\t ]*[-*] .+$\n?)+)/gm, function(block) {
			var items = block.trim().split('\n').map(function(line) {
				return '<li>' + line.replace(/^[\t ]*[-*] /, '') + '</li>';
			}).join('');
			return '<ul>' + items + '</ul>';
		});

		// Ordered lists — consecutive lines starting with 1. 2. etc
		s = s.replace(/((?:^[\t ]*\d+\. .+$\n?)+)/gm, function(block) {
			var items = block.trim().split('\n').map(function(line) {
				return '<li>' + line.replace(/^[\t ]*\d+\. /, '') + '</li>';
			}).join('');
			return '<ol>' + items + '</ol>';
		});

		// Links [text](url)
		s = s.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');

		// Paragraphs — double newlines become paragraph breaks
		s = s.replace(/\n{2,}/g, '</p><p>');
		// Single newlines become <br> (except inside block elements)
		s = s.replace(/\n/g, '<br>');

		// Wrap in paragraph if not already a block element
		if (!/^<(h[1-6]|ul|ol|pre|hr|p)/.test(s)) {
			s = '<p>' + s + '</p>';
		}

		// Clean up empty paragraphs and breaks adjacent to block elements
		s = s.replace(/<p><\/p>/g, '');
		s = s.replace(/<br>\s*(<\/?(?:ul|ol|li|h[1-6]|pre|hr|p)>)/g, '$1');
		s = s.replace(/(<\/?(?:ul|ol|li|h[1-6]|pre|hr|p)>)\s*<br>/g, '$1');

		return s;
	}

	/**
	 * Escape HTML to prevent XSS.
	 *
	 * @param {string} text The text to escape.
	 * @return {string} The escaped text.
	 */
	function escapeHtml(text) {
		if (!text) {
			return '';
		}
		const str = String(text);
		const map = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#039;',
		};
		return str.replace(/[&<>"']/g, (m) => map[m]);
	}

	/**
	 * Format date string.
	 *
	 * @param {string} dateString The date string to format.
	 * @return {string} The formatted date.
	 */
	function formatDate(dateString) {
		const date = new Date(dateString);
		return date.toLocaleDateString([], {
			month: 'short',
			day: 'numeric',
			hour: '2-digit',
			minute: '2-digit',
		});
	}

	// Initialize on document ready.
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initChat);
	} else {
		initChat();
	}
})();
