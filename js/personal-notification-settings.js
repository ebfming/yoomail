(function() {
	'use strict'

	function init() {
		const appId = 'yoomail'
		const root = document.getElementById('yoomail-personal-notification-settings')
		const dataNode = document.getElementById('yoomail-personal-notification-settings-data')

		if (!root || !dataNode) {
			return
		}

		const requestToken = document.head.getAttribute('data-requesttoken') || ''
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
		}

		async function saveSettings() {
			const payload = {
				nativeNewMail: root.querySelector('#ym-native-new-mail').checked,
				soundEnabled: root.querySelector('#ym-sound-enabled').checked,
				toastEnabled: root.querySelector('#ym-toast-enabled').checked,
				soundNewMail: root.querySelector('#ym-sound-new-mail').checked,
				soundSendSuccess: root.querySelector('#ym-sound-send-success').checked,
				soundSendFail: root.querySelector('#ym-sound-send-fail').checked,
			}

			const response = await fetch(apiUrl('/api/settings/notifications'), {
				method: 'PUT',
				headers: {
					'Accept': 'application/json',
					'Content-Type': 'application/json',
					'requesttoken': requestToken,
				},
				credentials: 'same-origin',
				body: JSON.stringify(payload),
			})

			if (!response.ok) {
				throw new Error(t(appId, 'Could not save YooMail notification settings'))
			}

			OC.Notification.showTemporary(t(appId, 'YooMail notification settings saved'))
		}

		async function requestPermission() {
			if (!('Notification' in window)) {
				OC.Notification.showTemporary(t(appId, 'This browser does not support native notifications.'))
				return
			}

			try {
				await Notification.requestPermission()
				updatePermissionStatus()
			} catch (error) {
				OC.Notification.showTemporary(t(appId, 'Failed to request browser notification permission'))
			}
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
		root.querySelector('#ym-test-new-mail-sound')?.addEventListener('click', function() { testSound('newMail') })
		root.querySelector('#ym-test-send-success-sound')?.addEventListener('click', function() { testSound('sendSuccess') })
		root.querySelector('#ym-test-send-fail-sound')?.addEventListener('click', function() { testSound('sendFail') })

		updatePermissionStatus()
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init, { once: true })
	} else {
		init()
	}
})()
