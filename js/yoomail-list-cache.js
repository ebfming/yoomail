(function() {
	'use strict'

	var CACHE_PREFIX = 'yoomail:list-cache:v1:'
	var CACHE_TTL_MS = 15 * 60 * 1000
	var ROUTE_POLL_MS = 1000

	function isMailboxRoute() {
		return /\/apps\/yoomail\/(?:box\/|$)/.test(window.location.pathname)
	}

	function getUserId() {
		return window.OC?.currentUser || window.OC?.getCurrentUser?.()?.uid || 'anonymous'
	}

	function normalizeQuery(query) {
		return query == null ? '' : String(query)
	}

	function getPreference(store, key, fallback) {
		try {
			var value = store.getPreference(key)
			return value == null ? fallback : value
		} catch (error) {
			return fallback
		}
	}

	function buildCacheKey(store, mailboxId, query) {
		return [
			CACHE_PREFIX,
			getUserId(),
			mailboxId,
			normalizeQuery(query),
			getPreference(store, 'sort-order', 'newest'),
			getPreference(store, 'layout-message-view', 'threaded'),
		].join(':')
	}

	function readCache(store, mailboxId, query) {
		try {
			var raw = window.localStorage.getItem(buildCacheKey(store, mailboxId, query))
			if (!raw) {
				return null
			}

			var parsed = JSON.parse(raw)
			if (!parsed || !Array.isArray(parsed.envelopes) || typeof parsed.savedAt !== 'number') {
				return null
			}

			if ((Date.now() - parsed.savedAt) > CACHE_TTL_MS) {
				window.localStorage.removeItem(buildCacheKey(store, mailboxId, query))
				return null
			}

			return parsed.envelopes
		} catch (error) {
			return null
		}
	}

	function writeCache(store, mailboxId, query) {
		try {
			var envelopes = store.getEnvelopes(mailboxId, query)
			if (!Array.isArray(envelopes)) {
				return
			}

			var mailbox = store.getMailbox(mailboxId)
			if (!mailbox || mailbox.isUnified) {
				return
			}

			if (envelopes.length === 0) {
				window.localStorage.removeItem(buildCacheKey(store, mailboxId, query))
				return
			}

			window.localStorage.setItem(buildCacheKey(store, mailboxId, query), JSON.stringify({
				savedAt: Date.now(),
				envelopes: envelopes.slice(0, 100),
			}))
		} catch (error) {
		}
	}

	function clearMailboxCache(store, mailboxId) {
		try {
			for (var i = window.localStorage.length - 1; i >= 0; i--) {
				var key = window.localStorage.key(i)
				if (!key) {
					continue
				}

				var parts = key.split(':')
				if (parts[0] === 'yoomail'
					&& parts[1] === 'list-cache'
					&& parts[2] === 'v1'
					&& parts[3] === getUserId()
					&& Number(parts[4]) === Number(mailboxId)) {
					window.localStorage.removeItem(key)
				}
			}
		} catch (error) {
		}
	}

	function getCurrentMailboxRoute() {
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
			query: normalizeQuery(query),
		}
	}

	function hydrateMailbox(store, mailboxId, query) {
		try {
			var mailbox = store.getMailbox(mailboxId)
			if (!mailbox || mailbox.isUnified) {
				return
			}

			var existing = store.getEnvelopes(mailboxId, query)
			if (Array.isArray(existing) && existing.length > 0) {
				return
			}

			var cached = readCache(store, mailboxId, query)
			if (!cached || cached.length === 0) {
				return
			}

			store.addEnvelopesMutation({
				query: query,
				envelopes: cached,
				addToUnifiedMailboxes: true,
			})
		} catch (error) {
		}
	}

	function patchMethod(store, methodName, wrapper) {
		if (typeof store[methodName] !== 'function') {
			return
		}
		var original = store[methodName].bind(store)
		store[methodName] = wrapper(original)
	}

	function patchStore(store) {
		if (!store || store.__yoomailListCachePatched) {
			return
		}
		store.__yoomailListCachePatched = true

		patchMethod(store, 'fetchEnvelopes', function(original) {
			return async function(payload) {
				var mailboxId = payload?.mailboxId
				var query = normalizeQuery(payload?.query)
				if (mailboxId != null) {
					hydrateMailbox(store, mailboxId, query)
				}
				var result = await original(payload)
				if (mailboxId != null) {
					writeCache(store, mailboxId, query)
				}
				return result
			}
		})

		patchMethod(store, 'fetchNextEnvelopes', function(original) {
			return async function(payload) {
				var result = await original(payload)
				if (payload?.mailboxId != null) {
					writeCache(store, payload.mailboxId, normalizeQuery(payload?.query))
				}
				return result
			}
		})

		patchMethod(store, 'syncEnvelopes', function(original) {
			return async function(payload) {
				var result = await original(payload)
				if (payload?.mailboxId != null) {
					writeCache(store, payload.mailboxId, normalizeQuery(payload?.query))
				}
				return result
			}
		})

		patchMethod(store, 'clearMailbox', function(original) {
			return async function(payload) {
				var result = await original(payload)
				if (payload?.mailbox?.databaseId != null) {
					clearMailboxCache(store, payload.mailbox.databaseId)
				}
				return result
			}
		})

		patchMethod(store, 'deleteMessage', function(original) {
			return async function(payload) {
				var envelope = payload?.id != null ? store.getEnvelope(payload.id) : null
				var result = await original(payload)
				if (envelope?.mailboxId != null) {
					writeCache(store, envelope.mailboxId, '')
				}
				return result
			}
		})

		patchMethod(store, 'moveMessage', function(original) {
			return async function(payload) {
				var envelope = payload?.id != null ? store.getEnvelope(payload.id) : null
				var result = await original(payload)
				if (envelope?.mailboxId != null) {
					writeCache(store, envelope.mailboxId, '')
				}
				return result
			}
		})

		var lastRouteKey = null
		window.setInterval(function() {
			var route = getCurrentMailboxRoute()
			if (!route) {
				return
			}

			var routeKey = [route.mailboxId, route.query].join(':')
			if (routeKey === lastRouteKey) {
				return
			}
			lastRouteKey = routeKey

			hydrateMailbox(store, route.mailboxId, route.query)
		}, ROUTE_POLL_MS)
	}

	function boot() {
		if (!isMailboxRoute()) {
			return
		}

		var store = window.OCA?.YooMailRealtime?.getMainStore?.()
		if (!store) {
			window.setTimeout(boot, 250)
			return
		}

		patchStore(store)
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot)
	} else {
		boot()
	}
})()
