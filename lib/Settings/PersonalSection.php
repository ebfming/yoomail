<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\YooMail\Settings;

use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

class PersonalSection implements IIconSection {
	public function __construct(
		private IURLGenerator $urlGenerator,
		private IL10N $l10n,
	) {
	}

	#[\Override]
	public function getID(): string {
		return 'yoomail';
	}

	#[\Override]
	public function getName(): string {
		return $this->l10n->t('YooMail');
	}

	#[\Override]
	public function getPriority(): int {
		return 62;
	}

	#[\Override]
	public function getIcon(): string {
		return $this->urlGenerator->imagePath('yoomail', 'yoomail.svg');
	}
}
