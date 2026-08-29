/**
 * SPDX-FileCopyrightText: 2026 TigerSprite Team
 * SPDX-License-Identifier: AGPL-3.0-only
 */

const appId = 'yoomail'

const state = {
	settings: null,
	health: null,
	saving: false,
	checking: false,
	googleOauthClientId: '',
	googleOauthRedirectUrl: '',
	googleOauthSaving: false,
}

const root = document.getElementById('yoomail-admin-basic-settings')

const requestToken = OC.requestToken || document.head.getAttribute('data-requesttoken') || ''

const apiUrl = (path) => OC.generateUrl(`/apps/${appId}${path}`)
const passwordPlaceholder = '*****'

const loadInitialState = (key, fallback) => {
	try {
		return window.OCP?.InitialState?.loadState?.(appId, key, fallback) ?? fallback
	} catch (error) {
		return fallback
	}
}

const request = async (path, options = {}) => {
	const response = await fetch(apiUrl(path), {
		method: options.method || 'GET',
		headers: {
			Accept: 'application/json',
			'Content-Type': 'application/json',
			'X-Requested-With': 'XMLHttpRequest',
			requesttoken: requestToken,
		},
		body: options.body ? JSON.stringify(options.body) : undefined,
		credentials: 'same-origin',
	})

	const data = await response.json().catch(() => ({}))
	if (!response.ok) {
		const message = Array.isArray(data?.ocs?.data?.message)
			? data.ocs.data.message.join(', ')
			: Array.isArray(data?.data)
				? data.data.join(', ')
				: data?.data?.message || data?.message
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
const normalizeSettings = (settings) => ({
	...settings,
	timeFormatDefault: String(settings?.timeFormatDefault ?? '24'),
	realtimeMode: String(settings?.realtimeMode ?? 'websocket'),
	fetchRangeDays: String(settings?.fetchRangeDays ?? '30'),
	deleteSyncLocalToServer: Boolean(settings?.deleteSyncLocalToServer),
	deleteSyncServerToLocal: Boolean(settings?.deleteSyncServerToLocal),
	bodyCacheCleanupEnabled: Boolean(settings?.bodyCacheCleanupEnabled),
	bodyCacheCleanupDays: Number(settings?.bodyCacheCleanupDays ?? 30),
	bodyCacheCleanupSizeMb: Number(settings?.bodyCacheCleanupSizeMb ?? 1024),
	localAttachmentCleanupEnabled: Boolean(settings?.localAttachmentCleanupEnabled),
	localAttachmentCleanupDays: Number(settings?.localAttachmentCleanupDays ?? 30),
	localAttachmentCleanupSizeMb: Number(settings?.localAttachmentCleanupSizeMb ?? 512),
})

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

			<div class="ym-admin-card">
				<h3>${escapeHtml(t(appId, 'Fetch range default'))}</h3>
				<p class="settings-hint">${escapeHtml(t(appId, 'This value is used as the default scope for first-time or rebuilt mailbox sync. It does not delete existing local mail.'))}</p>
				<p class="ym-admin-control-row">
					<input type="radio" class="radio" id="ym-fetch-range-7" name="ym-fetch-range-days" value="7" ${checked(settings.fetchRangeDays === '7')}>
					<label for="ym-fetch-range-7">${escapeHtml(t(appId, 'Last 7 days'))}</label>
				</p>
				<p class="ym-admin-control-row">
					<input type="radio" class="radio" id="ym-fetch-range-30" name="ym-fetch-range-days" value="30" ${checked(settings.fetchRangeDays === '30')}>
					<label for="ym-fetch-range-30">${escapeHtml(t(appId, 'Last 30 days'))}</label>
				</p>
				<p class="ym-admin-control-row">
					<input type="radio" class="radio" id="ym-fetch-range-90" name="ym-fetch-range-days" value="90" ${checked(settings.fetchRangeDays === '90')}>
					<label for="ym-fetch-range-90">${escapeHtml(t(appId, 'Last 90 days'))}</label>
				</p>
				<p class="ym-admin-control-row">
					<input type="radio" class="radio" id="ym-fetch-range-180" name="ym-fetch-range-days" value="180" ${checked(settings.fetchRangeDays === '180')}>
					<label for="ym-fetch-range-180">${escapeHtml(t(appId, 'Last 180 days'))}</label>
				</p>
				<p class="ym-admin-control-row">
					<input type="radio" class="radio" id="ym-fetch-range-all" name="ym-fetch-range-days" value="all" ${checked(settings.fetchRangeDays === 'all')}>
					<label for="ym-fetch-range-all">${escapeHtml(t(appId, 'Fetch all mail'))}</label>
				</p>
			</div>

			<div class="ym-admin-card">
				<h3>${escapeHtml(t(appId, 'Message body cache cleanup'))}</h3>
				<p class="ym-admin-control-row">
					<input type="checkbox" class="checkbox" id="ym-body-cache-cleanup-enabled" ${checked(settings.bodyCacheCleanupEnabled)}>
					<label for="ym-body-cache-cleanup-enabled">${escapeHtml(t(appId, 'Enable automatic cleanup for cached message bodies'))}</label>
				</p>
				<p class="settings-hint ym-admin-indented">${escapeHtml(t(appId, 'Cached message bodies are stored in Nextcloud appdata and are trimmed by age first, then by total size.'))}</p>
				<div class="ym-admin-inline-fields">
					<label for="ym-body-cache-cleanup-days">${escapeHtml(t(appId, 'Keep for days'))}</label>
					<input type="number" min="1" step="1" id="ym-body-cache-cleanup-days" value="${escapeHtml(settings.bodyCacheCleanupDays)}">
					<label for="ym-body-cache-cleanup-size">${escapeHtml(t(appId, 'Max size (MB)'))}</label>
					<input type="number" min="1" step="1" id="ym-body-cache-cleanup-size" value="${escapeHtml(settings.bodyCacheCleanupSizeMb)}">
				</div>
			</div>

			<div class="ym-admin-card">
				<h3>${escapeHtml(t(appId, 'Local attachment cleanup'))}</h3>
				<p class="ym-admin-control-row">
					<input type="checkbox" class="checkbox" id="ym-local-attachment-cleanup-enabled" ${checked(settings.localAttachmentCleanupEnabled)}>
					<label for="ym-local-attachment-cleanup-enabled">${escapeHtml(t(appId, 'Enable automatic cleanup for local draft attachments'))}</label>
				</p>
				<p class="settings-hint ym-admin-indented">${escapeHtml(t(appId, 'This cleanup only removes unattached local files. Attachments still linked to drafts or outbox messages are kept untouched.'))}</p>
				<div class="ym-admin-inline-fields">
					<label for="ym-local-attachment-cleanup-days">${escapeHtml(t(appId, 'Keep for days'))}</label>
					<input type="number" min="1" step="1" id="ym-local-attachment-cleanup-days" value="${escapeHtml(settings.localAttachmentCleanupDays)}">
					<label for="ym-local-attachment-cleanup-size">${escapeHtml(t(appId, 'Max size (MB)'))}</label>
					<input type="number" min="1" step="1" id="ym-local-attachment-cleanup-size" value="${escapeHtml(settings.localAttachmentCleanupSizeMb)}">
				</div>
			</div>

			<div class="ym-admin-card">
				<h3>${escapeHtml(t(appId, 'Gmail integration'))}</h3>
				<p class="settings-hint">${escapeHtml(t(appId, 'Gmail allows users to access their email via IMAP. For security reasons this access is only possible with an OAuth 2.0 connection or Google accounts that use two-factor authentication and app passwords.'))}</p>
				<p class="settings-hint">${escapeHtml(t(appId, 'You have to register a new Client ID for a "Web application" in the Google Cloud console. Add the URL {url} as authorized redirect URI.', {
					url: state.googleOauthRedirectUrl,
				}))}</p>
				<p><code>${escapeHtml(state.googleOauthRedirectUrl || '')}</code></p>
				<div class="ym-admin-oauth-form">
					<label for="ym-google-oauth-client-id">${escapeHtml(t(appId, 'Client ID'))}</label>
					<input type="text" id="ym-google-oauth-client-id" value="${escapeHtml(state.googleOauthClientId)}" ${state.googleOauthSaving ? 'disabled' : ''}>
					<label for="ym-google-oauth-client-secret">${escapeHtml(t(appId, 'Client secret'))}</label>
					<input type="password" id="ym-google-oauth-client-secret" value="${escapeHtml(state.googleOauthClientId ? passwordPlaceholder : '')}" ${state.googleOauthSaving ? 'disabled' : ''}>
				</div>
				<p class="ym-admin-card-actions">
					<button type="button" class="primary" id="ym-save-google-oauth" ${state.googleOauthSaving ? 'disabled' : ''}>${escapeHtml(state.googleOauthSaving ? t(appId, 'Save') + '...' : t(appId, 'Save'))}</button>
					<button type="button" id="ym-unlink-google-oauth" ${state.googleOauthSaving ? 'disabled' : ''}>${escapeHtml(t(appId, 'Unlink'))}</button>
				</p>
			</div>
			</div>
			<div class="ym-admin-actions">
				<button type="button" class="primary" id="ym-save-basic-settings" ${state.saving ? 'disabled' : ''}>${escapeHtml(state.saving ? t(appId, 'Save') + '...' : t(appId, 'Save'))}</button>
			</div>
		</div>
	`

	root.querySelector('#ym-check-health')?.addEventListener('click', checkHealth)
	root.querySelector('#ym-save-basic-settings')?.addEventListener('click', saveSettings)
	root.querySelector('#ym-save-google-oauth')?.addEventListener('click', saveGoogleOauth)
	root.querySelector('#ym-unlink-google-oauth')?.addEventListener('click', unlinkGoogleOauth)
}

const collectSettings = () => ({
	timeFormatDefault: root.querySelector('input[name="ym-time-format"]:checked')?.value || '24',
	realtimeMode: root.querySelector('input[name="ym-realtime-mode"]:checked')?.value || 'websocket',
	deleteSyncLocalToServer: Boolean(root.querySelector('#ym-delete-local-sync')?.checked),
	deleteSyncServerToLocal: Boolean(root.querySelector('#ym-delete-server-sync')?.checked),
	fetchRangeDays: root.querySelector('input[name="ym-fetch-range-days"]:checked')?.value || '30',
	bodyCacheCleanupEnabled: Boolean(root.querySelector('#ym-body-cache-cleanup-enabled')?.checked),
	bodyCacheCleanupDays: Number(root.querySelector('#ym-body-cache-cleanup-days')?.value || 30),
	bodyCacheCleanupSizeMb: Number(root.querySelector('#ym-body-cache-cleanup-size')?.value || 1024),
	localAttachmentCleanupEnabled: Boolean(root.querySelector('#ym-local-attachment-cleanup-enabled')?.checked),
	localAttachmentCleanupDays: Number(root.querySelector('#ym-local-attachment-cleanup-days')?.value || 30),
	localAttachmentCleanupSizeMb: Number(root.querySelector('#ym-local-attachment-cleanup-size')?.value || 512),
})

const loadSettings = async () => {
	state.settings = normalizeSettings(await request('/api/settings/basic'))
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
	const nextSettings = collectSettings()
	state.saving = true
	render()
	try {
		state.settings = normalizeSettings(await request('/api/settings/basic', {
			method: 'PUT',
			body: nextSettings,
		}))
		OC.Notification.showTemporary(t(appId, 'Admin settings saved'))
		await checkHealth()
	} catch (error) {
		OC.Notification.showTemporary(error.message || t(appId, 'There was an error while setting up your account'))
	} finally {
		state.saving = false
		render()
	}
}

const saveGoogleOauth = async () => {
	const clientId = root.querySelector('#ym-google-oauth-client-id')?.value?.trim() || ''
	const clientSecret = root.querySelector('#ym-google-oauth-client-secret')?.value || ''

	if (!clientId || !clientSecret || clientSecret === passwordPlaceholder) {
		OC.Notification.showTemporary(t(appId, 'Client ID and client secret are required'))
		return
	}

	state.googleOauthSaving = true
	render()
	try {
		const response = await request('/api/integration/google', {
			method: 'POST',
			body: {
				clientId,
				clientSecret,
			},
		})
		state.googleOauthClientId = response?.data?.clientId || clientId
		OC.Notification.showTemporary(t(appId, 'Google integration configured'))
	} catch (error) {
		OC.Notification.showTemporary(error.message || t(appId, 'Could not configure Google integration'))
	} finally {
		state.googleOauthSaving = false
		render()
	}
}

const unlinkGoogleOauth = async () => {
	state.googleOauthSaving = true
	render()
	try {
		await request('/api/integration/google', {
			method: 'DELETE',
		})
		state.googleOauthClientId = ''
		OC.Notification.showTemporary(t(appId, 'Google integration unlinked'))
	} catch (error) {
		OC.Notification.showTemporary(error.message || t(appId, 'Could not unlink Google integration'))
	} finally {
		state.googleOauthSaving = false
		render()
	}
}

if (root) {
	Promise.resolve()
		.then(() => {
			state.googleOauthClientId = loadInitialState('google_oauth_client_id', '') || ''
			state.googleOauthRedirectUrl = loadInitialState('google_oauth_redirect_url', '') || ''
		})
		.then(loadSettings)
		.then(checkHealth)
		.catch((error) => {
			root.innerHTML = `<div class="settings-section"><p>${escapeHtml(error.message || t(appId, 'Request failed'))}</p></div>`
		})
}
