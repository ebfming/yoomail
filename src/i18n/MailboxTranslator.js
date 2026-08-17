/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { translate as t } from '@nextcloud/l10n'
import logger from '../logger.js'

function translateSpecial(mailbox) {
	if (mailbox.specialUse.includes('all')) {
		// TRANSLATORS: translated mail box name
		return t('yoomail', 'All')
	}
	if (mailbox.specialUse.includes('archive')) {
		// TRANSLATORS: translated mail box name
		return t('yoomail', 'Archive')
	}
	if (mailbox.specialUse.includes('drafts')) {
		// TRANSLATORS: translated mail box name
		return t('yoomail', 'Drafts')
	}
	if (mailbox.specialUse.includes('flagged')) {
		// TRANSLATORS: translated mail box name
		return t('yoomail', 'Favorites')
	}
	if (mailbox.specialUse.includes('inbox')) {
		if (mailbox.isPriorityInbox) {
			// TRANSLATORS: translated mail box name
			return t('yoomail', 'Priority inbox')
		} else if (mailbox.isUnified) {
			// TRANSLATORS: translated mail box name
			return t('yoomail', 'All inboxes')
		} else {
			// TRANSLATORS: translated mail box name
			return t('yoomail', 'Inbox')
		}
	}
	if (mailbox.specialUse.includes('junk')) {
		// TRANSLATORS: translated mail box name
		return t('yoomail', 'Junk')
	}
	if (mailbox.specialUse.includes('sent')) {
		// TRANSLATORS: translated mail box name
		return t('yoomail', 'Sent')
	}
	if (mailbox.specialUse.includes('trash')) {
		// TRANSLATORS: translated mail box name
		return t('yoomail', 'Trash')
	}
	throw new Error(`unknown special use ${mailbox.specialUse}`)
}

export function translate(mailbox) {
	if (mailbox.specialUse.length > 0) {
		try {
			return translateSpecial(mailbox)
		} catch (e) {
			logger.error('could not translate special mailbox', { error: e })
		}
	}
	return mailbox.displayName
}
