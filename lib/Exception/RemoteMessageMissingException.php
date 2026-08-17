<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 TigerSprite Team
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace OCA\YooMail\Exception;

use Throwable;

class RemoteMessageMissingException extends ServiceException {
	public function __construct(string $message = 'Remote message does not exist', int $code = 0, ?Throwable $previous = null) {
		parent::__construct($message, $code, $previous);
	}
}
