<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcSelect
		input-label="flag"
		:model-value="flag"
		:required="true"
		:label-outside="true"
		:options="flags"
		:clearable="false"
		@input="updateAction({ flag: $event })">
		<template #selected-option="{ label }">
			{{ getLabelForFlag(label) }}
		</template>
		<template #option="{ label }">
			{{ getLabelForFlag(label) }}
		</template>
	</NcSelect>
</template>

<script>
import { NcSelect } from '@nextcloud/vue'
import { MailFilterSystemFlag } from '../../models/mailFilter.ts'

export default {
	name: 'ActionAddSystemFlag',
	components: {
		NcSelect,
	},

	props: {
		action: {
			type: Object,
			required: true,
		},

		account: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			flags: [
				MailFilterSystemFlag.Answered,
				MailFilterSystemFlag.Deleted,
				MailFilterSystemFlag.Draft,
				MailFilterSystemFlag.Flagged,
				MailFilterSystemFlag.Seen,
			],
		}
	},

	computed: {
		flag() {
			return this.action.flag ?? ''
		},
	},

	methods: {
		updateAction(value) {
			this.$emit('update-action', value)
		},

		getLabelForFlag(field) {
			switch (field) {
				case MailFilterSystemFlag.Answered:
					return t('yoomail', 'Answered')
				case MailFilterSystemFlag.Deleted:
					return t('yoomail', 'Deleted')
				case MailFilterSystemFlag.Draft:
					return t('yoomail', 'Draft')
				case MailFilterSystemFlag.Flagged:
					return t('yoomail', 'Flagged')
				case MailFilterSystemFlag.Seen:
					return t('yoomail', 'Seen')
			}
			return field
		},
	},
}
</script>
