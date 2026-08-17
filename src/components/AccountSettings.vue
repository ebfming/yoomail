<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<AppSettingsDialog
		id="app-settings-dialog"
		:open="open"
		:show-navigation="true"
		:additional-trap-elements="trapElements"
		:name="t('yoomail', 'Account settings')"
		@update:open="updateOpen">
		<AppSettingsSection
			id="alias-settings"
			:name="t('yoomail', 'Aliases')">
			<AliasSettings :account="account" @rename-primary-alias="scrollToAccountSettings" />
		</AppSettingsSection>
		<AppSettingsSection
			id="certificate-settings"
			:name="t('yoomail', 'Alias to S/MIME certificate mapping')">
			<CertificateSettings :account="account" />
		</AppSettingsSection>
		<AppSettingsSection id="writing-mode" :name="t('yoomail', 'Writing mode')">
			<p class="settings-hint">
				{{ t('yoomail', 'Preferred writing mode for new messages and replies.') }}
			</p>
			<EditorSettings :account="account" />
		</AppSettingsSection>
		<AppSettingsSection id="signature" :name="t('yoomail', 'Signature')">
			<p class="settings-hint">
				{{ t('yoomail', 'A signature is added to the text of new messages and replies.') }}
			</p>
			<SignatureSettings :account="account" />
		</AppSettingsSection>
		<AppSettingsSection id="default-folders" :name=" t('yoomail', 'Default folders')">
			<p class="settings-hint">
				{{
					t('yoomail', 'The folders to use for drafts, sent messages, deleted messages, archived messages and junk messages.')
				}}
			</p>
			<AccountDefaultsSettings :account="account" />
		</AppSettingsSection>
		<AppSettingsSection id="trash-retention" :name=" t('yoomail', 'Automatic trash deletion')">
			<p class="settings-hint">
				{{ t('yoomail', 'Days after which messages in Trash will automatically be deleted:') }}
			</p>
			<TrashRetentionSettings :account="account" />
		</AppSettingsSection>
		<AppSettingsSection
			v-if="account"
			id="out-of-office-replies"
			:name="t('yoomail', 'Autoresponder')">
			<p class="settings-hint">
				{{ t('yoomail', 'Automated reply to incoming messages. If someone sends you several messages, this automated reply will be sent at most once every 4 days.') }}
			</p>
			<OutOfOfficeForm v-if="account.sieveEnabled" :account="account" />
			<div v-else>
				<p>{{ t('yoomail', 'The autoresponder uses Sieve, a scripting language supported by many email providers. If you\'re unsure whether yours does, check with your provider. If Sieve is available, click the button to go to the settings and enable it.') }}</p>
				<NcButton variant="secondary" :aria-label="t('yoomail', 'Go to Sieve settings')" href="#sieve-form">
					{{ t('yoomail', 'Go to Sieve settings') }}
				</NcButton>
			</div>
		</AppSettingsSection>
		<AppSettingsSection
			v-if="account && systemVersion >= 33"
			id="calendar-settings"
			:name="t('yoomail', 'Calendar settings')">
			<CalendarSettings :account="account" />
		</AppSettingsSection>
		<AppSettingsSection
			v-if="account"
			id="classification"
			:name="t('yoomail', 'Classification settings')">
			<NcCheckboxRadioSwitch
				id="auto-classification-enabled"
				:model-value="account.classificationEnabled"
				:disabled="loadingClassificationToggle"
				@update:checked="onToggleClassification">
				{{ t('yoomail', 'Enable mark as important classification') }}
			</NcCheckboxRadioSwitch>
		</AppSettingsSection>
		<AppSettingsSection
			v-if="account && account.sieveEnabled"
			id="mail-filters"
			:name="t('yoomail', 'Filters')">
			<div id="mail-filters">
				<MailFilters :key="account.accountId" ref="mailFilters" :account="account" />
			</div>
		</AppSettingsSection>
		<AppSettingsSection
			v-if="account"
			id="quick-actions-settings"
			:name="t('yoomail', 'Quick actions')">
			<Settings :key="account.accountId" ref="quickActions" :account="account" />
		</AppSettingsSection>
		<AppSettingsSection
			v-if="account && account.sieveEnabled"
			id="sieve-filter"
			:name="t('yoomail', 'Sieve script editor')">
			<div id="sieve-filter">
				<SieveFilterForm
					:key="account.accountId"
					ref="sieveFilterForm"
					:account="account" />
			</div>
		</AppSettingsSection>
		<AppSettingsSection
			v-if="account && !account.provisioningId && !account.isDelegated"
			id="mail-server"
			ref="mail-server"
			:name="t('yoomail', 'Mail server')">
			<div id="mail-settings">
				<AccountForm
					:key="account.accountId"
					ref="accountForm"
					:display-name="displayName"
					:email="email"
					:account="account" />
			</div>
		</AppSettingsSection>
		<AppSettingsSection
			v-if="account && !account.provisioningId"
			id="sieve-settings"
			:name="t('yoomail', 'Sieve server')">
			<div id="sieve-settings">
				<SieveAccountForm
					:key="account.accountId"
					ref="sieveAccountForm"
					:account="account" />
			</div>
		</AppSettingsSection>
		<!-- TRANSLATORS: Settings for searching in a folder -->
		<AppSettingsSection id="mailbox_search" :name="t('yoomail', 'Folder search')">
			<SearchSettings :account="account" />
		</AppSettingsSection>
	</AppSettingsDialog>
</template>

<script>
import { NcAppSettingsDialog as AppSettingsDialog, NcAppSettingsSection as AppSettingsSection, NcButton, NcCheckboxRadioSwitch } from '@nextcloud/vue'
import { mapStores } from 'pinia'
import AccountDefaultsSettings from '../components/AccountDefaultsSettings.vue'
import AccountForm from '../components/AccountForm.vue'
import AliasSettings from '../components/AliasSettings.vue'
import EditorSettings from '../components/EditorSettings.vue'
import SignatureSettings from '../components/SignatureSettings.vue'
import CalendarSettings from './CalendarSettings.vue'
import CertificateSettings from './CertificateSettings.vue'
import MailFilters from './mailFilter/MailFilters.vue'
import OutOfOfficeForm from './OutOfOfficeForm.vue'
import Settings from './quickActions/Settings.vue'
import SearchSettings from './SearchSettings.vue'
import SieveAccountForm from './SieveAccountForm.vue'
import SieveFilterForm from './SieveFilterForm.vue'
import TrashRetentionSettings from './TrashRetentionSettings.vue'
import logger from '../logger.js'
import useMainStore from '../store/mainStore.js'

export default {
	name: 'AccountSettings',
	components: {
		SieveAccountForm,
		SieveFilterForm,
		AccountForm,
		AliasSettings,
		EditorSettings,
		SignatureSettings,
		AppSettingsDialog,
		AppSettingsSection,
		AccountDefaultsSettings,
		CalendarSettings,
		OutOfOfficeForm,
		CertificateSettings,
		TrashRetentionSettings,
		SearchSettings,
		MailFilters,
		NcButton,
		Settings,
		NcCheckboxRadioSwitch,
	},

	provide() {
		return {
			addToFocusTrap: (trapElement) => this.trapElements.push(trapElement),
		}
	},

	props: {
		account: {
			required: true,
			type: Object,
		},

		open: {
			type: Boolean,
			default: false,
		},

		scrollToSection: {
			type: String,
			default: undefined,
		},
	},

	data() {
		return {
			trapElements: [],
			fetchActiveSieveScript: this.account.sieveEnabled,
			loadingClassificationToggle: false,
			systemVersion: parseInt(OC.config.version.split('.')[0], 10),
		}
	},

	computed: {
		...mapStores(useMainStore),
		displayName() {
			return this.account.name
		},

		email() {
			return this.account.emailAddress
		},
	},

	watch: {
		open(newState, oldState) {
			if (newState === true && this.fetchActiveSieveScript === true) {
				logger.debug(`Load active sieve script for account ${this.account.accountId}`)
				this.fetchActiveSieveScript = false
				this.mainStore.fetchActiveSieveScript({
					accountId: this.account.id,
				})
			}
		},

		scrollToSection(newState) {
			this.$nextTick(() => {
				this.$refs[newState]?.$el?.scrollIntoView({
					behavior: 'smooth',
					block: 'start',
				})
			})
		},
	},

	methods: {
		scrollToAccountSettings() {
			this.$refs.accountForm.$el.scrollIntoView({
				behavior: 'smooth',
			})
		},

		updateOpen() {
			this.$emit('update:open')
		},

		async onToggleClassification(classificationEnabled) {
			this.loadingClassificationToggle = true
			await this.mainStore.patchAccount({ account: this.account, data: { classificationEnabled } })
			this.loadingClassificationToggle = false
		},
	},
}
</script>

<style lang="scss" scoped>
.alias-item {
	display: flex;
	justify-content: space-between;
}

.button.icon-rename {
	background-image: var(--icon-rename-000);
	background-color: var(--color-main-background);
	border: none;
	opacity: 0.7;
	&:hover,
	&:focus {
		opacity: 1;
	}
}

.settings-hint {
	margin-top: calc(var(--default-grid-baseline) * -3);
	margin-bottom: calc(var(--default-grid-baseline) * 2);
	color: var(--color-text-maxcontrast);
}

h2 {
	font-weight: bold;
	font-size: 20px;
	margin-bottom: calc(var(--default-grid-baseline) * 3);
	margin-inline-start: calc(var(--default-grid-baseline) * -7);
	line-height: calc(var(--default-grid-baseline) * 7);
	color: var(--color-text-light);
}

.app-settings-section {
	margin-bottom: calc(var(--default-grid-baseline) * 12);
}
</style>
