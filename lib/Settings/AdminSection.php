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

class AdminSection implements IIconSection {
	public function __construct(
		private IL10N $l10n,
		private IURLGenerator $urlGenerator,
	) {
	}

	#[\Override]
	public function getIcon(): string {
		return $this->urlGenerator->imagePath('yoomail', 'yoomail.svg');
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
		return 61;
	}
}
