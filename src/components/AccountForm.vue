<!--
  - SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<form id="account-form" @submit.prevent="onSubmit">
		<Tabs
			:options="{ useUrlFragment: false, defaultTabHash: settingsPage ? 'manual' : 'auto' }"
			cache-lifetime="0"
			@changed="onModeChanged">
			<Tab id="auto" key="auto" :name="t('yoomail', 'Auto')">
				<NcInputField
					id="auto-name"
					v-model="accountName"
					:label="t('yoomail', 'Name')"
					type="text"
					:placeholder="t('yoomail', 'Name')"
					:disabled="loading"
					autofocus />
				<NcInputField
					id="auto-address"
					v-model="emailAddress"
					:label="t('yoomail', 'Mail address')"
					:disabled="loading"
					:placeholder="t('yoomail', 'name@example.org')"
					required
					type="email"
					@change="clearFeedback" />
				<p v-if="!isValidEmail(emailAddress)" class="account-form--error">
					{{ t('yoomail', 'Please enter an email of the format name@example.com') }}
				</p>
				<NcPasswordField
					id="auto-password"
					v-model="autoConfig.password"
					:disabled="loading"
					type="password"
					:label="t('yoomail', 'Password')"
					:required="!hasPasswordAlternatives"
					@change="clearFeedback" />
				<NcCheckboxRadioSwitch
					id="auto-classification-enabled"
					v-model="classificationEnabled"
					:disabled="loading">
					{{ t('yoomail', 'Enable mark as important classification') }}
				</NcCheckboxRadioSwitch>
			</Tab>
			<Tab id="manual" key="manual" :name="t('yoomail', 'Manual')">
				<NcInputField
					id="man-name"
					v-model="accountName"
					:label="t('yoomail', 'Name')"
					type="text"
					:placeholder="t('yoomail', 'Name')"
					:disabled="loading" />
				<NcInputField
					id="man-address"
					v-model="emailAddress"
					:label="t('yoomail', 'Mail address')"
					type="email"
					:placeholder="t('yoomail', 'name@example.org')"
					:disabled="loading"
					required
					@change="clearFeedback" />
				<p v-if="!isValidEmail(emailAddress)" class="account-form--error">
					{{ t('yoomail', 'Please enter an email of the format name@example.com') }}
				</p>

				<h4>{{ t('yoomail', 'IMAP Settings') }}</h4>
				<NcInputField
					id="man-imap-host"
					v-model="manualConfig.imapHost"
					:label="t('yoomail', 'IMAP Host')"
					type="text"
					:placeholder="t('yoomail', 'IMAP Host')"
					:disabled="loading"
					required
					@change="clearFeedback" />
				<h4 class="account-form__heading--required">
					{{ t('yoomail', 'IMAP Security') }}
				</h4>
				<div class="flex-row">
					<NcCheckboxRadioSwitch
						id="man-imap-sec-none"
						:button-variant="true"
						:model-value="manualConfig.imapSslMode"
						type="radio"
						name="man-imap-sec"
						:disabled="loading"
						value="none"
						button-variant-grouped="horizontal"
						@update:checked="onImapSslModeChange">
						{{ t('yoomail', 'None') }}
					</NcCheckboxRadioSwitch>
					<NcCheckboxRadioSwitch
						id="man-imap-sec-ssl"
						:button-variant="true"
						:model-value="manualConfig.imapSslMode"
						type="radio"
						name="man-imap-sec"
						:disabled="loading"
						value="ssl"
						button-variant-grouped="horizontal"
						@update:checked="onImapSslModeChange">
						{{ t('yoomail', 'SSL/TLS') }}
					</NcCheckboxRadioSwitch>
					<NcCheckboxRadioSwitch
						id="man-imap-sec-tls"
						:button-variant="true"
						:model-value="manualConfig.imapSslMode"
						type="radio"
						name="man-imap-sec"
						:disabled="loading"
						value="tls"
						button-variant-grouped="horizontal"
						@update:checked="onImapSslModeChange">
						{{ t('yoomail', 'STARTTLS') }}
					</NcCheckboxRadioSwitch>
				</div>
				<NcInputField
					id="man-imap-port"
					v-model="manualConfig.imapPort"
					:label="t('yoomail', 'IMAP Port')"
					type="number"
					:placeholder="t('yoomail', 'IMAP Port')"
					:disabled="loading"
					required
					@change="clearFeedback" />
				<NcInputField
					id="man-imap-user"
					v-model="manualConfig.imapUser"
					:label="t('yoomail', 'IMAP User')"
					type="text"
					:placeholder="t('yoomail', 'IMAP User')"
					:disabled="loading"
					required
					@change="clearFeedback" />
				<NcPasswordField
					v-if="!useOauth"
					id="man-imap-password"
					v-model="manualConfig.imapPassword"
					type="password"
					:label="t('yoomail', 'IMAP Password')"
					:disabled="loading"
					required
					@change="clearFeedback" />

				<h4>{{ t('yoomail', 'SMTP Settings') }}</h4>
				<NcInputField
					id="man-smtp-host"
					ref="smtpHost"
					v-model="manualConfig.smtpHost"
					:label="t('yoomail', 'SMTP Host')"
					type="text"
					name="smtp-host"
					:placeholder="t('yoomail', 'SMTP Host')"
					:disabled="loading"
					required
					@change="clearFeedback" />
				<h4 class="account-form__heading--required">
					{{ t('yoomail', 'SMTP Security') }}
				</h4>
				<div class="flex-row">
					<NcCheckboxRadioSwitch
						id="man-imap-sec-none"
						:button-variant="true"
						:model-value="manualConfig.smtpSslMode"
						type="radio"
						name="man-smtp-sec"
						:disabled="loading"
						value="none"
						button-variant-grouped="horizontal"
						@update:checked="onSmtpSslModeChange">
						{{ t('yoomail', 'None') }}
					</NcCheckboxRadioSwitch>
					<NcCheckboxRadioSwitch
						id="man-imap-sec-ssl"
						:button-variant="true"
						:model-value="manualConfig.smtpSslMode"
						type="radio"
						name="man-smtp-sec"
						:disabled="loading"
						value="ssl"
						button-variant-grouped="horizontal"
						@update:checked="onSmtpSslModeChange">
						{{ t('yoomail', 'SSL/TLS') }}
					</NcCheckboxRadioSwitch>
					<NcCheckboxRadioSwitch
						id="man-imap-sec-tls"
						:button-variant="true"
						:model-value="manualConfig.smtpSslMode"
						type="radio"
						name="man-smtp-sec"
						:disabled="loading"
						value="tls"
						button-variant-grouped="horizontal"
						@update:checked="onSmtpSslModeChange">
						{{ t('yoomail', 'STARTTLS') }}
					</NcCheckboxRadioSwitch>
				</div>
				<NcInputField
					id="man-smtp-port"
					v-model="manualConfig.smtpPort"
					:label="t('yoomail', 'SMTP Port')"
					type="number"
					:placeholder="t('yoomail', 'SMTP Port')"
					:disabled="loading"
					required
					@change="clearFeedback" />
				<NcInputField
					id="man-smtp-user"
					v-model="manualConfig.smtpUser"
					:label="t('yoomail', 'SMTP User')"
					type="text"
					:placeholder="t('yoomail', 'SMTP User')"
					:disabled="loading"
					required
					@change="clearFeedback" />
				<NcPasswordField
					v-if="!useOauth"
					id="man-smtp-password"
					v-model="manualConfig.smtpPassword"
					:label="t('yoomail', 'SMTP Password')"
					type="password"
					:disabled="loading"
					required
					@change="clearFeedback" />
				<NcCheckboxRadioSwitch
					v-if="isSetup"
					id="auto-classification-enabled"
					v-model="classificationEnabled"
					:disabled="loading">
					{{ t('yoomail', 'Enable mark as important classification') }}
				</NcCheckboxRadioSwitch>
			</Tab>
		</Tabs>
		<div v-if="isGoogleAccount && !googleOauthUrl" class="account-form__google-sso">
			{{ t('yoomail', 'Google requires OAuth authentication. If your Nextcloud admin has not configured Google OAuth, you can use a Google App Password instead.') }}
		</div>
		<div v-if="isMicrosoftAccount && !microsoftOauthUrl" class="account-form__microsoft-sso">
			{{ t('yoomail', 'Microsoft requires OAuth authentication. Ask your Nextcloud admin to configure Microsoft OAuth in the admin settings.') }}
		</div>
		<div class="account-form__submit-buttons">
			<ButtonVue
				v-if="mode === 'auto'"
				:aria-label="submitButtonText"
				class="account-form__submit-button"
				type="primary"
				native-type="submit"
				:disabled="isDisabledAuto || loading"
				@click.prevent="onSubmit">
				<template #icon>
					<IconLoading v-if="loading" :size="20" />
					<IconCheck v-else :size="20" />
				</template>
				{{ submitButtonText }}
			</ButtonVue>
			<ButtonVue
				v-else-if="mode === 'manual'"
				:aria-label="submitButtonText"
				class="account-form__submit-button"
				type="primary"
				native-type="submit"
				:disabled="isDisabledManual || loading"
				@click.prevent="onSubmit">
				<template #icon>
					<IconLoading v-if="loading" :size="20" />
					<IconCheck v-else :size="20" />
				</template>
				{{ submitButtonText }}
			</ButtonVue>
		</div>
		<div v-if="feedback" class="account-form--feedback">
			{{ feedback }}
		</div>
	</form>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import { translate as t } from '@nextcloud/l10n'
import { NcButton as ButtonVue, NcLoadingIcon as IconLoading, NcCheckboxRadioSwitch, NcInputField, NcPasswordField } from '@nextcloud/vue'
import { mapState, mapStores } from 'pinia'
import { Tab, Tabs } from 'vue-tabs-component'
import IconCheck from 'vue-material-design-icons/Check.vue'
import { CONSENT_ABORTED, getUserConsent } from '../integration/oauth.js'
import logger from '../logger.js'
import {
	queryIspdb,
	queryMx,
	testConnectivity,
} from '../service/AutoConfigService.js'
import { generateOauthState } from '../service/OauthStateService.js'
import useMainStore from '../store/mainStore.js'

export default {
	name: 'AccountForm',
	components: {
		NcPasswordField,
		NcInputField,
		NcCheckboxRadioSwitch,
		Tab,
		Tabs,
		ButtonVue,
		IconLoading,
		IconCheck,
	},

	props: {
		displayName: {
			type: String,
			default: '',
		},

		email: {
			type: String,
			default: '',
		},

		account: {
			type: Object,
			required: false,
			default: () => undefined,
		},
	},

	data() {
		const fromAccountOr = (prop, def) => {
			if (this.account !== undefined) {
				return this.account[prop]
			} else {
				return def
			}
		}

		return {
			loading: false,
			loadingMessage: undefined,
			mode: 'auto',
			accountName: this.displayName,
			emailAddress: this.email,
			classificationEnabled: fromAccountOr('classificationEnabled', loadState('yoomail', 'importance_classification_default', true)),
			autoConfig: {
				password: '',
			},

			manualConfig: {
				imapHost: fromAccountOr('imapHost', ''),
				imapPort: fromAccountOr('imapPort', 993),
				imapSslMode: fromAccountOr('imapSslMode', 'ssl'),
				imapUser: fromAccountOr('imapUser', ''),
				imapPassword: '',
				smtpHost: fromAccountOr('smtpHost', ''),
				smtpPort: fromAccountOr('smtpPort', 587),
				smtpSslMode: fromAccountOr('smtpSslMode', 'tls'),
				smtpUser: fromAccountOr('smtpUser', ''),
				smtpPassword: '',
			},

			feedback: null,
			password: '',
		}
	},

	computed: {
		...mapStores(useMainStore),
		...mapState(useMainStore, [
			'googleOauthUrl',
			'microsoftOauthUrl',
		]),

		settingsPage() {
			return this.account !== undefined
		},

		isSetup() {
			return this.account === undefined
		},

		isDisabledAuto() {
			if (this.loading) {
				return true
			}

			if (this.mode !== 'auto') {
				return this.loading
			}

			return !this.emailAddress || !this.isValidEmail(this.emailAddress)
				|| (!this.googleOauthUrl && !this.autoConfig.password)
				|| (!this.microsoftOauthUrl && !this.autoConfig.password)
		},

		isDisabledManual() {
			if (this.loading) {
				return true
			}

			if (this.mode !== 'manual') {
				return this.loading
			}

			return !this.emailAddress || !this.isValidEmail(this.emailAddress)
				|| !this.manualConfig.imapHost || !this.manualConfig.imapPort
				|| !this.manualConfig.imapUser || (!this.useOauth && !this.manualConfig.imapPassword)
				|| !this.manualConfig.smtpHost || !this.manualConfig.smtpPort
				|| !this.manualConfig.smtpUser || (!this.useOauth && !this.manualConfig.smtpPassword)
		},

		isGoogleAccount() {
			return this.manualConfig.imapHost === 'imap.gmail.com'
				|| this.manualConfig.smtpHost === 'smtp.gmail.com'
		},

		isMicrosoftAccount() {
			return this.manualConfig.imapHost === 'outlook.office365.com'
				|| this.manualConfig.smtpHost === 'outlook.office365.com'
		},

		hasPasswordAlternatives() {
			return !!this.googleOauthUrl
				|| !!this.microsoftOauthUrl
		},

		useOauth() {
			return (this.isGoogleAccount && this.googleOauthUrl)
				|| (this.isMicrosoftAccount && this.microsoftOauthUrl)
		},

		submitButtonText() {
			if (this.loading) {
				return this.loadingMessage ?? t('yoomail', 'Connecting')
			}
			if (this.mode === 'manual' && this.useOauth) {
				if (this.isGoogleAccount) {
					return this.account ? t('yoomail', 'Reconnect Google account') : t('yoomail', 'Sign in with Google')
				} else {
					return this.account ? t('yoomail', 'Reconnect Microsoft account') : t('yoomail', 'Sign in with Microsoft')
				}
			}
			return this.account ? t('yoomail', 'Save') : t('yoomail', 'Connect')
		},
	},

	methods: {
		onModeChanged(e) {
			this.mode = e.tab.id

			if (this.mode === 'manual') {
				// IMAP
				if (this.manualConfig.imapUser === '') {
					this.manualConfig.imapUser = this.emailAddress
				}
				if (this.manualConfig.imapPassword === '') {
					this.manualConfig.imapPassword = this.autoConfig.password
				}

				// SMTP
				if (this.manualConfig.smtpUser === '') {
					this.manualConfig.smtpUser = this.emailAddress
				}
				if (this.manualConfig.smtpPassword === '') {
					this.manualConfig.smtpPassword = this.autoConfig.password
				}
			}
		},

		onImapSslModeChange(value) {
			this.clearFeedback()
			this.manualConfig.imapSslMode = value
			switch (this.manualConfig.imapSslMode) {
				case 'none':
				case 'tls':
					this.manualConfig.imapPort = 143
					break
				case 'ssl':
					this.manualConfig.imapPort = 993
					break
			}
		},

		onSmtpSslModeChange(value) {
			this.clearFeedback()
			this.manualConfig.smtpSslMode = value
			switch (this.manualConfig.smtpSslMode) {
				case 'none':
				case 'tls':
					this.manualConfig.smtpPort = 587
					break
				case 'ssl':
					this.manualConfig.smtpPort = 465
					break
			}
		},

		clearFeedback() {
			this.feedback = null
		},

		applyAutoConfig(config) {
			if (!config) {
				return false
			}
			if (config?.imapConfig) {
				this.manualConfig.imapUser = config.imapConfig.username
				this.manualConfig.imapHost = config.imapConfig.host
				this.manualConfig.imapPort = config.imapConfig.port
				this.manualConfig.imapSslMode = config.imapConfig.security
				this.manualConfig.imapPassword = this.autoConfig.password
			}
			if (config?.smtpConfig) {
				this.manualConfig.smtpUser = config.smtpConfig.username
				this.manualConfig.smtpHost = config.smtpConfig.host
				this.manualConfig.smtpPort = config.smtpConfig.port
				this.manualConfig.smtpSslMode = config.smtpConfig.security
				this.manualConfig.smtpPassword = this.autoConfig.password
			}
			return true
		},

		async detectConfig() {
			this.loadingMessage = t('yoomail', 'Looking up configuration')
			const config = await queryIspdb(this.emailAddress.split('@').pop(), this.emailAddress)
			logger.debug('fetched auto config', { config })
			// Apply settings to manual mode before submitting so the user
			// can make modifications if the config fails
			if (this.applyAutoConfig(config)) {
				logger.debug('ISP DB config applied')
				return true
			} else {
				const mxHosts = await queryMx(this.emailAddress)
				logger.debug('MX hosts fetched', { mxHosts })

				if (mxHosts.length) {
					// Try the TLD of the MX
					// FIXME: breaks with eTLDs like .co.uk
					const tldMx = mxHosts[0].split('.').splice(-2).join('.').toLowerCase()
					const mxConfig = await queryIspdb(
						tldMx,
						this.emailAddress,
					)
					logger.debug('fetched MX auto config', { mxConfig })
					if (mxConfig && this.applyAutoConfig(mxConfig)) {
						return true
					}
				}

				// Test the highest priority MX for open IMAP/SMTP ports
				this.loadingMessage = t('yoomail', 'Checking mail host connectivity')
				const imapAndSmtpHosts = mxHosts.slice(0, 1).flatMap((host) => {
					return [993, 143, 465, 587].map((port) => ({
						host,
						port,
					}))
				})
				const results = await Promise.all(imapAndSmtpHosts.map(async ({ host, port }) => {
					return {
						host,
						port,
						canConnect: await testConnectivity(host, port),
					}
				}))
				const firstImapHost = results.filter(({ canConnect, port }) => canConnect && port === 993)[0]
				const firstSmtpHost = results.filter(({ canConnect, port }) => canConnect && [465, 587].includes(port))[0]
				logger.debug('MX connectivity tested', { firstImapHost, firstSmtpHost })
				if (firstImapHost && firstSmtpHost) {
					this.applyAutoConfig({
						imapConfig: {
							username: this.emailAddress, // Assumption
							host: firstImapHost.host,
							port: firstImapHost.port,
							security: firstImapHost.port === 993 ? 'ssl' : 'tls',
						},
						smtpConfig: {
							username: this.emailAddress, // Assumption
							host: firstSmtpHost.host,
							port: firstSmtpHost.port,
							security: firstSmtpHost.port === 465 ? 'ssl' : 'tls',
						},
					})
					return true
				} else {
					this.feedback = t('yoomail', 'Configuration discovery failed. Please use the manual settings')
				}
				return false
			}
		},

		async onSubmit(event) {
			logger.debug('account form submitted', { event })
			if (this.isDisabledManual || this.isDisabledAuto) {
				logger.warn('submit is disabled')
				return
			}
			this.clearFeedback()
			this.loading = true
			try {
				if (this.mode === 'auto') {
					if (!await this.detectConfig()) {
						logger.warn('Auto mode failed')
						return
					}
				}
				if (!this.useOauth) {
					if (this.mode === 'auto' && this.autoConfig.password === '') {
						this.feedback = t('yoomail', 'Password required')
						return
					}
					if (this.mode === 'manual' && (this.manualConfig.imapPassword === '' || this.manualConfig.smtpPassword === '')) {
						this.feedback = t('yoomail', 'Password required')
						return
					}
				}
				this.loadingMessage = t('yoomail', 'Testing authentication')
				const data = {
					...this.manualConfig,
					accountName: this.accountName,
					emailAddress: this.emailAddress,
					imapHost: this.manualConfig.imapHost.trim(),
					smtpHost: this.manualConfig.smtpHost.trim(),
					authMethod: this.useOauth ? 'xoauth2' : 'password',
					classificationEnabled: this.classificationEnabled,
				}
				if (this.useOauth) {
					delete data.imapPassword
					delete data.smtpPassword
				}
				if (!this.account) {
					const account = await this.mainStore.startAccountSetup(data)
					if (this.useOauth) {
						this.loadingMessage = t('yoomail', 'Awaiting user consent')
						try {
							if (this.isGoogleAccount) {
								this.feedback = t('yoomail', 'Account created. Please follow the pop-up instructions to link your Google account')
								await getUserConsent(this.googleOauthUrl
									.replace('_state_', await generateOauthState(account.id))
									.replace('_email_', encodeURIComponent(account.emailAddress)))
							} else {
								this.feedback = t('yoomail', 'Account created. Please follow the pop-up instructions to link your Microsoft account')
								await getUserConsent(this.microsoftOauthUrl
									.replace('_state_', await generateOauthState(account.id))
									.replace('_email_', encodeURIComponent(account.emailAddress)))
							}
						} catch (e) {
							// Clean up the temporary account before we continue
							this.mainStore.deleteAccount(account)
							logger.info(`Temporary account ${account.id} deleted`)
							throw e
						}
						this.clearFeedback()
					}
					this.loadingMessage = t('yoomail', 'Loading account')
					await this.mainStore.finishAccountSetup({ account })
					this.$emit('account-created', account)
				} else {
					const oldAccountData = this.account
					const account = await this.mainStore.updateAccount({
						...data,
						accountId: this.account.id,
					})
					if (this.useOauth) {
						this.loadingMessage = t('yoomail', 'Awaiting user consent')
						try {
							if (this.isGoogleAccount) {
								this.feedback = t('yoomail', 'Account updated. Please follow the pop-up instructions to reconnect your Google account')
								await getUserConsent(this.googleOauthUrl
									.replace('_state_', await generateOauthState(account.id))
									.replace('_email_', encodeURIComponent(account.emailAddress)))
							} else {
								this.feedback = t('yoomail', 'Account updated. Please follow the pop-up instructions to reconnect your Microsoft account')
								await getUserConsent(this.microsoftOauthUrl
									.replace('_state_', await generateOauthState(account.id))
									.replace('_email_', encodeURIComponent(account.emailAddress)))
							}
						} catch (e) {
							// Undo changes
							await this.mainStore.updateAccount({
								...oldAccountData,
								accountId: oldAccountData.id,
							})
							logger.info(`Account ${account.id} update undone`)
							throw e
						}
						this.clearFeedback()
					}
					this.feedback = t('yoomail', 'Account updated')
				}
			} catch (error) {
				logger.error('could not save account details', { error })

				if (error.data?.error === 'CONNECTION_ERROR') {
					if (error.data.service === 'IMAP') {
						this.feedback = t('yoomail', 'IMAP server is not reachable')
					} else if (error.data.service === 'SMTP') {
						this.feedback = t('yoomail', 'SMTP server is not reachable')
					}
				} else if (error.data?.error === 'AUTHENTICATION_WRONG_PASSWORD') {
					if (error.data.service === 'IMAP') {
						this.feedback = t('yoomail', 'IMAP username or password is wrong')
					} else if (error.data.service === 'SMTP') {
						this.feedback = t('yoomail', 'SMTP username or password is wrong')
					}
				} else if (error.data?.error === 'AUTHENTICATION_DENIED') {
					if (error.data.service === 'IMAP') {
						this.feedback = t('yoomail', 'IMAP server denied authentication')
					} else if (error.data.service === 'SMTP') {
						this.feedback = t('yoomail', 'SMTP server denied authentication')
					}
				} else if (error.data?.error === 'AUTHENTICATION') {
					if (error.data.service === 'IMAP') {
						this.feedback = t('yoomail', 'IMAP authentication error')
					} else if (error.data.service === 'SMTP') {
						this.feedback = t('yoomail', 'SMTP authentication error')
					}
				} else {
					if (error.data?.service === 'IMAP') {
						this.feedback = t('yoomail', 'IMAP connection failed')
					} else if (error.data?.service === 'SMTP') {
						this.feedback = t('yoomail', 'SMTP connection failed')
					} else if (error.message === CONSENT_ABORTED) {
						this.feedback = t('yoomail', 'Authorization pop-up closed')
					} else if (error.response?.status === 429) {
						this.feedback = t('yoomail', 'Configuration discovery temporarily not available. Please try again later.')
					} else {
						this.feedback = t('yoomail', 'There was an error while setting up your account')
					}
				}
			} finally {
				this.loading = false
				this.loadingMessage = undefined
			}
		},

		isValidEmail(email) {
			const regExpEmail = /^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|(".+"))@(localhost|((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,})))$/
			return regExpEmail.test(email)
		},
	},
}
</script>

<style lang="scss" scoped>
:deep(.tabs-component-tabs) {
	display: flex;
}

:deep(.tabs-component-tab) {
	flex-grow: 1;
	text-align: center;
	color: var(--color-text-lighter);
	margin-bottom: calc(var(--default-grid-baseline) * 2 + var(--default-grid-baseline) / 2);
}

:deep(.tabs-component-tab.is-active) {
	border-bottom: var(--border-width-input) solid black;
	font-weight: bold;
}

:deep(.input-field) {
	margin: calc(var(--default-grid-baseline) * 3) 0;
}

.tabs-component-panels {
	padding-top: calc(var(--default-grid-baseline) * 5);
}

.tabs-component-panels label {
	text-align: start;
	width: 100%;
	display: inline-block;
}

.tabs-component-panels input,
.tabs-component-panels select {
	margin-bottom: calc(var(--default-grid-baseline) * 2);
}
</style>

<style scoped>
h4 {
	font-size: 17px;
	text-align: start;
}

.flex-row {
	display: flex;
}

input.primary {
	color: var(--color-main-background);
}

input[type='radio'] {
	display: none;
}

input[type='radio'][disabled] + label {
	cursor: default;
	opacity: 0.5;
}

.account-form__label--required:after {
	content:" *";
}

.account-form__heading--required:after {
	content:" *";
}

.account-form__submit-buttons {
	display: flex;
	justify-content: center;
	margin-top: var(--default-grid-baseline);
}

.account-form__submit-button {
	display: flex;
	align-items: center;
}

.account-form--feedback {
	color: var(--color-text-maxcontrast);
	margin-top: var(--default-grid-baseline);
	text-align: center;
}

.account-form--error {
	text-align: start;
	font-size: 14px;
}

#account-form {
	z-index: 1001;
	width: 300px;
	top: 15%;
	padding-bottom: calc(var(--default-grid-baseline) * 12);
	margin: 0 auto;
	padding-top: calc(var(--default-grid-baseline) * 7);
}

#account-form input {
	width: 100%;
	box-sizing: border-box;
}
</style>
