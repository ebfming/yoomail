/**
 * SPDX-FileCopyrightText: 2026
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Realtime WebSocket client for the Nextcloud Mail realtime service.
 *
 * Flow:
 *  1. Request a short-lived token from the Mail app HTTP API.
 *  2. Open a WebSocket to the realtime service (wsUrl returned by the API).
 *  3. Authenticate with the token.
 *  4. On `mailbox-changed`, ask the main store to refresh the relevant
 *     mailbox list.
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import logger from './logger.js'

const RECONNECT_DELAY_MS = 5000
const MAX_RECONNECT_DELAY_MS = 30000

class RealtimeClient {
	constructor() {
		this.ws = null
		this.reconnectTimer = null
		this.reconnectAttempts = 0
		this.stopped = false
		this.token = null
		this.wsUrl = null
		this.pendingMailboxChanges = []
		this.pendingFlushTimer = null
	}

	async start() {
		this.stopped = false
		await this.ensureConnected(true)
	}

	async fetchCredentials() {
		try {
			const { data } = await axios.post(generateUrl('/apps/yoomail/api/realtime/token'))
			this.token = data.token
			this.wsUrl = data.wsUrl
			return true
		} catch (error) {
			logger.debug('yoomail-realtime: could not obtain realtime token', { error })
			return false
		}
	}

	async ensureConnected(forceRefresh = false) {
		if (this.stopped) {
			return
		}

		if (this.ws && (this.ws.readyState === WebSocket.OPEN || this.ws.readyState === WebSocket.CONNECTING)) {
			return
		}

		if (forceRefresh || !this.token || !this.wsUrl) {
			const ok = await this.fetchCredentials()
			if (!ok) {
				this.scheduleReconnect()
				return
			}
		}

		this.connect()
	}

	connect() {
		if (this.stopped || !this.wsUrl || !this.token) {
			return
		}
		if (this.ws && (this.ws.readyState === WebSocket.OPEN || this.ws.readyState === WebSocket.CONNECTING)) {
			return
		}

		try {
			this.ws = new WebSocket(this.wsUrl)
		} catch (error) {
			logger.error('yoomail-realtime: WebSocket creation failed', { error })
			this.scheduleReconnect()
			return
		}

		this.ws.onopen = () => {
			logger.debug('yoomail-realtime: connected, authenticating')
			this.reconnectAttempts = 0
			this.ws.send(JSON.stringify({ type: 'auth', token: this.token }))
			this.flushPendingMailboxChanges()
		}

		this.ws.onmessage = (event) => {
			this.handleMessage(event.data)
		}

		this.ws.onclose = () => {
			logger.debug('yoomail-realtime: connection closed')
			this.ws = null
			this.scheduleReconnect()
		}

		this.ws.onerror = () => {
			logger.debug('yoomail-realtime: connection error')
		}
	}

	handleMessage(raw) {
		let msg
		try {
			msg = JSON.parse(raw)
		} catch (error) {
			return
		}

		if (msg.type === 'connected') {
			logger.debug('yoomail-realtime: authenticated')
			return
		}

		if (msg.type === 'sync-done') {
			logger.debug('yoomail-realtime: sync done', msg)
			if (!window.OCA?.YooMailRealtime?.applySyncDone?.(msg)) {
				this.onMailboxChanged(msg)
			}
			return
		}

		if (msg.type === 'mailbox-changed') {
			if (msg.sync === 'started') {
				logger.debug('yoomail-realtime: mailbox changed, waiting for sync-done', msg)
				return
			}
			logger.debug('yoomail-realtime: mailbox changed', msg)
			this.onMailboxChanged(msg)
		}
	}

	onMailboxChanged({ mailboxId, accountId }) {
		// The push event carries accountId (+ optionally mailboxId in the
		// future). Refresh the current mailbox list(s) for this account.
		const store = window.OCA?.YooMailRealtime?.getMainStore?.()
		if (!store) {
			this.queueMailboxChange({ mailboxId, accountId })
			return
		}
		try {
			store.syncEnvelopes({
				mailboxId: mailboxId ?? store.currentMailboxId ?? undefined,
				accountId,
			})
		} catch (error) {
			logger.error('yoomail-realtime: failed to refresh mailbox', { error })
		}
	}

	scheduleReconnect() {
		if (this.stopped) {
			return
		}
		if (this.reconnectTimer) {
			return
		}
		const delay = Math.min(
			RECONNECT_DELAY_MS * (2 ** this.reconnectAttempts),
			MAX_RECONNECT_DELAY_MS,
		)
		this.reconnectAttempts++
		logger.debug(`yoomail-realtime: reconnecting in ${delay}ms`)
		this.reconnectTimer = setTimeout(() => {
			this.reconnectTimer = null
			void this.ensureConnected(true)
		}, delay)
	}

	queueMailboxChange(change) {
		const exists = this.pendingMailboxChanges.some((item) => item.accountId === change.accountId && item.mailboxId === change.mailboxId)
		if (!exists) {
			this.pendingMailboxChanges.push(change)
		}

		if (this.pendingFlushTimer) {
			return
		}

		this.pendingFlushTimer = setTimeout(() => {
			this.pendingFlushTimer = null
			this.flushPendingMailboxChanges()
		}, 1000)
	}

	flushPendingMailboxChanges() {
		const store = window.OCA?.YooMailRealtime?.getMainStore?.()
		if (!store || this.pendingMailboxChanges.length === 0) {
			return
		}

		const pending = [...this.pendingMailboxChanges]
		this.pendingMailboxChanges = []
		for (const change of pending) {
			this.onMailboxChanged(change)
		}
	}

	resume() {
		if (this.reconnectTimer) {
			clearTimeout(this.reconnectTimer)
			this.reconnectTimer = null
		}
		void this.ensureConnected(true)
	}

	stop() {
		this.stopped = true
		if (this.reconnectTimer) {
			clearTimeout(this.reconnectTimer)
			this.reconnectTimer = null
		}
		if (this.ws) {
			this.ws.close()
			this.ws = null
		}
		if (this.pendingFlushTimer) {
			clearTimeout(this.pendingFlushTimer)
			this.pendingFlushTimer = null
		}
	}
}

let client = null

// Start after the page is ready so that the token API is available
window.addEventListener('DOMContentLoaded', () => {
	client = new RealtimeClient()
	client.start()
})

// Reconnect when the tab becomes visible again after being hidden
document.addEventListener('visibilitychange', () => {
	if (!document.hidden && client) {
		client.resume()
	}
})

export default RealtimeClient
