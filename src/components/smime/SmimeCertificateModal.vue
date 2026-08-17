<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcModal :name="showImportScreen ? t('yoomail', 'Import S/MIME certificate') : t('yoomail', 'S/MIME certificates')" @close="$emit('close')">
		<div class="certificate-modal">
			<div v-if="!showImportScreen" class="certificate-modal__list">
				<h2>{{ t('yoomail', 'S/MIME certificates') }}</h2>

				<table class="certificate-modal__list__table">
					<thead>
						<tr>
							<th>{{ t('yoomail', 'Certificate name') }}</th>
							<th>{{ t('yoomail', 'E-mail address') }}</th>
							<th>{{ t('yoomail', 'Valid until') }}</th>
							<th />
						</tr>
					</thead>
					<tbody>
						<tr v-for="certificate in certificates" :key="certificate.id">
							<td :title="certificate.info.commonName">
								{{ certificate.info.commonName }}
							</td>
							<td :title="certificate.info.emailAddress">
								{{ certificate.info.emailAddress }}
							</td>
							<td :title="moment.unix(certificate.info.notAfter).format('LL')">
								{{ moment.unix(certificate.info.notAfter).format('LL') }}
							</td>
							<td>
								<NcButton
									variant="tertiary-no-background"
									:aria-label="t('yoomail', 'Delete certificate')"
									@click="deleteCertificate(certificate.id)">
									<template #icon>
										<DeleteIcon
											:title="t('yoomail', 'Delete certificate')"
											:size="20" />
									</template>
								</NcButton>
							</td>
						</tr>
					</tbody>
				</table>
				<NcEmptyContent
					v-if="certificates.length === 0"
					class="certificate__empty"
					:name="t('yoomail', 'No certificate imported yet')" />
				<div class="certificate-modal__list__actions">
					<NcButton
						variant="primary"
						:aria-label="t('yoomail', 'Import certificate')"
						@click="showImportScreen = true">
						{{ t('yoomail', 'Import certificate') }}
					</NcButton>
				</div>
			</div>
			<form
				v-else
				class="certificate-modal__import"
				@submit.prevent="uploadCertificate">
				<h2>{{ t('yoomail', 'Import S/MIME certificate') }}</h2>

				<fieldset class="certificate-modal__import__type">
					<div>
						<input
							id="certificate-type-pkcs12"
							v-model="certificateType"
							name="certificate-type"
							type="radio"
							:value="TYPE_PKCS12">
						<label for="certificate-type-pkcs12">
							{{ t('yoomail', 'PKCS #12 Certificate') }}
						</label>
					</div>

					<div>
						<input
							id="certificate-type-pem"
							v-model="certificateType"
							name="certificate-type"
							type="radio"
							:value="TYPE_PEM">
						<label for="certificate-type-pem">
							{{ t('yoomail', 'PEM Certificate') }}
						</label>
					</div>
				</fieldset>

				<fieldset>
					<label for="certificate">{{ t('yoomail', 'Certificate') }}</label>
					<input
						id="certificate"
						ref="certificate"
						type="file"
						accept=".p12,.crt,.pem"
						required
						@change="certificate = $event.target.files[0]">
				</fieldset>

				<fieldset v-if="certificateType === TYPE_PEM">
					<label for="private-key">{{ t('yoomail', 'Private key (optional)') }}</label>
					<input
						id="private-key"
						ref="privateKey"
						type="file"
						accept=".key,.pem"
						@change="privateKey = $event.target.files[0]">
				</fieldset>

				<fieldset v-if="certificateType === TYPE_PKCS12">
					<label for="password">{{ t('yoomail', 'Password') }}</label>
					<NcPasswordField v-model="password" :label="t('yoomail', 'Password')" />
				</fieldset>

				<div class="certificate-modal__import__hints">
					<p v-if="certificateType === TYPE_PEM">
						{{ t('yoomail', 'The private key is only required if you intend to send signed and encrypted emails using this certificate.') }}
					</p>
				</div>

				<div class="certificate-modal__import__actions">
					<NcButton
						variant="tertiary-no-background"
						:aria-label="t('yoomail', 'Back')"
						@click="resetImportForm">
						{{ t('yoomail', 'Back') }}
					</NcButton>
					<NcButton
						variant="primary"
						:aria-label="t('yoomail', 'Submit')"
						type="submit"
						:disabled="loading || !inputFormIsValid">
						{{ t('yoomail', 'Submit') }}
					</NcButton>
				</div>
			</form>
		</div>
	</NcModal>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import moment from '@nextcloud/moment'
import { NcButton, NcEmptyContent, NcModal, NcPasswordField } from '@nextcloud/vue'
import { mapState, mapStores } from 'pinia'
import DeleteIcon from 'vue-material-design-icons/TrashCanOutline.vue'
import logger from '../../logger.js'
import useMainStore from '../../store/mainStore.js'
import { convertPkcs12ToPem, InvalidPkcs12CertificateError } from '../../util/pkcs12.js'

const TYPE_PKCS12 = 'pkcs12'
const TYPE_PEM = 'pem'

export default {
	name: 'SmimeCertificateModal',
	components: {
		NcModal,
		NcButton,
		NcPasswordField,
		NcEmptyContent,
		DeleteIcon,
	},

	data() {
		return {
			TYPE_PKCS12,
			TYPE_PEM,

			moment,
			showImportScreen: false,
			loading: false,
			certificateType: TYPE_PKCS12,
			certificate: undefined,
			privateKey: undefined,
			password: '',
		}
	},

	computed: {
		...mapStores(useMainStore),
		...mapState(useMainStore, {
			certificates: 'getSmimeCertificates',
		}),

		inputFormIsValid() {
			return !!this.certificate
		},
	},

	async mounted() {
		// Refresh S/MIME certificates for good measure
		await this.mainStore.fetchSmimeCertificates()
	},

	methods: {
		async deleteCertificate(id) {
			await this.mainStore.deleteSmimeCertificate(id)
		},

		async uploadCertificate() {
			let certificate = this.$refs.certificate.files[0]
			let privateKey
			if (this.certificateType === TYPE_PKCS12) {
				try {
					const result = convertPkcs12ToPem(await certificate.arrayBuffer(), this.password)
					certificate = new Blob([result.certificate])
					privateKey = new Blob([result.privateKey])
				} catch (error) {
					if (error.name === InvalidPkcs12CertificateError.name) {
						logger.error('PKCS #12 certificate contains multiple certs or keys', { error })
						showError(t('yoomail', 'The provided PKCS #12 certificate must contain at least one certificate and exactly one private key.'))
					} else {
						logger.debug('Is probably not a PKCS #12 certificate or the password is wrong', { error })
						showError(t('yoomail', 'Failed to import the certificate. Please check the password.'))
					}

					return
				}
			} else if (this.certificateType === TYPE_PEM) {
				privateKey = this.$refs.privateKey.files[0]
			} else {
				return
			}

			this.loading = true
			try {
				await this.mainStore.createSmimeCertificate({
					certificate,
					privateKey,
				})
				showSuccess(t('yoomail', 'Certificate imported successfully'))
				this.resetImportForm()
			} catch (error) {
				logger.error(
					`Failed to import a S/MIME certificate: ${error.response?.data?.data}`,
					{ error },
				)
				if (privateKey) {
					showError(t('yoomail', 'Failed to import the certificate. Please make sure that the private key matches the certificate and is not protected by a passphrase.'))
				} else {
					showError(t('yoomail', 'Failed to import the certificate'))
				}
			} finally {
				this.loading = false
			}
		},

		resetImportForm() {
			this.certificateType = TYPE_PKCS12
			this.showImportScreen = false
			this.certificate = undefined
			this.privateKey = undefined
			this.password = ''
		},
	},
}
</script>

<style lang="scss" scoped>
.empty-content{
	height: 100%;
	display: flex;
}

.certificate-modal {
	padding: calc(var(--default-grid-baseline) * 5);

	&__list {
		table {
			table-layout: fixed;
			width: 100%;

			th {
				color: var(--color-text-maxcontrast);
			}

			th, td {
				padding: calc(var(--default-grid-baseline) * 0.5);
				text-overflow: ellipsis;
				white-space: nowrap;
				overflow: hidden;
				flex: 5 1 0px;
			}

			th:last-child, td:last-child {
				flex: 1 1 0px;
			}

			span {
				text-overflow: ellipsis;
			}

			tr {
				display: flex;
				flex-direction: row;
				align-items: center;
			}

			// Disable default hover style
			tr:hover {
				background-color: unset;
			}
		}

		&__actions {
			margin: calc(var(--default-grid-baseline) * 3);
			float: inline-end;
		}
	}

	&__import {
		display: flex;
		flex-direction: column;
		gap: calc(var(--default-grid-baseline) * 2);

		input[type=file] {
			display: flex;
			width: 100%;
		}

		&__type {
			display: flex;
			gap: 0 calc(var(--default-grid-baseline) * 5);
			flex-wrap: wrap;

			> div {
				display: flex;
				gap: var(--default-grid-baseline);
				align-items: center;
			}
		}

		&__hints {
			color: var(--color-text-maxcontrast);
		}

		&__actions {
			display: flex;
			justify-content: space-between;
			gap: calc(var(--default-grid-baseline) * 4);
		}
	}
}
</style>
