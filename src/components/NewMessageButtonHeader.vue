<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="header">
		<ButtonVue
			:aria-label="t('yoomail', 'New message')"
			type="secondary"
			button-id="mail_new_message"
			:wide="true"
			@click="onNewMessage">
			<template #icon>
				<IconAdd :size="20" />
			</template>
			{{ t('yoomail', 'New message') }}
		</ButtonVue>
		<ButtonVue
			v-if="showRefresh && currentMailbox"
			:aria-label="t('yoomail', 'Refresh')"
			type="tertiary-no-background"
			class="refresh__button"
			:disabled="refreshing"
			@click="refreshMailbox">
			<template #icon>
				<IconRefresh
					v-if="!refreshing"
					:size="20" />
				<IconLoading
					v-if="refreshing"
					:size="20" />
			</template>
		</ButtonVue>
	</div>
</template>

<script>
import { showInfo } from '@nextcloud/dialogs'
import { NcButton as ButtonVue } from '@nextcloud/vue'
import { mapStores } from 'pinia'
import IconLoading from '@nextcloud/vue/components/NcLoadingIcon'
import IconAdd from 'vue-material-design-icons/Plus.vue'
import IconRefresh from 'vue-material-design-icons/Refresh.vue'
import logger from '../logger.js'
import { PRIORITY_INBOX_ID } from '../store/constants.js'
import useMainStore from '../store/mainStore.js'

export default {
	name: 'NewMessageButtonHeader',
	components: {
		ButtonVue,
		IconAdd,
		IconRefresh,
		IconLoading,
	},

	props: {
		showRefresh: {
			default: true,
		},
	},

	data() {
		return {
			refreshing: false,
		}
	},

	computed: {
		...mapStores(useMainStore),
		currentMailbox() {
			if (this.$route.name === 'message' || this.$route.name === 'mailbox') {
				return this.mainStore.getMailbox(this.$route.params.mailboxId)
			}
			return undefined
		},

		account() {
			return this.currentMailbox && this.mainStore.getAccount(this.currentMailbox.accountId)
		},
	},

	methods: {
		async refreshMailbox() {
			if (this.refreshing === true || !this.currentMailbox) {
				logger.debug('already sync\'ing mailbox.. aborting')
				return
			}
			this.refreshing = true
			try {
				const currentMailbox = this.currentMailbox
				if (currentMailbox.isUnified) {
					const accounts = this.mainStore.getAccounts.filter((account) => !account.isUnified)
					await Promise.all(accounts.map((account) => this.mainStore.syncMailboxesForAccount(account)))
					await this.mainStore.syncInboxes({ repairVanished: true })
					logger.debug('Unified mailbox is syncing')
					return
				}

				if (!this.account || this.account.isUnified) {
					logger.warn('could not determine the IMAP account for the current mailbox', { mailbox: currentMailbox })
					return
				}

				const mailboxId = currentMailbox.databaseId
				await this.mainStore.syncMailboxesForAccount(this.account)
				const synchronizedMailbox = this.mainStore.getMailbox(mailboxId)
				if (!synchronizedMailbox) {
					showInfo(t('yoomail', 'This folder no longer exists and was removed from the list.'))
					await this.$router.replace({
						name: 'mailbox',
						params: { mailboxId: PRIORITY_INBOX_ID },
					})
					return
				}
				await this.mainStore.syncEnvelopes({
					mailboxId: synchronizedMailbox.databaseId,
					repairVanished: true,
				})
				logger.debug('Current folder is sync\'ing ')
			} catch (error) {
				logger.error('could not sync current folder', { error })
			} finally {
				this.refreshing = false
			}
		},

		async onNewMessage() {
			await this.mainStore.startComposerSession({
				isBlankMessage: true,
			})
		},
	},
}
</script>

<style lang="scss" scoped>
.header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: var(--default-grid-baseline);
}

.refresh__button {
	background-color: transparent;
}
</style>
