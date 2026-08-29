(function() {
	'use strict'

	function init() {
		const appId = 'yoomail'
		const stateLoader = window.OCP?.InitialState
		const defaultSettings = {
			nativeNewMail: false,
			soundEnabled: true,
			toastEnabled: true,
			soundNewMail: true,
			soundSendSuccess: true,
			soundSendFail: true,
		}
		let settings = Object.assign({}, defaultSettings, stateLoader?.loadState?.(appId, 'notification-settings', {}) || {})
		const audioUrls = stateLoader?.loadState?.(appId, 'notification-audio-urls', {}) || {}

		if (!document.body) {
			return
		}

		const toastHost = document.createElement('div')
		toastHost.id = 'yoomail-notification-toast-host'
		document.body.appendChild(toastHost)

		const requestToken = OC.requestToken || document.head.getAttribute('data-requesttoken') || ''
		const audioCache = {}
		const recentMessageIds = new Map()

		function applySettings(nextSettings) {
			if (nextSettings && typeof nextSettings === 'object') {
				settings = Object.assign({}, defaultSettings, settings, nextSettings)
			}
		}

		function refreshSettings() {
			if (typeof fetch !== 'function') {
				return
			}

			fetch(OC.generateUrl(`/apps/${appId}/api/settings/notifications`), {
				method: 'GET',
				headers: {
					'Accept': 'application/json',
					'X-Requested-With': 'XMLHttpRequest',
					'requesttoken': requestToken,
				},
				credentials: 'same-origin',
			}).then(function(response) {
				if (!response.ok) {
					return null
				}
				return response.json()
			}).then(function(nextSettings) {
				applySettings(nextSettings)
			}).catch(function() {})
		}

		function tOrFallback(text, replacements) {
			try {
				return t(appId, text, replacements)
			} catch (error) {
				return text
			}
		}

		function createToast(title, body, onClick) {
			if (!settings.toastEnabled) {
				return
			}

			const toast = document.createElement('div')
			toast.className = 'yoomail-notification-toast'
			toast.innerHTML = `
			<button class="yoomail-notification-toast__close" type="button" aria-label="Close">&times;</button>
			<div class="yoomail-notification-toast__title"></div>
			<div class="yoomail-notification-toast__body"></div>
		`
			toast.querySelector('.yoomail-notification-toast__title').textContent = title
			toast.querySelector('.yoomail-notification-toast__body').textContent = body
			toast.addEventListener('click', function() {
				if (typeof onClick === 'function') {
					onClick()
				}
				toast.remove()
			})
			let autoHideTimer = null
			const dismissToast = function() {
				if (autoHideTimer !== null) {
					window.clearTimeout(autoHideTimer)
					autoHideTimer = null
				}
				toast.classList.remove('yoomail-notification-toast--visible')
				window.setTimeout(function() { toast.remove() }, 180)
			}
			toast.querySelector('.yoomail-notification-toast__close').addEventListener('click', function(event) {
				event.stopPropagation()
				dismissToast()
			})
			toastHost.appendChild(toast)
			window.setTimeout(function() {
				toast.classList.add('yoomail-notification-toast--visible')
			}, 10)
			autoHideTimer = window.setTimeout(dismissToast, 10000)
		}

	function buildThreadUrl(mailboxId, messageId) {
		return OC.generateUrl(`/apps/${appId}/box/${mailboxId}/thread/${messageId}`)
	}

	function playSound(kind) {
		if (!settings.soundEnabled) {
			return
		}

		if (kind === 'newMail' && !settings.soundNewMail) {
			return
		}
		if (kind === 'sendSuccess' && !settings.soundSendSuccess) {
			return
		}
		if (kind === 'sendFail' && !settings.soundSendFail) {
			return
		}

		const url = audioUrls[kind]
		if (!url) {
			return
		}

		if (!audioCache[kind]) {
			audioCache[kind] = new Audio(url)
			audioCache[kind].preload = 'auto'
		}

		const audio = audioCache[kind].cloneNode()
		audio.volume = 1
		const result = audio.play()
		if (result && typeof result.catch === 'function') {
			result.catch(function() {})
		}
	}

	function showNativeNotification(title, body, onClick) {
		if (!settings.nativeNewMail || !('Notification' in window) || Notification.permission !== 'granted') {
			return
		}

		if (!document.hidden) {
			return
		}

		const notification = new Notification(title, {
			body: body,
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

		function handleNewMail(detail) {
			const messages = Array.isArray(detail?.messages) ? detail.messages : []
			const claimNewMail = window.OCA?.YooMailGlobalNotifier?.claimNewMail
			if (typeof claimNewMail === 'function' && !claimNewMail(messages)) {
				return
			}

			const dedupedMessages = messages.filter(function(message) {
				const id = Number(message?.databaseId)
				if (!Number.isFinite(id)) {
					return true
				}

				if (recentMessageIds.has(id)) {
					return false
				}

				recentMessageIds.set(id, Date.now())
				window.setTimeout(function() {
					recentMessageIds.delete(id)
				}, 20000)
				return true
			})
			if (dedupedMessages.length === 0) {
				return
			}

			const first = dedupedMessages[0]
			const sender = first?.from?.[0]?.label || first?.from?.[0]?.email || tOrFallback('Unknown sender')
			const subject = first?.subject || tOrFallback('(No subject)')
			const messageCount = dedupedMessages.length
			const title = messageCount > 1
				? tOrFallback('You have {count} new emails', { count: messageCount })
				: tOrFallback('New mail from {sender}', { sender: sender })
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
			showNativeNotification(title, body, openMessage)
			playSound('newMail')
		}

	function handleSendSuccess(detail) {
		const subject = detail?.subject || tOrFallback('Your message was sent successfully')
		createToast(tOrFallback('Message sent'), subject)
		playSound('sendSuccess')
	}

	function handleSendFail(detail) {
		const subject = detail?.subject || tOrFallback('YooMail could not send this message')
		createToast(tOrFallback('Message sending failed'), subject)
		playSound('sendFail')
	}

	function parseRequestBody(body) {
		if (body && typeof FormData !== 'undefined' && body instanceof FormData) {
			return Object.fromEntries(body.entries())
		}

		if (body && typeof URLSearchParams !== 'undefined' && body instanceof URLSearchParams) {
			return Object.fromEntries(body.entries())
		}

		if (body && typeof body === 'object') {
			return body
		}

		if (typeof body !== 'string') {
			return null
		}

		try {
			return JSON.parse(body)
		} catch (error) {
			return null
		}
	}

	function readRequestSubject(body) {
		const parsedBody = parseRequestBody(body)
		return parsedBody?.subject || parsedBody?.data?.subject || ''
	}

		function detectTrackedRequest(method, url, body) {
			const normalizedMethod = String(method || 'GET').toUpperCase()
			const normalizedUrl = String(url || '')
			const subject = readRequestSubject(body)

		if (normalizedMethod === 'POST' && (
			/\/apps\/yoomail\/api\/messages(?:\?|$)/.test(normalizedUrl)
			|| /\/(?:ocs\/v\d+\.php\/)?apps\/(?:yoomail|mail)\/(?:api\/v\d+\/)?message\/send(?:\?|$)/.test(normalizedUrl)
		)) {
			return {
				type: 'send',
				subject: subject,
			}
		}

			if (normalizedMethod === 'POST' && /\/apps\/yoomail\/api\/outbox\/\d+(?:\?|$)/.test(normalizedUrl)) {
				return {
					type: 'outbox-send',
					subject: subject,
				}
			}

			if (normalizedMethod === 'POST' && /\/apps\/yoomail\/api\/mailboxes\/\d+\/sync(?:\?|$)/.test(normalizedUrl)) {
				return {
					type: 'mail-sync',
				}
			}

			return null
		}

		function readNewMessagesFromPayload(payload) {
			const messages = Array.isArray(payload?.newMessages)
				? payload.newMessages
				: Array.isArray(payload?.data?.newMessages)
					? payload.data.newMessages
					: []

			return messages
		}

		function isInboxMessage(message) {
			const mailboxId = Number(message?.mailboxId)
			if (!Number.isFinite(mailboxId)) {
				return false
			}

			const store = window.OCA?.YooMailRealtime?.getMainStore?.()
			const mailbox = store?.getMailbox?.(mailboxId)
			return mailbox?.specialRole === 'inbox'
		}

		function reportTrackedResult(meta, status, payload) {
			if (!meta) {
				return
			}

			if (meta.type === 'mail-sync' && status === 200) {
				const messages = readNewMessagesFromPayload(payload)
				if (messages.length > 0 && messages.some(isInboxMessage)) {
					handleNewMail({ messages: messages })
				}
				return
			}

			if ((meta.type === 'send' || meta.type === 'outbox-send') && (status === 200 || status === 202)) {
				handleSendSuccess({ subject: meta.subject })
			return
		}

		if ((meta.type === 'send' || meta.type === 'outbox-send') && status >= 400) {
			handleSendFail({ subject: meta.subject })
		}
	}

	function patchFetch() {
		if (typeof window.fetch !== 'function' || window.fetch.__yoomailNotificationsPatched) {
			return
		}

		const originalFetch = window.fetch.bind(window)
		window.fetch = function(input, init) {
			const url = typeof input === 'string' ? input : input?.url
			const method = init?.method || input?.method || 'GET'
			const body = init?.body
			const meta = detectTrackedRequest(method, url, body)

			return originalFetch(input, init).then(function(response) {
				if (meta?.type === 'mail-sync') {
					response.clone().json().then(function(payload) {
						reportTrackedResult(meta, response.status, payload)
					}).catch(function() {})
				} else {
					reportTrackedResult(meta, response.status)
				}
				return response
			}).catch(function(error) {
				if (meta) {
					handleSendFail({ subject: meta.subject })
				}
				throw error
			})
		}
		window.fetch.__yoomailNotificationsPatched = true
	}

	function patchXhr() {
		if (window.XMLHttpRequest.prototype.__yoomailNotificationsPatched) {
			return
		}

		const originalOpen = window.XMLHttpRequest.prototype.open
		const originalSend = window.XMLHttpRequest.prototype.send

		window.XMLHttpRequest.prototype.open = function(method, url) {
			this.__yoomailNotificationMethod = method
			this.__yoomailNotificationUrl = url
			return originalOpen.apply(this, arguments)
		}

		window.XMLHttpRequest.prototype.send = function(body) {
			this.__yoomailNotificationMeta = detectTrackedRequest(
				this.__yoomailNotificationMethod,
				this.__yoomailNotificationUrl,
				body
			)

			this.addEventListener('loadend', function() {
				let payload = null
				if (this.__yoomailNotificationMeta?.type === 'mail-sync' && typeof this.responseText === 'string' && this.responseText !== '') {
					try {
						payload = JSON.parse(this.responseText)
					} catch (error) {
						payload = null
					}
				}
				reportTrackedResult(this.__yoomailNotificationMeta, this.status, payload)
			})

			return originalSend.apply(this, arguments)
		}

		window.XMLHttpRequest.prototype.__yoomailNotificationsPatched = true
	}

		function unlockAudio() {
		window.removeEventListener('pointerdown', unlockAudio)
		window.removeEventListener('keydown', unlockAudio)
		}

	toastHost.innerHTML = `
		<style>
			#yoomail-notification-toast-host {
				position: fixed;
				right: 20px;
				bottom: 20px;
				z-index: 99999;
				display: flex;
				flex-direction: column;
				gap: 12px;
				pointer-events: none;
				}
				.yoomail-notification-toast {
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
				.yoomail-notification-toast--visible {
					transform: translateY(0);
					opacity: 1;
				}
				.yoomail-notification-toast__title {
					color: var(--color-main-text);
					font-size: 15px;
					font-weight: 700;
					line-height: 1.35;
					margin-bottom: 6px;
				}
				.yoomail-notification-toast__close {
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
				.yoomail-notification-toast__close:hover {
					background: var(--color-background-hover);
					color: var(--color-main-text);
				}
				.yoomail-notification-toast__body {
					color: var(--color-main-text);
					font-size: 14px;
					line-height: 1.5;
				}
			</style>
	`

		window.addEventListener('pointerdown', unlockAudio, { once: true })
		window.addEventListener('keydown', unlockAudio, { once: true })
		window.addEventListener('storage', function(event) {
			if (event.key !== 'yoomail-notification-settings-updated' || !event.newValue) {
				return
			}

			try {
				const payload = JSON.parse(event.newValue)
				applySettings(payload?.settings)
			} catch (error) {
			}
		})
		window.addEventListener('yoomail:new-mail', function(event) {
			handleNewMail(event.detail)
		})

		refreshSettings()
		window.setInterval(refreshSettings, 15000)
		patchFetch()
		patchXhr()
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init, { once: true })
	} else {
		init()
	}
})()
