/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { loadState } from '@nextcloud/initial-state'
import logger from './logger.js'
import { fixAccountId } from './service/AccountService.js'
import { fetchAvailableLanguages } from './service/translationService.js'
import useMainStore from './store/mainStore.js'
import useOutboxStore from './store/outboxStore.js'

export default function initAfterAppCreation() {
	logger.debug('Init after app creation')
	const mainStore = useMainStore()

	const preferences = loadState('yoomail', 'preferences', [])

	mainStore.savePreferenceMutation({
		key: 'debug',
		value: loadState('yoomail', 'debug', false),
	})
	mainStore.savePreferenceMutation({
		key: 'ncVersion',
		value: loadState('yoomail', 'ncVersion'),
	})
	mainStore.savePreferenceMutation({
		key: 'mailVersion',
		value: preferences['app-version'] || loadState('yoomail', 'mailVersion'),
	})
	mainStore.savePreferenceMutation({
		key: 'internalVersion',
		value: loadState('yoomail', 'internalVersion', ''),
	})

	mainStore.savePreferenceMutation({
		key: 'timezone',
		value: loadState('yoomail', 'timezone', 'UTC'),
	})

	mainStore.savePreferenceMutation({
		key: 'time-format',
		value: loadState('yoomail', 'time-format', '24'),
	})

	mainStore.savePreferenceMutation({
		key: 'sort-order',
		value: loadState('yoomail', 'sort-order', 'newest'),
	})

	mainStore.savePreferenceMutation({
		key: 'attachment-size-limit',
		value: Number.parseInt(preferences['attachment-size-limit'], 10),
	})
	mainStore.savePreferenceMutation({
		key: 'version',
		value: preferences['config-installed-version'],
	})
	mainStore.savePreferenceMutation({
		key: 'external-avatars',
		value: preferences['external-avatars'],
	})
	mainStore.savePreferenceMutation({
		key: 'collect-data',
		value: preferences['collect-data'],
	})
	mainStore.savePreferenceMutation({
		key: 'search-priority-body',
		value: preferences['search-priority-body'],
	})
	mainStore.savePreferenceMutation({
		key: 'sort-favorites',
		value: preferences['sort-favorites'],
	})
	const startMailboxId = preferences['start-mailbox-id']
	mainStore.savePreferenceMutation({
		key: 'start-mailbox-id',
		value: startMailboxId ? parseInt(startMailboxId, 10) : null,
	})
	mainStore.savePreferenceMutation({
		key: 'index-context-chat',
		value: preferences['index-context-chat'],
	})
	mainStore.savePreferenceMutation({
		key: 'allow-new-accounts',
		value: loadState('yoomail', 'allow-new-accounts', true),
	})
	mainStore.savePreferenceMutation({
		key: 'password-is-unavailable',
		value: loadState('yoomail', 'password-is-unavailable', false),
	})
	mainStore.savePreferenceMutation({
		key: 'layout-mode',
		value: preferences['layout-mode'],
	})
	mainStore.savePreferenceMutation({
		key: 'layout-message-view',
		value: preferences['layout-message-view'],
	})
	mainStore.savePreferenceMutation({
		key: 'follow-up-reminders',
		value: preferences['follow-up-reminders'],
	})
	mainStore.savePreferenceMutation({
		key: 'internal-addresses',
		value: loadState('yoomail', 'internal-addresses', false),
	})
	mainStore.savePreferenceMutation({
		key: 'smime-sign-aliases',
		value: loadState('yoomail', 'smime-sign-aliases', []),
	})
	mainStore.savePreferenceMutation({
		key: 'compact-mode',
		value: preferences['compact-mode'],
	})

	mainStore.setQuickActions(loadState('yoomail', 'quick-actions', []))

	const accountSettings = loadState('yoomail', 'account-settings')
	const accounts = loadState('yoomail', 'accounts', [])
	const internalAddressesList = loadState('yoomail', 'internal-addresses-list', [])
	const tags = loadState('yoomail', 'tags', [])
	const outboxMessages = loadState('yoomail', 'outbox-messages')
	const disableScheduledSend = loadState('yoomail', 'disable-scheduled-send')
	const disableSnooze = loadState('yoomail', 'disable-snooze')
	const googleOauthUrl = loadState('yoomail', 'google-oauth-url', null)
	const microsoftOauthUrl = loadState('yoomail', 'microsoft-oauth-url', null)
	const followUpFeatureAvailable = loadState('yoomail', 'llm_followup_available', false)
	const contextChatFeatureAvailable = loadState('yoomail', 'context_chat_available', false)

	accounts.map(fixAccountId).forEach((account) => {
		const settings = accountSettings.find((settings) => settings.accountId === account.id)
		if (settings) {
			delete settings.accountId
			Object.entries(settings).forEach(([key, value]) => {
				mainStore.setAccountSettingMutation({
					accountId: account.id,
					key,
					value,
				})
			})
		}
		mainStore.addAccountMutation({ ...account, ...settings })
	})

	tags.forEach((tag) => mainStore.addTagMutation({ tag }))
	internalAddressesList.forEach((internalAddress) => mainStore.addInternalAddressMutation(internalAddress))

	mainStore.setScheduledSendingDisabledMutation(disableScheduledSend)
	mainStore.setSnoozeDisabledMutation(disableSnooze)
	mainStore.setGoogleOauthUrlMutation(googleOauthUrl)
	mainStore.setMicrosoftOauthUrlMutation(microsoftOauthUrl)
	mainStore.setFollowUpFeatureAvailableMutation(followUpFeatureAvailable)
	mainStore.setContextChatFeatureAvailableMutation(contextChatFeatureAvailable)

	const smimeCertificates = loadState('yoomail', 'smime-certificates', [])
	mainStore.setSmimeCertificatesMutation(smimeCertificates)

	const outboxStore = useOutboxStore()
	outboxMessages.forEach((message) => outboxStore.addMessageMutation({ message }))

	const llmTranslationEnabled = loadState('yoomail', 'llm_translation_enabled', false)
	mainStore.isTranslationEnabled = llmTranslationEnabled
	if (llmTranslationEnabled) {
		fetchAvailableLanguages().then(() => {})
	}
}
