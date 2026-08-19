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
	}

	async start() {
		this.stopped = false
		try {
			const { data } = await axios.post(generateUrl('/apps/yoomail/api/realtime/token'))
			this.token = data.token
			this.wsUrl = data.wsUrl
			this.connect()
		} catch (error) {
			logger.debug('mail-realtime: could not obtain realtime token', { error })
		}
	}

	connect() {
		if (this.stopped || !this.wsUrl || !this.token) {
			return
		}
		try {
			this.ws = new WebSocket(this.wsUrl)
		} catch (error) {
			logger.error('mail-realtime: WebSocket creation failed', { error })
			this.scheduleReconnect()
			return
		}

		this.ws.onopen = () => {
			logger.debug('mail-realtime: connected, authenticating')
			this.reconnectAttempts = 0
			this.ws.send(JSON.stringify({ type: 'auth', token: this.token }))
		}

		this.ws.onmessage = (event) => {
			this.handleMessage(event.data)
		}

		this.ws.onclose = () => {
			logger.debug('mail-realtime: connection closed')
			this.ws = null
			this.scheduleReconnect()
		}

		this.ws.onerror = () => {
			logger.debug('mail-realtime: connection error')
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
			logger.debug('mail-realtime: authenticated')
			return
		}

		if (msg.type === 'sync-done') {
			logger.debug('mail-realtime: sync done', msg)
			if (!window.OCA?.YooMailRealtime?.applySyncDone?.(msg)) {
				this.onMailboxChanged(msg)
			}
			return
		}

		if (msg.type === 'mailbox-changed') {
			if (msg.sync === 'started') {
				logger.debug('mail-realtime: mailbox changed, waiting for sync-done', msg)
				return
			}
			logger.debug('mail-realtime: mailbox changed', msg)
			this.onMailboxChanged(msg)
		}
	}

	onMailboxChanged({ mailboxId, accountId }) {
		// The push event carries accountId (+ optionally mailboxId in the
		// future). Refresh the current mailbox list(s) for this account.
		const store = window.OCA?.YooMailRealtime?.getMainStore?.()
		if (!store) {
			logger.warn('mail-realtime: main store not available')
			return
		}
		try {
			store.syncEnvelopes({
				mailboxId: mailboxId ?? store.currentMailboxId ?? undefined,
				accountId,
			})
		} catch (error) {
			logger.error('mail-realtime: failed to refresh mailbox', { error })
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
		logger.debug(`mail-realtime: reconnecting in ${delay}ms`)
		this.reconnectTimer = setTimeout(() => {
			this.reconnectTimer = null
			this.connect()
		}, delay)
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
		client.connect()
	}
})

export default RealtimeClient
