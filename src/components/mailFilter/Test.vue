<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="condition">
		<div class="condition__value">
			<div class="condition__value__field_operator">
				<NcSelect
					input-label="field"
					class="condition__value__field_operator__column"
					:model-value="test.field"
					:required="true"
					:label-outside="true"
					:options="fields"
					:clearable="false"
					@input="updateTest({ field: $event })">
					<template #selected-option="{ label }">
						{{ getLabelForField(label) }}
					</template>
					<template #option="{ label }">
						{{ getLabelForField(label) }}
					</template>
				</NcSelect>
				<NcSelect
					input-label="operator"
					class="condition__value__field_operator__column"
					:model-value="test.operator"
					:required="true"
					:label-outside="true"
					:options="operators"
					:clearable="false"
					@input="updateTest({ operator: $event })">
					<template #selected-option="{ label }">
						{{ getLabelForOperator(label) }}
					</template>
					<template #option="{ label }">
						{{ getLabelForOperator(label) }}
					</template>
				</NcSelect>
			</div>
			<NcSelect
				v-model="localValues"
				class="condition__value__values"
				input-label="value"
				:multiple="true"
				:wrap="true"
				:keep-open="true"
				:taggable="true"
				:required="true"
				:label-outside="true"
				:placeholder="placeholderText"
				@option:selected="updateTest({ values: localValues })"
				@option:deselected="updateTest({ values: localValues })" />
		</div>
		<div class="condition__delete">
			<NcButton
				aria-label="Delete action"
				variant="tertiary-no-background"
				@click="deleteTest">
				<template #icon>
					<DeleteIcon :size="20" />
				</template>
			</NcButton>
		</div>
	</div>
</template>

<script>
import { NcButton, NcSelect } from '@nextcloud/vue'
import DeleteIcon from 'vue-material-design-icons/TrashCanOutline.vue'
import { MailFilterConditionField, MailFilterConditionOperator } from '../../models/mailFilter.ts'

export default {
	name: 'Test',
	components: {
		NcButton,
		NcSelect,
		DeleteIcon,
	},

	props: {
		test: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			inputValue: '',
			localValues: [],
			fields: [
				MailFilterConditionField.Subject,
				MailFilterConditionField.From,
				MailFilterConditionField.To,
			],

			operators: [
				MailFilterConditionOperator.Is,
				MailFilterConditionOperator.Contains,
				MailFilterConditionOperator.Matches,
			],
		}
	},

	computed: {
		placeholderText() {
			switch (this.test.field) {
				case MailFilterConditionField.Subject:
					return t('yoomail', 'Enter subject')
				case MailFilterConditionField.From:
					return t('yoomail', 'Enter sender')
				case MailFilterConditionField.To:
					return t('yoomail', 'Enter recipient')
			}
			return ''
		},
	},

	mounted() {
		this.localValues = [...this.test.values]
	},

	methods: {
		updateTest(properties) {
			this.$emit('update-test', { ...this.test, ...properties })
		},

		deleteTest() {
			this.$emit('delete-test', this.test)
		},

		getLabelForField(field) {
			switch (field) {
				case MailFilterConditionField.Subject:
					return t('yoomail', 'Subject')
				case MailFilterConditionField.From:
					return t('yoomail', 'Sender')
				case MailFilterConditionField.To:
					return t('yoomail', 'Recipient')
			}
			return field
		},

		getLabelForOperator(field) {
			switch (field) {
				case MailFilterConditionOperator.Is:
					return t('yoomail', 'is exactly')
				case MailFilterConditionOperator.Contains:
					return t('yoomail', 'contains')
				case MailFilterConditionOperator.Matches:
					return t('yoomail', 'matches')
			}
			return field
		},
	},
}
</script>

<style lang="scss" scoped>
.condition {
	display: flex;
	margin-bottom: calc(var(--default-grid-baseline) * 2);
	&__value {
		width: 100%;
		margin-inline-end: var(--default-grid-baseline);
		&__field_operator {
			display: flex;
			gap: var(--default-grid-baseline);
			&__column {
				flex: 1;
			}
		}
		&__values {
			width: 100%;
		}
	}
	&__delete {
		width: 30px;
	}
}
</style>
