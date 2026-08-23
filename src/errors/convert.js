/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import CouldNotConnectError from './CouldNotConnectError.js'
import MailboxLockedError from './MailboxLockedError.js'
import MailboxNotCachedError from './MailboxNotCachedError.js'
import ManageSieveError from './ManageSieveError.js'
import ManyRecipientsError from './ManyRecipientsError.js'
import NoDraftsMailboxConfiguredError from './NoDraftsMailboxConfiguredError.js'
import NoSentMailboxConfiguredError from './NoSentMailboxConfiguredError.js'
import NoTrashMailboxConfiguredError from './NoTrashMailboxConfiguredError.js'

const map = {
	'OCA\\YooMail\\Exception\\DraftsMailboxNotSetException': NoDraftsMailboxConfiguredError,
	'OCA\\YooMail\\Exception\\MailboxLockedException': MailboxLockedError,
	'OCA\\YooMail\\Exception\\MailboxNotCachedException': MailboxNotCachedError,
	'OCA\\YooMail\\Exception\\SentMailboxNotSetException': NoSentMailboxConfiguredError,
	'OCA\\YooMail\\Exception\\TrashMailboxNotSetException': NoTrashMailboxConfiguredError,
	'OCA\\YooMail\\Exception\\CouldNotConnectException': CouldNotConnectError,
	'OCA\\YooMail\\Exception\\ManyRecipientsException': ManyRecipientsError,
	'Horde\\ManageSieve\\Exception': ManageSieveError,
}

/**
 * @param {object} axiosError the axios Error
 * @return {Error}
 */
export function convertAxiosError(axiosError) {
	if (!('response' in axiosError)) {
		// No conversion
		return axiosError
	}

	const response = axiosError.response
	if (response.status === 428) {
		return new MailboxNotCachedError(response.data?.data?.message || 'Mailbox is not cached yet')
	}

	if (response.data?.data?.type === 'OCA\\YooMail\\Exception\\MailboxNotCachedException') {
		return new MailboxNotCachedError(response.data.data.message)
	}

	if (!('x-mail-response' in axiosError.response.headers)) {
		// Not a structured response
		return axiosError
	}

	const responseType = response.data?.data?.type
	if (!(responseType in map)) {
		// No conversion possible
		return axiosError
	}

	return new map[responseType](response.data.data.message)
}
