/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import Axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export function normalizeAttachmentUrl(url) {
	if (typeof url !== 'string') {
		return url
	}

	return url.replace('/apps/mail/api/messages/', '/apps/yoomail/api/messages/')
}

export async function saveAttachmentToFiles(id, attachmentId, directory) {
	const url = generateUrl(
		'/apps/yoomail/api/messages/{id}/attachment/{attachmentId}',
		{
			id,
			attachmentId,
		},
	)

	return await Axios.post(url, {
		targetPath: directory,
	})
}

export async function saveAttachmentsToFiles(id, directory) {
	// attachmentId = 0 means 'all attachments' (see MessageController.php::saveAttachement)
	return await saveAttachmentToFiles(id, 0, directory)
}

export function downloadAttachment(url) {
	return Axios.get(normalizeAttachmentUrl(url)).then((res) => res.data)
}

export function uploadLocalAttachment(file, accountId, progress, controller) {
	const url = generateUrl('/apps/yoomail/api/attachments')
	const data = new FormData()
	const opts = {
		onUploadProgress: (prog) => progress(prog, prog.loaded, prog.total),
	}
	if (controller) {
		opts.signal = controller.signal
	}
	data.append('attachment', file)

	if (accountId) {
		data.append('accountId', accountId)
	}

	return Axios.post(url, data, opts)
		.then((resp) => resp.data)
		.then(({ id }) => {
			return {
				file,
				id,
			}
		})
}
