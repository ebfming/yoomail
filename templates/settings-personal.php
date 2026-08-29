<?php

/**
 * SPDX-FileCopyrightText: 2026
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
?>
<div id="yoomail-personal-notification-settings" class="section">
	<h2><?php p($l->t('YooMail notifications')); ?></h2>
	<p class="settings-hint">
		<?php p($l->t('These reminders work while any page from this Nextcloud site is open in your browser.')); ?>
	</p>

	<script type="application/json" id="yoomail-personal-notification-settings-data"><?php
		print_unescaped(json_encode($_['notificationData'], JSON_THROW_ON_ERROR));
	?></script>

	<div class="yoomail-settings-group">
		<input type="checkbox" class="checkbox" id="ym-native-new-mail" <?php if (!empty($_['notificationData']['settings']['nativeNewMail'])) { echo 'checked'; } ?>>
		<label class="yoomail-checkbox-label" for="ym-native-new-mail"><?php p($l->t('Use native browser notifications for new mail')); ?></label>
		<p class="settings-hint yoomail-setting-copy"><?php p($l->t('This uses the browser Notification API and does not change Nextcloud global notifications.')); ?></p>
		<div class="yoomail-setting-actions yoomail-setting-copy">
			<button type="button" class="button" id="ym-request-notification-permission"><?php p($l->t('Request browser permission')); ?></button>
		</div>
		<p class="settings-hint yoomail-setting-copy" id="ym-browser-permission-status"></p>
	</div>

	<div class="yoomail-settings-group">
		<input type="checkbox" class="checkbox" id="ym-sound-enabled" <?php if (!empty($_['notificationData']['settings']['soundEnabled'])) { echo 'checked'; } ?>>
		<label class="yoomail-checkbox-label" for="ym-sound-enabled"><?php p($l->t('Enable sound notifications')); ?></label>
		<p class="settings-hint yoomail-setting-copy"><?php p($l->t('Sound playback depends on browser policy and usually requires at least one interaction with this Nextcloud site.')); ?></p>
		<div class="yoomail-sound-grid yoomail-setting-copy">
			<div>
				<input type="checkbox" class="checkbox" id="ym-sound-new-mail" <?php if (!empty($_['notificationData']['settings']['soundNewMail'])) { echo 'checked'; } ?>>
				<label class="yoomail-checkbox-label" for="ym-sound-new-mail"><?php p($l->t('Play a sound for new mail')); ?></label>
			</div>
			<div>
				<input type="checkbox" class="checkbox" id="ym-sound-send-success" <?php if (!empty($_['notificationData']['settings']['soundSendSuccess'])) { echo 'checked'; } ?>>
				<label class="yoomail-checkbox-label" for="ym-sound-send-success"><?php p($l->t('Play a sound when sending succeeds')); ?></label>
			</div>
			<div>
				<input type="checkbox" class="checkbox" id="ym-sound-send-fail" <?php if (!empty($_['notificationData']['settings']['soundSendFail'])) { echo 'checked'; } ?>>
				<label class="yoomail-checkbox-label" for="ym-sound-send-fail"><?php p($l->t('Play a sound when sending fails')); ?></label>
			</div>
		</div>
		<div class="yoomail-setting-actions yoomail-setting-copy">
			<button type="button" class="button" id="ym-test-new-mail-sound"><?php p($l->t('Test new mail sound')); ?></button>
			<button type="button" class="button" id="ym-test-send-success-sound"><?php p($l->t('Test send success sound')); ?></button>
			<button type="button" class="button" id="ym-test-send-fail-sound"><?php p($l->t('Test send fail sound')); ?></button>
		</div>
	</div>

	<div class="yoomail-settings-group">
		<input type="checkbox" class="checkbox" id="ym-toast-enabled" <?php if (!empty($_['notificationData']['settings']['toastEnabled'])) { echo 'checked'; } ?>>
		<label class="yoomail-checkbox-label" for="ym-toast-enabled"><?php p($l->t('Show YooMail pop-up reminders in the bottom-right corner')); ?></label>
		<p class="settings-hint yoomail-setting-copy"><?php p($l->t('These reminders are rendered by YooMail itself and do not affect other Nextcloud apps.')); ?></p>
	</div>

	<div class="yoomail-settings-group">
		<input type="checkbox" class="checkbox" id="ym-top-app-icon-enabled" <?php if (!empty($_['notificationData']['settings']['topAppIconEnabled'])) { echo 'checked'; } ?>>
		<label class="yoomail-checkbox-label" for="ym-top-app-icon-enabled"><?php p($l->t('Show a new mail marker on the YooMail top app icon')); ?></label>
		<p class="settings-hint yoomail-setting-copy"><?php p($l->t('The marker is scoped to this Nextcloud site and clears when you open YooMail.')); ?></p>
	</div>

	<div class="yoomail-setting-actions">
		<button type="button" class="button primary" id="ym-save-notification-settings"><?php p($l->t('Save')); ?></button>
	</div>
</div>
