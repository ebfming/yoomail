(function() {
	'use strict'

	const appId = 'yoomail'

	const state = {
		settings: null,
		health: null,
		saving: false,
		checking: false,
	}

	const root = document.getElementById('yoomail-admin-basic-settings')
	if (!root) {
		return
	}

	const requestToken = document.head.getAttribute('data-requesttoken') || ''

	const apiUrl = (path) => OC.generateUrl(`/apps/${appId}${path}`)

	const request = async (path, options = {}) => {
		const response = await fetch(apiUrl(path), {
			method: options.method || 'GET',
			headers: {
				'Accept': 'application/json',
				'Content-Type': 'application/json',
				'requesttoken': requestToken,
			},
			body: options.body ? JSON.stringify(options.body) : undefined,
			credentials: 'same-origin',
		})

		const data = await response.json().catch(() => ({}))
		if (!response.ok) {
			const message = Array.isArray(data?.ocs?.data?.message) ? data.ocs.data.message.join(', ') : data?.message
			throw new Error(message || t(appId, 'Request failed'))
		}
		return data
	}

	const escapeHtml = (value) => String(value)
		.replaceAll('&', '&amp;')
		.replaceAll('<', '&lt;')
		.replaceAll('>', '&gt;')
		.replaceAll('"', '&quot;')

	const checked = (value) => value ? 'checked' : ''
	const render = () => {
		const settings = state.settings
		if (!settings) {
			root.innerHTML = `<div class="settings-section"><p>${escapeHtml(t(appId, 'Loading account'))}</p></div>`
			return
		}

		const health = state.health
		const healthClass = health ? `ym-admin-health ym-admin-health--${health.status}` : 'ym-admin-health'
		const issues = health?.issues?.length
			? `<ul class="ym-admin-list">${health.issues.map((issue) => `<li>${escapeHtml(issue)}</li>`).join('')}</ul>`
			: ''

		root.innerHTML = `
			<div class="settings-section">
				<h2>${escapeHtml(t(appId, 'Basic configuration'))}</h2>
				<p class="settings-hint">${escapeHtml(t(appId, 'These options define YooMail defaults and sync behavior for the whole site.'))}</p>
				<div class="ym-admin-grid">
				<div class="ym-admin-card">
					<h3>${escapeHtml(t(appId, 'Health check'))}</h3>
					<div class="${healthClass}">
						<div class="ym-admin-health-summary">${escapeHtml(health?.summary || t(appId, 'Health check has not run yet.'))}</div>
						${issues}
					</div>
					<button type="button" class="primary" id="ym-check-health" ${state.checking ? 'disabled' : ''}>${escapeHtml(state.checking ? t(appId, 'Checking mail host connectivity') : t(appId, 'Check now'))}</button>
				</div>

				<div class="ym-admin-card">
					<h3>${escapeHtml(t(appId, 'Time display default'))}</h3>
					<p class="settings-hint">${escapeHtml(t(appId, 'Users can still override this in their personal preferences later.'))}</p>
					<p class="ym-admin-control-row">
						<input type="radio" class="radio" id="ym-time-format-24" name="ym-time-format" value="24" ${checked(settings.timeFormatDefault === '24')}>
						<label for="ym-time-format-24">${escapeHtml(t(appId, '24-hour format'))}</label>
					</p>
					<p class="ym-admin-control-row">
						<input type="radio" class="radio" id="ym-time-format-12" name="ym-time-format" value="12" ${checked(settings.timeFormatDefault === '12')}>
						<label for="ym-time-format-12">${escapeHtml(t(appId, '12-hour format'))}</label>
					</p>
				</div>

				<div class="ym-admin-card">
					<h3>${escapeHtml(t(appId, 'Mail sync mode'))}</h3>
					<p class="ym-admin-control-row">
						<input type="radio" class="radio" id="ym-realtime-mode-websocket" name="ym-realtime-mode" value="websocket" ${checked(settings.realtimeMode === 'websocket')}>
						<label for="ym-realtime-mode-websocket">${escapeHtml(t(appId, 'Realtime sync (WebSocket)'))}</label>
					</p>
					<p class="settings-hint ym-admin-indented">${escapeHtml(t(appId, 'Prefer realtime notifications and mailbox refresh. HTTP sync remains available as a fallback path when needed.'))}</p>
					<p class="ym-admin-control-row">
						<input type="radio" class="radio" id="ym-realtime-mode-http" name="ym-realtime-mode" value="http" ${checked(settings.realtimeMode === 'http')}>
						<label for="ym-realtime-mode-http">${escapeHtml(t(appId, 'Async sync (HTTP)'))}</label>
					</p>
					<p class="settings-hint ym-admin-indented">${escapeHtml(t(appId, 'Disable WebSocket usage and rely on the classic HTTP-based sync flow.'))}</p>
					${settings.realtimeMode === 'websocket' && health?.status === 'error'
						? `<p class="ym-admin-warning">${escapeHtml(t(appId, 'WebSocket is selected, but the realtime service is not healthy.'))}</p>`
						: ''}
				</div>

				<div class="ym-admin-card">
					<h3>${escapeHtml(t(appId, 'Delete behavior'))}</h3>
					<p class="ym-admin-control-row">
						<input type="checkbox" class="checkbox" id="ym-delete-local-sync" ${checked(settings.deleteSyncLocalToServer)}>
						<label for="ym-delete-local-sync">${escapeHtml(t(appId, 'Sync local delete to mail server'))}</label>
					</p>
					<p class="settings-hint ym-admin-indented">${escapeHtml(t(appId, 'When enabled, deleting a message in YooMail also performs the delete or move-to-trash action on the mail server.'))}</p>
					<p class="ym-admin-control-row">
						<input type="checkbox" class="checkbox" id="ym-delete-server-sync" ${checked(settings.deleteSyncServerToLocal)}>
						<label for="ym-delete-server-sync">${escapeHtml(t(appId, 'Sync server delete back to YooMail'))}</label>
					</p>
					<p class="settings-hint ym-admin-indented">${escapeHtml(t(appId, 'When enabled, deletions detected from the mail server are also removed from YooMail local data during sync.'))}</p>
				</div>
				</div>
				<div class="ym-admin-actions">
					<button type="button" class="primary" id="ym-save-basic-settings" ${state.saving ? 'disabled' : ''}>${escapeHtml(state.saving ? t(appId, 'Save') + '...' : t(appId, 'Save'))}</button>
				</div>
			</div>
		`

		root.querySelector('#ym-check-health')?.addEventListener('click', checkHealth)
		root.querySelector('#ym-save-basic-settings')?.addEventListener('click', saveSettings)
	}

	const collectSettings = () => ({
		timeFormatDefault: root.querySelector('input[name="ym-time-format"]:checked')?.value || '24',
		realtimeMode: root.querySelector('input[name="ym-realtime-mode"]:checked')?.value || 'websocket',
		deleteSyncLocalToServer: Boolean(root.querySelector('#ym-delete-local-sync')?.checked),
		deleteSyncServerToLocal: Boolean(root.querySelector('#ym-delete-server-sync')?.checked),
	})

	const loadSettings = async () => {
		state.settings = await request('/api/settings/basic')
		render()
	}

	const checkHealth = async () => {
		state.checking = true
		render()
		try {
			state.health = await request('/api/settings/realtime-health')
		} catch (error) {
			OC.Notification.showTemporary(error.message || t(appId, 'There was an error while setting up your account'))
		} finally {
			state.checking = false
			render()
		}
	}

	const saveSettings = async () => {
		state.saving = true
		render()
		try {
			state.settings = await request('/api/settings/basic', {
				method: 'PUT',
				body: collectSettings(),
			})
			OC.Notification.showTemporary(t(appId, 'Admin settings saved'))
			await checkHealth()
		} catch (error) {
			OC.Notification.showTemporary(error.message || t(appId, 'There was an error while setting up your account'))
		} finally {
			state.saving = false
			render()
		}
	}

	Promise.resolve()
		.then(loadSettings)
		.then(checkHealth)
		.catch((error) => {
			root.innerHTML = `<div class="settings-section"><p>${escapeHtml(error.message || t(appId, 'Request failed'))}</p></div>`
		})
})()
