(function() {
	'use strict'

	function init() {
		const appId = 'yoomail'
		const root = document.getElementById('yoomail-personal-notification-settings')
		const dataNode = document.getElementById('yoomail-personal-notification-settings-data')

		if (!root || !dataNode) {
			return
		}

		const requestToken = OC.requestToken || document.head.getAttribute('data-requesttoken') || ''
		let notificationData = {}
		try {
			notificationData = JSON.parse(dataNode.textContent || '{}')
		} catch (error) {
			console.error('Failed to parse YooMail notification settings JSON', error)
			OC.Notification.showTemporary(t(appId, 'Could not load YooMail notification settings'))
			return
		}
		const audioUrls = notificationData.audioUrls || {}

		function apiUrl(path) {
			return OC.generateUrl(`/apps/${appId}${path}`)
		}

		function getPermissionText() {
			if (!('Notification' in window)) {
				return t(appId, 'This browser does not support native notifications.')
			}

			switch (Notification.permission) {
			case 'granted':
				return t(appId, 'Browser notification permission: granted')
			case 'denied':
				return t(appId, 'Browser notification permission: denied')
			default:
				return t(appId, 'Browser notification permission: not requested')
			}
		}

		function updatePermissionStatus() {
			const node = root.querySelector('#ym-browser-permission-status')
			if (node) {
				node.textContent = getPermissionText()
			}

			const button = root.querySelector('#ym-request-notification-permission')
			if (!button) {
				return
			}

			if (!('Notification' in window)) {
				button.disabled = true
				button.textContent = t(appId, 'Browser notifications unavailable')
				return
			}

			switch (Notification.permission) {
			case 'granted':
				button.disabled = true
				button.textContent = t(appId, 'Browser permission granted')
				break
			case 'denied':
				button.disabled = false
				button.textContent = t(appId, 'Open browser notification settings')
				break
			default:
				button.disabled = false
				button.textContent = t(appId, 'Request browser permission')
			}
		}

			async function saveSettings() {
				const checkedValue = function(selector) {
					return root.querySelector(selector).checked ? 'yes' : 'no'
				}
				const payload = {
					nativeNewMail: checkedValue('#ym-native-new-mail'),
					soundEnabled: checkedValue('#ym-sound-enabled'),
					toastEnabled: checkedValue('#ym-toast-enabled'),
					topAppIconEnabled: checkedValue('#ym-top-app-icon-enabled'),
					soundNewMail: checkedValue('#ym-sound-new-mail'),
					soundSendSuccess: checkedValue('#ym-sound-send-success'),
					soundSendFail: checkedValue('#ym-sound-send-fail'),
				}

			const response = await fetch(apiUrl('/api/settings/notifications'), {
				method: 'PUT',
				headers: {
					'Accept': 'application/json',
					'Content-Type': 'application/json',
					'X-Requested-With': 'XMLHttpRequest',
					'requesttoken': requestToken,
				},
				credentials: 'same-origin',
				body: JSON.stringify(payload),
			})

				if (!response.ok) {
					throw new Error(t(appId, 'Could not save YooMail notification settings'))
				}

				const savedSettings = await response.json()
				try {
					localStorage.setItem('yoomail-notification-settings-updated', JSON.stringify({
						time: Date.now(),
						settings: savedSettings,
					}))
				} catch (error) {
				}

				OC.Notification.showTemporary(t(appId, 'YooMail notification settings saved'))
			}

			async function requestPermission() {
				if (!('Notification' in window)) {
					OC.Notification.showTemporary(t(appId, 'This browser does not support native notifications.'))
					return
				}

			if (Notification.permission === 'granted') {
				updatePermissionStatus()
				OC.Notification.showTemporary(t(appId, 'Browser notifications are already allowed'))
				return
			}

			if (Notification.permission === 'denied') {
				updatePermissionStatus()
				OC.Notification.showTemporary(t(appId, 'Browser notifications were blocked. Please enable notifications for this site in your browser settings.'))
				return
			}

			try {
				const permission = await Notification.requestPermission()
				updatePermissionStatus()
				if (permission === 'granted') {
					OC.Notification.showTemporary(t(appId, 'Browser notifications are now allowed'))
					return
				}
				if (permission === 'denied') {
					OC.Notification.showTemporary(t(appId, 'Browser notifications were denied. Please enable notifications for this site in your browser settings if you want to use them.'))
					return
				}
				OC.Notification.showTemporary(t(appId, 'Browser notification permission was not changed'))
			} catch (error) {
				OC.Notification.showTemporary(t(appId, 'Failed to request browser notification permission'))
				}
			}

			function getSoundInputs() {
				return {
					parent: root.querySelector('#ym-sound-enabled'),
					children: [
						root.querySelector('#ym-sound-new-mail'),
						root.querySelector('#ym-sound-send-success'),
						root.querySelector('#ym-sound-send-fail'),
					].filter(Boolean),
				}
			}

			function updateSoundParentFromChildren() {
				const inputs = getSoundInputs()
				if (!inputs.parent) {
					return
				}

				inputs.parent.checked = inputs.children.some(function(input) {
					return input.checked
				})
			}

			function updateSoundChildrenFromParent() {
				const inputs = getSoundInputs()
				if (!inputs.parent) {
					return
				}

				inputs.children.forEach(function(input) {
					input.checked = inputs.parent.checked
				})
			}

			function normalizeInitialSoundState() {
				const inputs = getSoundInputs()
				if (!inputs.parent) {
					return
				}

				if (inputs.parent.checked && inputs.children.every(function(input) { return !input.checked })) {
					inputs.children.forEach(function(input) {
						input.checked = true
					})
				}

				updateSoundParentFromChildren()
			}

			async function testSound(kind) {
				const url = audioUrls[kind]
				if (!url) {
					return
				}

			try {
				const audio = new Audio(url)
				audio.preload = 'auto'
				audio.volume = 1
				await audio.play()
			} catch (error) {
				OC.Notification.showTemporary(t(appId, 'Sound playback was blocked by the browser'))
			}
		}

			root.querySelector('#ym-save-notification-settings')?.addEventListener('click', function() {
				saveSettings().catch(function(error) {
					OC.Notification.showTemporary(error.message || t(appId, 'Could not save YooMail notification settings'))
				})
			})
			root.querySelector('#ym-request-notification-permission')?.addEventListener('click', requestPermission)
			root.querySelector('#ym-sound-enabled')?.addEventListener('change', updateSoundChildrenFromParent)
			getSoundInputs().children.forEach(function(input) {
				input.addEventListener('change', updateSoundParentFromChildren)
			})
			root.querySelector('#ym-test-new-mail-sound')?.addEventListener('click', function() { testSound('newMail') })
			root.querySelector('#ym-test-send-success-sound')?.addEventListener('click', function() { testSound('sendSuccess') })
			root.querySelector('#ym-test-send-fail-sound')?.addEventListener('click', function() { testSound('sendFail') })

			normalizeInitialSoundState()
			updatePermissionStatus()
		}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init, { once: true })
	} else {
		init()
	}
})()
