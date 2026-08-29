/**
 * SPDX-FileCopyrightText: 2026
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const APP_ID = 'yoomail'
const TAB_ID = `${Date.now()}-${Math.random().toString(36).slice(2)}`
const LEADER_KEY = 'yoomail-global-notifier-leader'
const CLAIM_PREFIX = 'yoomail-global-notifier-claim:'
const NOTIFICATION_KEY = 'yoomail-global-notifier-new-mail'
const APP_ICON_ALERT_KEY = 'yoomail-global-notifier-app-icon-alert'
const SETTINGS_EVENT_KEY = 'yoomail-notification-settings-updated'
const LEASE_MS = 12000
const HEARTBEAT_MS = 4000
const CLAIM_TTL_MS = 20000
const SETTINGS_REFRESH_MS = 30000
const RECONNECT_DELAY_MS = 5000
const MAX_RECONNECT_DELAY_MS = 30000

const defaultSettings = {
	nativeNewMail: false,
	soundEnabled: true,
	toastEnabled: true,
	topAppIconEnabled: true,
	soundNewMail: true,
	soundSendSuccess: true,
	soundSendFail: true,
}

let settings = normalizeSettings(loadInitialState('notification-settings', {}))
let audioUrls = loadInitialState('notification-audio-urls', {}) || {}
let socket = null
let reconnectTimer = null
let reconnectAttempts = 0
let heartbeatTimer = null
let toastHost = null
let appIconStyle = null
let appMenuObserver = null
let isStopped = false
const audioCache = {}
const memoryClaims = new Map()
const visibleNotifications = new Map()
const diagnostics = {
	tabId: TAB_ID,
	loadedAt: new Date().toISOString(),
	lastSettingsRefreshAt: null,
	lastTokenAt: null,
	lastSocketOpenAt: null,
	lastSocketMessageAt: null,
	lastBroadcastAt: null,
	lastToastAt: null,
	lastError: null,
}

function setRuntimeHealth(state) {
	document.documentElement?.setAttribute('data-yoomail-site-runtime', state)
}

function setRuntimeDetail(key, value) {
	document.documentElement?.setAttribute(`data-yoomail-site-runtime-${key}`, String(value))
}

function toBoolean(value) {
	return value === true
		|| value === 1
		|| value === '1'
		|| value === 'true'
		|| value === 'yes'
		|| value === 'on'
}

function normalizeSettings(nextSettings) {
	const merged = Object.assign({}, defaultSettings, nextSettings || {})

	return Object.fromEntries(
		Object.entries(merged).map(([key, value]) => [key, toBoolean(value)]),
	)
}

function loadInitialState(key, fallback) {
	try {
		return window.OCP?.InitialState?.loadState?.(APP_ID, key, fallback) ?? fallback
	} catch (error) {
		return fallback
	}
}

function generateAppUrl(path) {
	if (typeof window.OC?.generateUrl === 'function') {
		return window.OC.generateUrl(path)
	}

	return path
}

function hasEnabledNewMailChannel() {
	return settings.nativeNewMail
		|| settings.toastEnabled
		|| settings.topAppIconEnabled
		|| (settings.soundEnabled && settings.soundNewMail)
}

function tOrFallback(text, replacements) {
	try {
		return window.t ? window.t(APP_ID, text, replacements) : text
	} catch (error) {
		return text
	}
}

function readJson(key) {
	try {
		const value = window.localStorage.getItem(key)
		return value ? JSON.parse(value) : null
	} catch (error) {
		return null
	}
}

function writeJson(key, value) {
	try {
		window.localStorage.setItem(key, JSON.stringify(value))
	} catch (error) {
	}
}

function isYooMailPage() {
	return window.location.pathname.startsWith(generateAppUrl(`/apps/${APP_ID}`))
}

function topAppLink() {
	return document.querySelector(`.app-menu-entry__link[href*="/apps/${APP_ID}"]`)
}

function ensureAppIconStyle() {
	if (appIconStyle !== null || !document.head) {
		return
	}

	appIconStyle = document.createElement('style')
	appIconStyle.id = 'yoomail-global-app-icon-alert-style'
	appIconStyle.textContent = `
		.app-menu-entry__link.yoomail-app-icon-alert {
			position: relative;
		}
		.app-menu-entry__link.yoomail-app-icon-alert::after {
			content: "";
			position: absolute;
			top: 8px;
			right: 8px;
			width: 8px;
			height: 8px;
			border: 2px solid color-mix(in srgb, var(--color-main-background) 92%, transparent);
			border-radius: 50%;
			background: var(--color-primary-element);
			box-shadow:
				0 0 0 1px color-mix(in srgb, var(--color-main-text) 14%, transparent),
				0 2px 6px color-mix(in srgb, var(--color-primary-element) 42%, transparent);
			animation: yoomail-app-icon-alert-pulse 2.4s ease-out infinite;
			pointer-events: none;
		}
		@keyframes yoomail-app-icon-alert-pulse {
			0% {
				box-shadow:
					0 0 0 1px color-mix(in srgb, var(--color-main-text) 14%, transparent),
					0 0 0 0 color-mix(in srgb, var(--color-primary-element) 32%, transparent);
			}
			70% {
				box-shadow:
					0 0 0 1px color-mix(in srgb, var(--color-main-text) 10%, transparent),
					0 0 0 6px transparent;
			}
			100% {
				box-shadow:
					0 0 0 1px color-mix(in srgb, var(--color-main-text) 10%, transparent),
					0 0 0 0 transparent;
			}
		}
		@media (prefers-reduced-motion: reduce) {
			.app-menu-entry__link.yoomail-app-icon-alert::after {
				animation: none;
			}
		}
	`
	document.head.appendChild(appIconStyle)
}

function setTopAppIconAlert(enabled) {
	ensureAppIconStyle()
	const link = topAppLink()
	if (!link) {
		return
	}

	link.classList.toggle('yoomail-app-icon-alert', enabled)
}

function persistTopAppIconAlert(enabled) {
	if (enabled) {
		writeJson(APP_ICON_ALERT_KEY, {
			active: true,
			updatedAt: Date.now(),
		})
		return
	}

	removeStorage(APP_ICON_ALERT_KEY)
}

function applyTopAppIconAlertFromStorage() {
	if (isYooMailPage()) {
		persistTopAppIconAlert(false)
		setTopAppIconAlert(false)
		return
	}

	setTopAppIconAlert(readJson(APP_ICON_ALERT_KEY)?.active === true)
}

function watchTopAppIcon() {
	if (appMenuObserver !== null || !document.body) {
		return
	}

	appMenuObserver = new MutationObserver(function() {
		applyTopAppIconAlertFromStorage()
	})
	appMenuObserver.observe(document.body, {
		childList: true,
		subtree: true,
	})
}

function markTopAppIconAlert() {
	if (!settings.topAppIconEnabled) {
		persistTopAppIconAlert(false)
		setTopAppIconAlert(false)
		return
	}

	if (isYooMailPage()) {
		return
	}

	persistTopAppIconAlert(true)
	setTopAppIconAlert(true)
}

function removeStorage(key) {
	try {
		window.localStorage.removeItem(key)
	} catch (error) {
	}
}

function currentLeader() {
	return readJson(LEADER_KEY)
}

function writeLeaderLease() {
	writeJson(LEADER_KEY, {
		id: TAB_ID,
		visible: !document.hidden,
		expiresAt: Date.now() + LEASE_MS,
	})
}

function shouldOwnLeaderLease() {
	const leader = currentLeader()
	return !leader
		|| leader.id === TAB_ID
		|| (!document.hidden && leader.visible !== true)
		|| !Number.isFinite(Number(leader.expiresAt))
		|| Number(leader.expiresAt) <= Date.now()
}

function ownsLeaderLease() {
	const leader = currentLeader()
	return leader?.id === TAB_ID && Number(leader.expiresAt) > Date.now()
}

function startHeartbeat() {
	if (heartbeatTimer !== null) {
		return
	}

	writeLeaderLease()
	heartbeatTimer = window.setInterval(function() {
		if (!hasEnabledNewMailChannel()) {
			releaseLeadership()
			return
		}

		writeLeaderLease()
		ensureSocket()
	}, HEARTBEAT_MS)
}

function stopHeartbeat() {
	if (heartbeatTimer === null) {
		return
	}

	window.clearInterval(heartbeatTimer)
	heartbeatTimer = null
}

function releaseLeadership() {
	if (ownsLeaderLease()) {
		removeStorage(LEADER_KEY)
	}

	stopHeartbeat()
	closeSocket()
}

function updateLeadership() {
	if (isStopped || !hasEnabledNewMailChannel()) {
		releaseLeadership()
		return
	}

	if (shouldOwnLeaderLease()) {
		startHeartbeat()
		ensureSocket()
		return
	}

	stopHeartbeat()
	closeSocket()
}

async function refreshSettings() {
	try {
		const response = await window.fetch(generateAppUrl(`/apps/${APP_ID}/api/settings/notifications`), {
			method: 'GET',
			headers: {
				Accept: 'application/json',
				'X-Requested-With': 'XMLHttpRequest',
				requesttoken: window.OC?.requestToken || '',
			},
			credentials: 'same-origin',
		})
		if (!response.ok) {
			setRuntimeHealth('settings-unavailable')
			return
		}

		settings = normalizeSettings(await response.json())
		diagnostics.lastSettingsRefreshAt = new Date().toISOString()
		setRuntimeHealth('settings-ready')
		if (!settings.topAppIconEnabled) {
			persistTopAppIconAlert(false)
			setTopAppIconAlert(false)
		} else {
			applyTopAppIconAlertFromStorage()
		}
		updateLeadership()
	} catch (error) {
		diagnostics.lastError = `settings: ${error?.message || error}`
		setRuntimeHealth('settings-error')
	}
}

async function fetchRealtimeToken() {
	const response = await window.fetch(generateAppUrl(`/apps/${APP_ID}/api/realtime/token`), {
		method: 'POST',
		headers: {
			Accept: 'application/json',
			'X-Requested-With': 'XMLHttpRequest',
			requesttoken: window.OC?.requestToken || '',
		},
		credentials: 'same-origin',
	})
	if (!response.ok) {
		diagnostics.lastError = `token: HTTP ${response.status}`
		return null
	}

	diagnostics.lastTokenAt = new Date().toISOString()
	return response.json()
}

function closeSocket() {
	if (reconnectTimer !== null) {
		window.clearTimeout(reconnectTimer)
		reconnectTimer = null
	}

	if (socket !== null) {
		socket.onopen = null
		socket.onmessage = null
		socket.onclose = null
		socket.onerror = null
		socket.close()
		socket = null
	}
}

function scheduleReconnect() {
	if (isStopped || !ownsLeaderLease() || !hasEnabledNewMailChannel() || reconnectTimer !== null) {
		return
	}

	const delay = Math.min(MAX_RECONNECT_DELAY_MS, RECONNECT_DELAY_MS * (reconnectAttempts + 1))
	reconnectAttempts += 1
	reconnectTimer = window.setTimeout(function() {
		reconnectTimer = null
		ensureSocket()
	}, delay)
}

async function ensureSocket() {
	if (!ownsLeaderLease() || !hasEnabledNewMailChannel()) {
		return
	}

	if (socket !== null && (socket.readyState === WebSocket.OPEN || socket.readyState === WebSocket.CONNECTING)) {
		return
	}

	try {
		const auth = await fetchRealtimeToken()
		if (!auth?.token || !auth?.wsUrl || !ownsLeaderLease()) {
			return
		}

		const nextSocket = new WebSocket(auth.wsUrl)
		socket = nextSocket
		nextSocket.onopen = function() {
			reconnectAttempts = 0
			diagnostics.lastSocketOpenAt = new Date().toISOString()
			setRuntimeHealth('socket-open')
			nextSocket.send(JSON.stringify({
				type: 'auth',
				token: auth.token,
			}))
		}
		nextSocket.onmessage = function(event) {
			handleSocketMessage(event.data)
		}
		nextSocket.onclose = function() {
			if (socket === nextSocket) {
				socket = null
			}
			scheduleReconnect()
		}
		nextSocket.onerror = function() {
			nextSocket.close()
		}
	} catch (error) {
		diagnostics.lastError = `socket: ${error?.message || error}`
		setRuntimeHealth('socket-error')
		scheduleReconnect()
	}
}

function handleSocketMessage(data) {
	let message = null
	try {
		message = JSON.parse(data)
	} catch (error) {
		diagnostics.lastError = `message: ${error?.message || error}`
		return
	}

	diagnostics.lastSocketMessageAt = new Date().toISOString()
	setRuntimeHealth('socket-message')
	setRuntimeDetail('type', message?.type || 'unknown')
	setRuntimeDetail('sync', message?.sync || 'unknown')
	if (message?.type !== 'sync-done' || message.sync !== 'ok') {
		setRuntimeHealth('ignored-event')
		return
	}

	handleRealtimePayload(message)
}

function handleRealtimePayload(message) {
	const payload = message?.realtimePayload
	if (!payload) {
		setRuntimeHealth('ignored-no-payload')
		return
	}

	setRuntimeDetail('role', payload.mailboxRole || 'unknown')
	if (payload.mailboxRole && payload.mailboxRole !== 'inbox') {
		setRuntimeHealth('ignored-role')
		return
	}

	const messages = normalizeMessages(payload.newMessages, message)
	setRuntimeDetail('new-messages', messages.length)
	if (messages.length === 0) {
		setRuntimeHealth('ignored-empty')
		return
	}

	if (!claimNewMail(messages)) {
		setRuntimeHealth('ignored-duplicate')
		return
	}

	setRuntimeHealth('new-mail')
	markTopAppIconAlert()
	broadcastNewMail(messages)
	notifyNewMail(messages)
}

function normalizeMessages(messages, message) {
	if (!Array.isArray(messages)) {
		return []
	}

	return messages
		.filter((item) => item && typeof item === 'object')
		.map((item) => Object.assign({}, item, {
			accountId: item.accountId ?? message.accountId,
			mailboxId: item.mailboxId ?? message.mailboxId,
		}))
}

function hashString(value) {
	let hash = 0
	for (let i = 0; i < value.length; i++) {
		hash = ((hash << 5) - hash) + value.charCodeAt(i)
		hash |= 0
	}

	return Math.abs(hash).toString(36)
}

function claimKey(messages) {
	const ids = messages.map(function(message, index) {
		const mailboxId = message?.mailboxId ?? 'mailbox'
		const id = message?.databaseId ?? message?.messageId ?? message?.uid ?? index
		return `${mailboxId}:${id}`
	}).sort()

	return `${CLAIM_PREFIX}${hashString(ids.join('|'))}`
}

function claimNewMail(messages) {
	if (!Array.isArray(messages) || messages.length === 0) {
		return false
	}

	const now = Date.now()
	for (const [key, expiresAt] of memoryClaims.entries()) {
		if (expiresAt <= now) {
			memoryClaims.delete(key)
		}
	}

	const key = claimKey(messages)
	const memoryExpiresAt = memoryClaims.get(key)
	if (memoryExpiresAt && memoryExpiresAt > now) {
		return false
	}

	const stored = readJson(key)
	if (stored?.expiresAt && Number(stored.expiresAt) > now) {
		return false
	}

	const expiresAt = now + CLAIM_TTL_MS
	memoryClaims.set(key, expiresAt)
	writeJson(key, {
		tabId: TAB_ID,
		expiresAt,
	})

	return true
}

function notificationKey(messages) {
	return claimKey(messages).replace(CLAIM_PREFIX, '')
}

function rememberVisibleNotification(key) {
	const now = Date.now()
	for (const [itemKey, expiresAt] of visibleNotifications.entries()) {
		if (expiresAt <= now) {
			visibleNotifications.delete(itemKey)
		}
	}

	if (visibleNotifications.get(key) > now) {
		return false
	}

	visibleNotifications.set(key, now + CLAIM_TTL_MS)
	return true
}

function broadcastNewMail(messages) {
	diagnostics.lastBroadcastAt = new Date().toISOString()
	writeJson(NOTIFICATION_KEY, {
		key: notificationKey(messages),
		messages,
		createdAt: Date.now(),
	})
	window.setTimeout(function() {
		removeStorage(NOTIFICATION_KEY)
	}, 1000)
}

function handleBroadcastNewMail(payload) {
	if (!payload?.key || !Array.isArray(payload.messages)) {
		return
	}

	if (Number(payload.createdAt || 0) + CLAIM_TTL_MS < Date.now()) {
		return
	}

	if (!rememberVisibleNotification(payload.key)) {
		return
	}

	markTopAppIconAlert()
	notifyNewMail(payload.messages, true)
}

function ensureToastHost() {
	if (toastHost !== null || !document.body) {
		return toastHost
	}

	toastHost = document.createElement('div')
	toastHost.id = 'yoomail-global-notification-toast-host'
	toastHost.innerHTML = `
		<style>
			#yoomail-global-notification-toast-host {
				position: fixed;
				right: 20px;
				bottom: 20px;
				z-index: 99999;
				display: flex;
				flex-direction: column;
				gap: 12px;
				pointer-events: none;
			}
			.yoomail-global-notification-toast {
				position: relative;
				width: min(400px, calc(100vw - 32px));
				padding: 16px 18px 16px 20px;
				border: 1px solid var(--color-border);
				border-inline-start: 4px solid var(--color-primary-element);
				border-radius: var(--border-radius-large, 14px);
				background: var(--color-main-background);
				color: var(--color-main-text);
				text-align: left;
				box-shadow: 0 16px 44px rgba(0, 0, 0, 0.22);
				transform: translateY(12px);
				opacity: 0;
				transition: transform 160ms ease, opacity 160ms ease;
				pointer-events: auto;
				cursor: pointer;
				overflow: hidden;
			}
			.yoomail-global-notification-toast--visible {
				transform: translateY(0);
				opacity: 1;
			}
			.yoomail-global-notification-toast__title {
				color: var(--color-main-text);
				font-size: 15px;
				font-weight: 700;
				line-height: 1.35;
				margin-bottom: 6px;
			}
			.yoomail-global-notification-toast__close {
				position: absolute;
				top: 4px;
				right: 6px;
				border: none;
				background: transparent;
				color: var(--color-text-maxcontrast);
				font-size: 16px;
				line-height: 1;
				padding: 4px 7px;
				cursor: pointer;
				border-radius: var(--border-radius-small, 6px);
				z-index: 1;
			}
			.yoomail-global-notification-toast__close:hover {
				background: var(--color-background-hover);
				color: var(--color-main-text);
			}
			.yoomail-global-notification-toast__body {
				color: var(--color-main-text);
				font-size: 14px;
				line-height: 1.5;
			}
		</style>
	`
	document.body.appendChild(toastHost)

	return toastHost
}

function createToast(title, body, onClick) {
	if (!settings.toastEnabled) {
		setRuntimeHealth('toast-disabled')
		return
	}

	const host = ensureToastHost()
	if (host === null) {
		setRuntimeHealth('toast-host-unavailable')
		return
	}

	diagnostics.lastToastAt = new Date().toISOString()
	setRuntimeHealth('toast-created')
	const toast = document.createElement('div')
	toast.className = 'yoomail-global-notification-toast'
	toast.innerHTML = `
		<button class="yoomail-global-notification-toast__close" type="button" aria-label="Close">&times;</button>
		<div class="yoomail-global-notification-toast__title"></div>
		<div class="yoomail-global-notification-toast__body"></div>
	`
	toast.querySelector('.yoomail-global-notification-toast__title').textContent = title
	toast.querySelector('.yoomail-global-notification-toast__body').textContent = body
	toast.addEventListener('click', function() {
		if (typeof onClick === 'function') {
			onClick()
		}
		toast.remove()
	})
	// Manual close via the × button: dismiss this toast only (not the
	// notification feature) and cancel the auto-dismiss timer.
	let autoHideTimer = null
	const dismissToast = function() {
		if (autoHideTimer !== null) {
			window.clearTimeout(autoHideTimer)
			autoHideTimer = null
		}
		toast.classList.remove('yoomail-global-notification-toast--visible')
		window.setTimeout(function() {
			toast.remove()
		}, 180)
	}
	toast.querySelector('.yoomail-global-notification-toast__close').addEventListener('click', function(event) {
		event.stopPropagation()
		dismissToast()
	})
	host.appendChild(toast)
	window.setTimeout(function() {
		toast.classList.add('yoomail-global-notification-toast--visible')
	}, 10)
	autoHideTimer = window.setTimeout(dismissToast, 10000)
}

function playSound() {
	if (!settings.soundEnabled || !settings.soundNewMail) {
		return
	}

	const url = audioUrls.newMail
	if (!url) {
		return
	}

	if (!audioCache.newMail) {
		audioCache.newMail = new Audio(url)
		audioCache.newMail.preload = 'auto'
	}

	const audio = audioCache.newMail.cloneNode()
	const result = audio.play()
	if (result && typeof result.catch === 'function') {
		result.catch(function() {})
	}
}

function showNativeNotification(title, body, onClick) {
	if (!settings.nativeNewMail || !('Notification' in window) || Notification.permission !== 'granted' || !document.hidden) {
		return
	}

	const notification = new Notification(title, {
		body,
		tag: 'yoomail-new-mail',
	})
	notification.onclick = function() {
		window.focus()
		if (typeof onClick === 'function') {
			onClick()
		}
	}
	window.setTimeout(function() {
		notification.close()
	}, 8000)
}

function buildThreadUrl(mailboxId, messageId) {
	return generateAppUrl(`/apps/${APP_ID}/box/${mailboxId}/thread/${messageId}`)
}

function notifyNewMail(messages, broadcast = false) {
	if (broadcast && document.hidden) {
		return
	}

	const first = messages[0]
	const sender = first?.from?.[0]?.label || first?.from?.[0]?.email || tOrFallback('Unknown sender')
	const subject = first?.subject || tOrFallback('(No subject)')
	const messageCount = messages.length
	const title = messageCount > 1
		? tOrFallback('You have {count} new emails', { count: messageCount })
		: tOrFallback('New mail from {sender}', { sender })
	const body = messageCount > 1
		? subject
		: `${sender} · ${subject}`
	const openMessage = function() {
		const mailboxId = Number(first?.mailboxId)
		const messageId = Number(first?.databaseId)
		if (Number.isFinite(mailboxId) && Number.isFinite(messageId)) {
			window.location.href = buildThreadUrl(mailboxId, messageId)
		}
	}

	createToast(title, body, openMessage)
	if (!broadcast) {
		showNativeNotification(title, body, openMessage)
		playSound()
	}
}

function applyLocalSettings(nextSettings) {
	settings = normalizeSettings(Object.assign({}, settings, nextSettings || {}))
	if (!settings.topAppIconEnabled) {
		persistTopAppIconAlert(false)
		setTopAppIconAlert(false)
	}
	updateLeadership()
}

function init() {
	if (window.OCA?.YooMailGlobalNotifier?.initialized) {
		return
	}

	window.OCA = window.OCA || {}
	setRuntimeHealth('initialized')
	window.OCA.YooMailGlobalNotifier = {
		initialized: true,
		claimNewMail,
		getStatus: function() {
			return Object.assign({}, diagnostics, {
				settings,
				hasEnabledNewMailChannel: hasEnabledNewMailChannel(),
				leader: currentLeader(),
				ownsLeader: ownsLeaderLease(),
				socketReadyState: socket?.readyState ?? null,
				documentHidden: document.hidden,
				toastHostExists: toastHost !== null,
			})
		},
		testToast: function() {
			notifyNewMail([{
				databaseId: 0,
				mailboxId: 0,
				subject: 'YooMail notification test',
				from: [{ label: 'YooMail' }],
			}], true)
		},
	}

	window.addEventListener('storage', function(event) {
		if (event.key === LEADER_KEY) {
			updateLeadership()
			return
		}

		if (event.key === NOTIFICATION_KEY && event.newValue) {
			try {
				handleBroadcastNewMail(JSON.parse(event.newValue))
			} catch (error) {
			}
			return
		}

		if (event.key === APP_ICON_ALERT_KEY) {
			applyTopAppIconAlertFromStorage()
			return
		}

		if (event.key !== SETTINGS_EVENT_KEY || !event.newValue) {
			return
		}

		try {
			const payload = JSON.parse(event.newValue)
			applyLocalSettings(payload?.settings)
		} catch (error) {
		}
	})
	window.addEventListener('pagehide', function() {
		isStopped = true
		releaseLeadership()
	})
	document.addEventListener('visibilitychange', updateLeadership)
	watchTopAppIcon()
	document.addEventListener('click', function(event) {
		if (event.target?.closest?.(`.app-menu-entry__link[href*="/apps/${APP_ID}"]`)) {
			persistTopAppIconAlert(false)
			setTopAppIconAlert(false)
		}
	})

	applyTopAppIconAlertFromStorage()
	refreshSettings()
	window.setInterval(refreshSettings, SETTINGS_REFRESH_MS)
	updateLeadership()
}

// This is registered through Nextcloud's init-script queue. It can be emitted
// after DOMContentLoaded on app pages, and does not need the document body.
init()
