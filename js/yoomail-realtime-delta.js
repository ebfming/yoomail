(function() {
	'use strict'

	function isMailboxRoute() {
		return /\/apps\/yoomail\/(?:box\/|$)/.test(window.location.pathname)
	}

	function getStore() {
		return window.OCA?.YooMailRealtime?.getMainStore?.() || null
	}

	function patchFetchThreadRecovery(store) {
		if (!store || store.__yoomailFetchThreadRecoveryPatched || typeof store.fetchThread !== 'function') {
			return
		}

		var originalFetchThread = store.fetchThread.bind(store)

		store.fetchThread = async function(id) {
			try {
				return await originalFetchThread(id)
			} catch (error) {
				var status = error?.response?.status
				var envelope = store.getEnvelope?.(id)
				var mailboxId = Number(envelope?.mailboxId)
				if (status !== 403 || !Number.isFinite(mailboxId) || typeof store.syncEnvelopes !== 'function') {
					throw error
				}

				try {
					await store.syncEnvelopes({
						mailboxId: mailboxId,
						query: '',
						init: false,
					})
				} catch (syncError) {
					throw error
				}

				return await originalFetchThread(id)
			}
		}

		store.__yoomailFetchThreadRecoveryPatched = true
	}

	function getCurrentRouteInfo() {
		var parts = window.location.pathname.split('/').filter(Boolean)
		var boxIndex = parts.lastIndexOf('box')
		if (boxIndex === -1 || boxIndex + 1 >= parts.length) {
			return null
		}

		var mailboxIndex = boxIndex + 2
		var query = ''
		if (boxIndex + 2 >= parts.length || parts[boxIndex + 2] === 'thread') {
			mailboxIndex = boxIndex + 1
		} else {
			query = decodeURIComponent(parts[boxIndex + 1] || '')
		}

		var mailboxId = Number(parts[mailboxIndex])
		if (!Number.isFinite(mailboxId)) {
			return null
		}

		return {
			mailboxId: mailboxId,
			query: query,
			threadView: parts.indexOf('thread') !== -1,
		}
	}

	function applyEnvelopeDelta(store, mailbox, delta) {
		var newMessages = normalizeEnvelopes(delta?.newMessages, mailbox)
		var changedMessages = normalizeEnvelopes(delta?.changedMessages, mailbox)
		var vanishedMessages = Array.isArray(delta?.vanishedMessages) ? delta.vanishedMessages : []

		if (newMessages.length > 0) {
			store.addEnvelopesMutation({
				envelopes: newMessages,
			})
		}

		var unifiedMailbox = mailbox?.specialRole ? store.getUnifiedMailbox?.(mailbox.specialRole) : null
		newMessages.forEach(function(envelope) {
			if (unifiedMailbox) {
				store.updateEnvelopeMutation({ envelope: envelope })
			}
		})
		changedMessages.forEach(function(envelope) {
			store.updateEnvelopeMutation({ envelope: envelope })
		})
		vanishedMessages.forEach(function(id) {
			store.removeEnvelopeMutation({ id: id })
		})
	}

	function normalizeEnvelopes(envelopes, mailbox) {
		if (!Array.isArray(envelopes) || envelopes.length === 0) {
			return []
		}

		return envelopes.map(function(envelope) {
			return Object.assign({}, envelope, {
				accountId: envelope?.accountId ?? mailbox?.accountId,
				mailboxId: envelope?.mailboxId ?? mailbox?.databaseId,
			})
		})
	}

	function applyMailboxStats(store, mailboxId, delta) {
		if (delta?.stats?.unread == null) {
			return
		}

		store.setMailboxUnreadCountMutation({
			id: mailboxId,
			unread: delta.stats.unread,
		})
	}

	window.OCA = window.OCA || {}
	window.OCA.YooMailRealtime = window.OCA.YooMailRealtime || {}
	window.OCA.YooMailRealtime.applySyncDone = function(payload) {
		if (!isMailboxRoute()) {
			return false
		}

		var store = getStore()
		if (!store) {
			return false
		}

		patchFetchThreadRecovery(store)

		var delta = payload?.realtimePayload
		var mailboxId = Number(payload?.mailboxId)
		if (!delta || !Number.isFinite(mailboxId)) {
			return false
		}

		var mailbox = store.getMailbox?.(mailboxId)
		if (!mailbox) {
			return false
		}

		applyMailboxStats(store, mailboxId, delta)
		applyEnvelopeDelta(store, mailbox, delta)

		var route = getCurrentRouteInfo()
		if (!route) {
			return true
		}

		if (route.mailboxId !== mailboxId) {
			return true
		}

		if (route.threadView || route.query) {
			return false
		}

		return true
	}
})()
