/**
 * SPDX-FileCopyrightText: 2026 TigerSprite Team
 * SPDX-License-Identifier: AGPL-3.0-only
 */

import { loadState } from '@nextcloud/initial-state'
import moment from 'moment-timezone'

/**
 * The user's configured timezone from Nextcloud preferences
 * (falls back to the browser timezone when unset).
 */
export function getUserTimezone() {
	const configured = loadState('yoomail', 'timezone', '')
	if (configured) {
		return configured
	}
	return Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC'
}

/**
 * Whether times should be shown in 24h format.
 *
 * Configurable via the app preference `time-format` ('24' or '12').
 * Defaults to 24h. A future admin/user settings page can write this
 * preference to switch between 24h and 12h (AM/PM) display.
 */
export function is24Hour() {
	return loadState('yoomail', 'time-format', '24') !== '12'
}

/**
 * The moment format string for displaying just the time of day.
 * 24h → HH:mm, 12h → h:mm A (AM/PM).
 */
export function timeOfDayFormat() {
	return is24Hour() ? 'HH:mm' : 'h:mm A'
}

/**
 * Format a timestamp (epoch seconds) in the user's timezone.
 *
 * @param {number} epochSeconds
 * @param {string} format
 * @return {string}
 */
export function formatInUserTimezone(epochSeconds, format = 'LLL') {
	return moment(epochSeconds * 1000).tz(getUserTimezone()).format(format)
}

/**
 * Format a timestamp (epoch seconds) showing just the time of day,
 * respecting the 24h/12h preference.
 *
 * @param {number} epochSeconds
 * @return {string}
 */
export function formatTimeOfDay(epochSeconds) {
	return moment(epochSeconds * 1000).tz(getUserTimezone()).format(timeOfDayFormat())
}
