<!--
  - SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="app-settings">
		<NcAppSettingsDialog
			id="app-settings-dialog"
			:name="t('yoomail', 'Mail settings')"
			:show-navigation="true"
			:additional-trap-elements="trapElements"
			:legacy="false"
			no-version
			:open.sync="showSettings">
			<NcAppSettingsSection id="general" :name="t('yoomail', 'General')">
				<NcButton
					variant="secondary"
					:aria-label="t('yoomail', 'Set as default mail app')"
					wide
					@click="registerProtocolHandler">
					{{ t('yoomail', 'Set as default mail app') }}
				</NcButton>

				<NcFormGroup :label="t('yoomail', 'Account settings')">
					<NcFormBox>
						<NcFormBoxButton
							v-for="account in accountsWithEmail"
							:key="account.id"
							:aria-label="t('yoomail', 'Account settings')"
							@click="openAccountSettings(account.id)">
							<template #icon>
								<IconArrow :size="20" />
							</template>
							{{ account.emailAddress }}
						</NcFormBoxButton>
						<NcFormBoxButton
							v-for="account in delegatedAccounts"
							:key="account.id"
							:aria-label="t('yoomail', 'Account settings')"
							@click="openAccountSettings(account.id)">
							<template #icon>
								<IconArrow :size="20" />
							</template>
							{{ t('yoomail', '{email} (delegated)', { email: account.emailAddress }) }}
						</NcFormBoxButton>
						<NcButton
							v-if="allowNewMailAccounts"
							variant="secondary"
							to="/setup"
							:aria-label="t('yoomail', 'Add mail account')"
							wide>
							<template #icon>
								<IconAdd :size="20" />
							</template>
							{{ t('yoomail', 'Add mail account') }}
						</NcButton>
					</NcFormBox>
				</NcFormGroup>
			</NcAppSettingsSection>

			<NcAppSettingsSection id="appearance" :name="t('yoomail', 'Appearance')">
				<NcFormBox>
					<NcFormBoxSwitch
						v-model="layoutMessageView"
						:label="t('yoomail', 'Show all messages in thread')"
						:disabled="hasLoadingState('layout-message-view')"
						:description="t('yoomail', 'When off, only the selected message will be shown')" />
				</NcFormBox>
				<NcFormBox>
					<NcFormBoxSwitch
						v-model="sortFavorites"
						:label="t('yoomail', 'Sort favorites up')"
						:disabled="hasLoadingState('sort-favorites')"
						:description="t('yoomail', 'When on, favorite messages will be sorted to the top of folders')" />
				</NcFormBox>
				<NcRadioGroup v-model="layoutMode" :label="t('yoomail', 'Layout')">
					<NcRadioGroupButton :label="t('yoomail', 'Vertical split')" value="vertical-split" :disabled="hasLoadingState('layout-mode')">
						<template #icon>
							<VerticalSplit :size="20" />
						</template>
					</NcRadioGroupButton>
					<NcRadioGroupButton :label="t('yoomail', 'Horizontal split')" value="horizontal-split" :disabled="hasLoadingState('layout-mode')">
						<template #icon>
							<HorizontalSplit :size="20" />
						</template>
					</NcRadioGroupButton>
					<NcRadioGroupButton :label="t('yoomail', 'List')" value="no-split" :disabled="hasLoadingState('layout-mode')">
						<template #icon>
							<CompactMode :size="20" />
						</template>
					</NcRadioGroupButton>
				</NcRadioGroup>

				<NcFormBox>
					<NcFormBoxSwitch
						v-model="compactMode"
						:label="t('yoomail', 'Use compact mode')"
						:disabled="hasLoadingState('compact-mode')" />
				</NcFormBox>

				<NcRadioGroup :model-value="sortOrder" :label="t('yoomail', 'Sorting')" @update:modelValue="onSortByDate">
					<NcRadioGroupButton :label="t('yoomail', 'Newest first')" value="newest" :disabled="hasLoadingState('sort-order')" />
					<NcRadioGroupButton :label="t('yoomail', 'Oldest first')" value="oldest" :disabled="hasLoadingState('sort-order')" />
				</NcRadioGroup>

				<NcDialog
					:open.sync="textBlockDialogOpen"
					:name="t('yoomail', 'New text block')"
					:is-form="true"
					size="normal">
					<NcInputField v-model="localTextBlock.title" :label="t('yoomail', 'Title of the text block')" />
					<TextEditor
						v-model="localTextBlock.content"
						:is-bordered="true"
						:html="true"
						:placeholder="t('yoomail', 'Content of the text block')"
						:bus="bus" />
					<div class="text-block-buttons">
						<NcButton
							variant="tertiary"
							class="text-block-buttons__button"
							@click="closeTextBlockDialog">
							<template #icon>
								<IconClose :size="20" />
							</template>
							{{ t('yoomail', 'Cancel') }}
						</NcButton>
						<NcButton
							variant="primary"
							class="text-block-buttons__button"
							:disabled="!localTextBlock.title || !localTextBlock.content"
							@click="newTextBlock">
							<template #icon>
								<IconCheck :size="20" />
							</template>
							{{ t('yoomail', 'Ok') }}
						</NcButton>
					</div>
				</NcDialog>
			</NcAppSettingsSection>

			<NcAppSettingsSection id="messages" name="Messages">
				<NcFormBox>
					<NcFormBoxSwitch
						v-model="useExternalAvatars"
						:disabled="hasLoadingState('external-avatars')">
						{{ t('yoomail', 'Avatars from Gravatar and favicons') }}
					</NcFormBoxSwitch>

					<NcFormBoxSwitch
						v-model="searchPriorityBody"
						:disabled="hasLoadingState('search-priority-body')">
						{{ prioritySettingsText }}
					</NcFormBoxSwitch>
				</NcFormBox>

				<NcRadioGroup :model-value="useBottomReplies" :label="t('yoomail', 'Reply position')" @update:modelValue="onToggleButtonReplies">
					<NcRadioGroupButton :label="t('yoomail', 'Top')" :value="false" :disabled="hasLoadingState('reply-mode')" />
					<NcRadioGroupButton :label="t('yoomail', 'Bottom')" :value="true" :disabled="hasLoadingState('reply-mode')" />
				</NcRadioGroup>

				<NcFormGroup
					:label="t('yoomail', 'Text blocks')"
					:description="t('yoomail', 'Reusable pieces of text that can be inserted in messages')">
					<List
						:text-blocks="getMyTextBlocks()" />
					<NcButton variant="secondary" wide @click="() => textBlockDialogOpen = true">
						<template #icon>
							<IconAdd :size="20" />
						</template>
						{{ t('yoomail', 'New text block') }}
					</NcButton>
					<template v-if="getSharedTextBlocks().length > 0">
						<h6>{{ t('yoomail', 'Shared with me') }}</h6>
						<List
							:text-blocks="getSharedTextBlocks()"
							:shared="true" />
					</template>
				</NcFormGroup>
			</NcAppSettingsSection>

			<NcAppSettingsSection id="privacy" :name="t('yoomail', 'Privacy')">
				<NcFormBoxSwitch
					v-model="useDataCollection"
					:label="t('yoomail', 'Data collection')"
					:description="t('yoomail', 'Allow the app to collect and process data locally to adapt to your preferences')" />

				<NcFormGroup :label="t('yoomail', 'Always show images from')">
					<TrustedSenders />
				</NcFormGroup>
			</NcAppSettingsSection>

			<NcAppSettingsSection id="security" :name="t('yoomail', 'Security')">
				<NcFormBoxSwitch
					v-model="useInternalAddresses"
					:disabled="hasLoadingState('internal-addresses')"
					:label="internalAddressText"
					:description="t('yoomail', 'Manage your internal addresses and domains to ensure recognized contacts stay unmarked')" />
				<InternalAddress />

				<NcFormGroup :label="t('yoomail', 'S/MIME')">
					<NcButton
						class="app-settings-button"
						variant="secondary"
						:aria-label="t('yoomail', 'Manage certificates')"
						wide
						@click.prevent.stop="displaySmimeCertificateModal = true">
						<template #icon>
							<IconMedal :size="20" />
						</template>
						{{ t('yoomail', 'Manage certificates') }}
					</NcButton>
					<SmimeCertificateModal
						v-if="displaySmimeCertificateModal"
						@close="displaySmimeCertificateModal = false" />
				</NcFormGroup>

				<NcFormGroup :label="t('yoomail', 'Mailvelope')">
					<NcNoteCard v-if="mailvelopeIsAvailable" type="success">
						{{ t('yoomail', 'Mailvelope is enabled for the current domain.') }}
					</NcNoteCard>

					<NcFormBox v-else>
						<NcFormBoxButton
							href="https://www.mailvelope.com/"
							target="_blank"
							:label="t('yoomail', 'Step 1')"
							:description="t('yoomail', 'Install the browser extension')"
							inverted-accent />
						<NcFormBoxButton
							:label="t('yoomail', 'Step 2')"
							:description="t('yoomail', 'Enable for the current domain')"
							inverted-accent
							@click="mailvelopeAuthorizeDomain">
							<template #icon>
								<IconDomain :size="20" />
							</template>
						</NcFormBoxButton>
					</NcFormBox>
				</NcFormGroup>
			</NcAppSettingsSection>

			<NcAppSettingsSection v-if="followUpFeatureAvailable" id="autotagging-settings" :name="t('yoomail', 'Assistance features')">
				<NcFormBox>
					<NcFormBoxSwitch
						v-model="useFollowUpReminders"
						:disabled="hasLoadingState('follow-up-reminders')">
						{{ followUpReminderText }}
					</NcFormBoxSwitch>
				</NcFormBox>
			</NcAppSettingsSection>

			<NcAppSettingsSection v-if="contextChatFeatureAvailable" id="context-chat-settings" :name="t('yoomail', 'Context Chat integration')">
				<NcFormBox>
					<NcFormBoxSwitch
						v-model="useContextChat"
						:disabled="hasLoadingState('index-context-chat')">
						{{ contextChatText }}
					</NcFormBoxSwitch>
				</NcFormBox>
			</NcAppSettingsSection>

			<NcAppSettingsShortcutsSection>
				<NcHotkeyList>
					<NcHotkey :label="t('yoomail', 'Compose new message')" hotkey="C" />
					<NcHotkey :label="t('yoomail', 'Newer message')" hotkey="ArrowLeft" />
					<NcHotkey :label="t('yoomail', 'Older message')" hotkey="ArrowRight" />
					<NcHotkey :label="t('yoomail', 'Toggle star')" hotkey="S" />
					<NcHotkey :label="t('yoomail', 'Toggle unread')" hotkey="U" />
					<NcHotkey :label="t('yoomail', 'Archive')" hotkey="A" />
					<NcHotkey :label="t('yoomail', 'Delete')" hotkey="Delete" />
					<NcHotkey :label="t('yoomail', 'Search')" hotkey="Control F" />
					<NcHotkey :label="t('yoomail', 'Send')" hotkey="Control Enter" />
					<NcHotkey :label="t('yoomail', 'Refresh')" hotkey="R" />
					<NcHotkey :label="t('yoomail', 'Heading1')" hotkey="Control Alt 1" />
					<NcHotkey :label="t('yoomail', 'Heading2')" hotkey="Control Alt 2" />
					<NcHotkey :label="t('yoomail', 'Heading3')" hotkey="Control Alt 3" />
				</NcHotkeyList>
			</NcAppSettingsShortcutsSection>

			<NcAppSettingsSection id="about-settings" :name="t('yoomail', 'About')">
				<NcFormGroup
					:label="t('yoomail', 'Acknowledgements')"
					:description="t('yoomail', 'This application includes CKEditor, an open-source editor. Copyright © CKEditor contributors. Licensed under GPLv2.')" />
				<NcFormGroup
					:label="t('yoomail', 'YooMail {version}', { version: mailVersion })"
					:description="internalVersionLabel" />
			</NcAppSettingsSection>
		</NcAppSettingsDialog>
	</div>
</template>

<script>
import { showError } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import {
	NcAppSettingsDialog,
	NcAppSettingsSection,
	NcAppSettingsShortcutsSection,
	NcButton,
	NcDialog,
	NcFormBox,
	NcFormBoxButton,
	NcFormBoxSwitch,
	NcFormGroup,
	NcHotkey,
	NcHotkeyList,
	NcInputField,
	NcNoteCard,
	NcRadioGroup,
	NcRadioGroupButton,
} from '@nextcloud/vue'
import mitt from 'mitt'
import { mapState, mapStores } from 'pinia'
import IconArrow from 'vue-material-design-icons/ArrowRight.vue'
import IconCheck from 'vue-material-design-icons/Check.vue'
import IconClose from 'vue-material-design-icons/Close.vue'
import HorizontalSplit from 'vue-material-design-icons/DockBottom.vue'
import VerticalSplit from 'vue-material-design-icons/DockLeft.vue'
import IconDomain from 'vue-material-design-icons/Domain.vue'
import CompactMode from 'vue-material-design-icons/ListBoxOutline.vue'
import IconMedal from 'vue-material-design-icons/MedalOutline.vue'
import IconAdd from 'vue-material-design-icons/Plus.vue'
import InternalAddress from './InternalAddress.vue'
import SmimeCertificateModal from './smime/SmimeCertificateModal.vue'
import List from './textBlocks/List.vue'
import TextEditor from './TextEditor.vue'
import TrustedSenders from './TrustedSenders.vue'
import Logger from '../logger.js'
import useMainStore from '../store/mainStore.js'

export default {
	name: 'AppSettingsMenu',
	components: {
		TrustedSenders,
		InternalAddress,
		NcButton,
		IconAdd,
		IconMedal,
		IconClose,
		IconCheck,
		SmimeCertificateModal,
		NcAppSettingsDialog,
		NcAppSettingsSection,
		NcAppSettingsShortcutsSection,
		NcRadioGroup,
		NcRadioGroupButton,
		CompactMode,
		VerticalSplit,
		HorizontalSplit,
		List,
		NcDialog,
		NcInputField,
		TextEditor,
		NcFormBox,
		NcFormBoxButton,
		NcFormBoxSwitch,
		NcFormGroup,
		IconDomain,
		NcNoteCard,
		NcHotkeyList,
		NcHotkey,
		IconArrow,
	},

	provide() {
		return {
			addToFocusTrap: (trapElement) => this.trapElements.push(trapElement),
		}
	},

	props: {
		open: {
			required: true,
			type: Boolean,
		},
	},

	data() {
		return {
			loadingStates: [],
			prioritySettingsText: t('yoomail', 'Search the body of messages in priority Inbox'),

			optOutSettingsText: t('yoomail', 'Activate'),
			contextChatText: t('yoomail', 'Make mails available to Context Chat'),
			followUpReminderText: t('yoomail', 'Remind about messages that require a reply but received none'),
			internalAddressText: t('yoomail', 'Highlight external addresses'),
			toggleAutoTagging: false,
			displaySmimeCertificateModal: false,
			sortOrder: 'newest',
			showSettings: false,
			showAccountSettings: false,
			showMailSettings: true,
			selectedAccount: null,
			mailvelopeIsAvailable: false,
			trapElements: [],
			bus: mitt(),
			textBlockDialogOpen: false,
			localTextBlock: {
				title: '',
				content: '',
			},
		}
	},

	computed: {
		...mapStores(useMainStore),
		...mapState(useMainStore, [
			'getAccounts',
			'followUpFeatureAvailable',
			'contextChatFeatureAvailable',
			'getMyTextBlocks',
			'getSharedTextBlocks',
		]),

		useBottomReplies() {
			return this.mainStore.getPreference('reply-mode', 'top') === 'bottom'
		},

		allowNewMailAccounts() {
			return this.mainStore.getPreference('allow-new-accounts', true)
		},

		mailVersion() {
			return this.mainStore.getPreference('mailVersion', '0.0.0')
		},

		internalVersion() {
			return this.mainStore.getPreference('internalVersion', '')
		},

		internalVersionLabel() {
			return this.internalVersion
				? t('yoomail', 'Internal version: {version}', { version: this.internalVersion })
				: ''
		},

		accountsWithEmail() {
			return this.getAccounts.filter((account) => account && account.emailAddress && !account.isDelegated)
		},

		delegatedAccounts() {
			return this.getAccounts.filter((account) => account && account.emailAddress && account.isDelegated)
		},

		sortFavorites: {
			get() {
				return this.mainStore.getPreference('sort-favorites', 'false') === 'true'
			},

			set(value) {
				this.onToggleSortFavorites(value)
			},
		},

		searchPriorityBody: {
			get() {
				return this.mainStore.getPreference('search-priority-body', 'false') === 'true'
			},

			set(value) {
				this.onToggleSearchPriorityBody(value)
			},
		},

		useExternalAvatars: {
			get() {
				return this.mainStore.getPreference('external-avatars', 'true') === 'true'
			},

			set(value) {
				this.onToggleExternalAvatars(value)
			},
		},

		useDataCollection: {
			get() {
				return this.mainStore.getPreference('collect-data', 'true') === 'true'
			},

			set(value) {
				this.onToggleCollectData(value)
			},
		},

		useContextChat: {
			get() {
				return this.mainStore.getPreference('index-context-chat', 'true') === 'true'
			},

			set(value) {
				this.onToggleContextChat(value)
			},
		},

		useInternalAddresses: {
			get() {
				return this.mainStore.getPreference('internal-addresses', 'false') === 'true'
			},

			set(value) {
				this.onToggleInternalAddress(value)
			},
		},

		useFollowUpReminders: {
			get() {
				return this.mainStore.getPreference('follow-up-reminders', 'true') === 'true'
			},

			set(value) {
				this.onToggleFollowUpReminders(value)
			},
		},

		layoutMode: {
			get() {
				return this.mainStore.getPreference('layout-mode', 'vertical-split')
			},

			set(value) {
				this.setLayout(value)
			},
		},

		compactMode: {
			get() {
				return this.mainStore.getPreference('compact-mode', 'false') === 'true'
			},

			set(value) {
				this.setCompactMode(value)
			},
		},

		layoutMessageView: {
			get() {
				const preference = this.mainStore.getPreference('layout-message-view')
				return preference === 'threaded' ? true : false
			},

			set(value) {
				if (value) {
					this.setLayoutMessageView('threaded')
				} else {
					this.setLayoutMessageView('singleton')
				}
			},
		},
	},

	watch: {
		showSettings(value) {
			if (!value) {
				this.$emit('update:open', value)
			}
		},

		async open(value) {
			if (value) {
				await this.onOpen()
			}
		},
	},

	mounted() {
		this.sortOrder = this.mainStore.getPreference('sort-order', 'newest')
		document.addEventListener.call(window, 'mailvelope', () => this.checkMailvelope())
		if (!this.mainStore.areTextBlocksFetched()) {
			this.mainStore.fetchMyTextBlocks()
			this.mainStore.fetchSharedTextBlocks()
		}
	},

	updated() {
		this.checkMailvelope()
	},

	methods: {
		hasLoadingState(key) {
			return this.loadingStates.includes(key)
		},

		setLoadingState(key, value) {
			const index = this.loadingStates.indexOf(key)
			if (value && index === -1) {
				this.loadingStates.push(key)
			} else if (!value && index !== -1) {
				this.loadingStates.splice(index, 1)
			}
		},

		openAccountSettings(accountId) {
			this.mainStore.showSettingsForAccountMutation(accountId)
			this.showSettings = false
		},

		checkMailvelope() {
			this.mailvelopeIsAvailable = !!window.mailvelope
		},

		async setLayout(layoutMode) {
			this.setLoadingState('layout-mode', true)

			try {
				await this.mainStore.savePreference({
					key: 'layout-mode',
					value: layoutMode,
				})
			} catch (error) {
				Logger.error('Could not save preferences', { error })
			} finally {
				this.setLoadingState('layout-mode', false)
			}
		},

		async setCompactMode(value) {
			this.setLoadingState('compact-mode', true)

			try {
				await this.mainStore.savePreference({
					key: 'compact-mode',
					value: value ? 'true' : 'false',
				})
			} catch (error) {
				Logger.error('Could not save preferences', { error })
			} finally {
				this.setLoadingState('compact-mode', false)
			}
		},

		async setLayoutMessageView(value) {
			this.setLoadingState('layout-message-view', true)

			try {
				await this.mainStore.savePreference({
					key: 'layout-message-view',
					value,
				})
			} catch (error) {
				Logger.error('Could not save preferences', { error })
			} finally {
				this.setLoadingState('layout-message-view', false)
			}
		},

		async onOpen() {
			this.showSettings = true
		},

		onToggleButtonReplies(atBottom) {
			this.setLoadingState('reply-mode', true)

			this.mainStore.savePreference({
				key: 'reply-mode',
				value: atBottom ? 'bottom' : 'top',
			})
				.catch((error) => Logger.error('could not save preferences', { error }))
				.then(() => {
					this.setLoadingState('reply-mode', false)
				})
		},

		onToggleExternalAvatars(enabled) {
			this.setLoadingState('external-avatars', true)

			this.mainStore.savePreference({
				key: 'external-avatars',
				value: enabled ? 'true' : 'false',
			})
				.catch((error) => Logger.error('could not save preferences', { error }))
				.then(() => {
					this.setLoadingState('external-avatars', false)
				})
		},

		async onToggleSearchPriorityBody(enabled) {
			this.setLoadingState('search-priority-body', true)

			try {
				await this.mainStore.savePreference({
					key: 'search-priority-body',
					value: enabled ? 'true' : 'false',
				})
			} catch (error) {
				Logger.error('could not save preferences', { error })
			} finally {
				this.setLoadingState('search-priority-body', false)
			}
		},

		async onToggleSortFavorites(enabled) {
			this.setLoadingState('sort-favorites', true)

			try {
				await this.mainStore.savePreference({
					key: 'sort-favorites',
					value: enabled ? 'true' : 'false',
				})
			} catch (error) {
				Logger.error('could not save preferences', { error })
			} finally {
				this.setLoadingState('sort-favorites', false)
			}
		},

		onToggleCollectData(collect) {
			this.setLoadingState('collect-data', true)

			this.mainStore.savePreference({
				key: 'collect-data',
				value: collect ? 'true' : 'false',
			})
				.catch((error) => Logger.error('could not save preferences', { error }))
				.then(() => {
					this.setLoadingState('collect-data', false)
				})
		},

		async onSortByDate(value) {
			this.setLoadingState('sort-order', true)

			const previousValue = this.sortOrder
			try {
				this.sortOrder = value
				await this.mainStore.savePreference({
					key: 'sort-order',
					value,
				})
				this.mainStore.removeAllEnvelopesMutation()
			} catch (error) {
				Logger.error('could not save preferences', { error })
				this.sortOrder = previousValue
				showError(t('yoomail', 'Could not update preference'))
			} finally {
				this.setLoadingState('sort-order', false)
			}
		},

		async onToggleFollowUpReminders(enabled) {
			this.setLoadingState('follow-up-reminders', true)

			try {
				await this.mainStore.savePreference({
					key: 'follow-up-reminders',
					value: enabled ? 'true' : 'false',
				})
			} catch (error) {
				Logger.error('Could not save preferences', { error })
				showError(t('yoomail', 'Could not update preference'))
			} finally {
				this.setLoadingState('follow-up-reminders', false)
			}
		},

		async onToggleContextChat(enabled) {
			this.setLoadingState('index-context-chat', true)

			try {
				await this.mainStore.savePreference({
					key: 'index-context-chat',
					value: enabled ? 'true' : 'false',
				})
			} catch (error) {
				Logger.error('Could not save preferences', { error })
				showError(t('yoomail', 'Could not update preference'))
			} finally {
				this.setLoadingState('index-context-chat', false)
			}
		},

		async onToggleInternalAddress(enabled) {
			this.setLoadingState('internal-addresses', true)

			try {
				await this.mainStore.savePreference({
					key: 'internal-addresses',
					value: enabled ? 'true' : 'false',
				})
			} catch (error) {
				Logger.error('Could not save preferences', { error })
				showError(t('yoomail', 'Could not update preference'))
			} finally {
				this.setLoadingState('internal-addresses', false)
			}
		},

		registerProtocolHandler() {
			if (window.navigator.registerProtocolHandler) {
				const url
					= window.location.protocol + '//' + window.location.host + generateUrl('apps/yoomail/compose?uri=%s')
				try {
					window.navigator.registerProtocolHandler('mailto', url, OC.theme.name + ' Mail')
				} catch (err) {
					Logger.error('could not register protocol handler', { err })
				}
			}
		},

		mailvelopeAuthorizeDomain() {
			const iframe = document.createElement('iframe')
			iframe.style = 'display: none'
			iframe.src = 'https://api.mailvelope.com/authorize-domain/?api=true'
			document.body.append(iframe)
		},

		newTextBlock() {
			this.mainStore.createTextBlock({ ...this.localTextBlock })
			this.textBlockDialogOpen = false
			this.localTextBlock = {
				title: '',
				content: '',
			}
		},

		closeTextBlockDialog() {
			this.textBlockDialogOpen = false
			this.localTextBlock = {
				title: '',
				content: '',
			}
		},
	},
}
</script>
