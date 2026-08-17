/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { getRequestToken } from '@nextcloud/auth'
import { registerDavProperty } from '@nextcloud/files'
import { generateFilePath } from '@nextcloud/router'
import { loadState } from '@nextcloud/initial-state'
import moment from 'moment-timezone'
import ncMoment from '@nextcloud/moment'
import { createPinia, PiniaVuePlugin, setActivePinia } from 'pinia'
import vToolTip from 'v-tooltip'
import Vue from 'vue'
import VueShortKey from 'vue-shortkey'
import App from './App.vue'
import Nextcloud from './mixins/Nextcloud.js'
import router from './router.js'
import useMainStore from './store/mainStore.js'
import './realtime.js'

import '@nextcloud/dialogs/style.css'
import './directives/drag-and-drop/styles/drag-and-drop.scss'

// Display mail timestamps in the user's configured timezone instead of the
// browser's local timezone (which may differ, e.g. on remote/servers).
// moment-timezone shares the global moment singleton, so setting the default
// here affects @nextcloud/moment used across the app.
moment.tz.setDefault(loadState('yoomail', 'timezone', 'UTC'))

// Apply the configured 12/24h time format to the moment instance used for
// mail timestamp display (@nextcloud/moment). This makes every
// moment().format('LT'|'LLL'|'lll') call across the app (envelope list,
// message details, ...) honor the admin/user `time-format` preference
// instead of the browser locale default.
const applyTimeFormat = () => {
	const is24h = loadState('yoomail', 'time-format', '24') !== '12'
	const time = is24h ? 'HH:mm' : 'h:mm A'
	const locale = ncMoment.locale()
	ncMoment.updateLocale(locale, {
		longDateFormat: {
			LT: time,
			LLL: `MMM D, YYYY ${time}`,
			lll: `MMM D, YYYY ${time}`,
			LTS: is24h ? 'HH:mm:ss' : 'h:mm:ss A',
		},
	})
}
applyTimeFormat()

__webpack_nonce__ = btoa(getRequestToken())

__webpack_public_path__ = generateFilePath('yoomail', '', 'js/')

Vue.use(PiniaVuePlugin)
const pinia = createPinia()
setActivePinia(pinia)

Vue.mixin(Nextcloud)

Vue.use(VueShortKey, { prevent: ['input', 'div', 'textarea'] })
Vue.use(vToolTip)

registerDavProperty('nc:share-attributes', { nc: 'http://nextcloud.org/ns' })

// Expose a minimal bridge for the realtime WebSocket client (realtime.js)
window.OCA = window.OCA || {}
window.OCA.YooMailRealtime = {
	// Keep a reference so realtime.js can refresh the mailbox list on push
	getMainStore: () => useMainStore(pinia),
}

export default new Vue({
	el: '#content',
	name: 'Mail',
	router,
	pinia,
	render: (h) => h(App),
})
