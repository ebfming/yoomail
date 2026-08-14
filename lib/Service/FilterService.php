<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\YooMail\Service;

use Horde\ManageSieve\Exception as ManageSieveException;
use JsonException;
use OCA\YooMail\Db\MailAccount;
use OCA\YooMail\Exception\ClientException;
use OCA\YooMail\Exception\CouldNotConnectException;
use OCA\YooMail\Exception\FilterParserException;
use OCA\YooMail\Exception\OutOfOfficeParserException;
use OCA\YooMail\Service\MailFilter\FilterBuilder;
use OCA\YooMail\Service\MailFilter\FilterParser;
use OCA\YooMail\Service\MailFilter\FilterParserResult;
use OCA\YooMail\Service\OutOfOffice\OutOfOfficeParser;
use OCA\YooMail\Service\OutOfOffice\OutOfOfficeState;
use Psr\Log\LoggerInterface;

class FilterService {

	public function __construct(
		private AllowedRecipientsService $allowedRecipientsService,
		private OutOfOfficeParser $outOfOfficeParser,
		private FilterParser $filterParser,
		private FilterBuilder $filterBuilder,
		private SieveService $sieveService,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @throws ClientException
	 * @throws ManageSieveException
	 * @throws CouldNotConnectException
	 * @throws FilterParserException
	 */
	public function parse(MailAccount $account): FilterParserResult {
		$script = $this->sieveService->getActiveScript($account->getUserId(), $account->getId());
		return $this->filterParser->parseFilterState($script->getScript());
	}

	/**
	 * @throws CouldNotConnectException
	 * @throws JsonException
	 * @throws ClientException
	 * @throws OutOfOfficeParserException
	 * @throws ManageSieveException
	 * @throws FilterParserException
	 */
	public function update(MailAccount $account, array $filters): void {
		$script = $this->sieveService->getActiveScript($account->getUserId(), $account->getId());

		$oooResult = $this->outOfOfficeParser->parseOutOfOfficeState($script->getScript());
		$filterResult = $this->filterParser->parseFilterState($oooResult->getUntouchedSieveScript());

		$newScript = $this->filterBuilder->buildSieveScript(
			$filters,
			$filterResult->getUntouchedSieveScript()
		);

		$oooState = $oooResult->getState();

		if ($oooState instanceof OutOfOfficeState) {
			$newScript = $this->outOfOfficeParser->buildSieveScript(
				$oooState,
				$newScript,
				$this->allowedRecipientsService->get($account),
			);
		}

		try {
			$this->sieveService->updateActiveScript($account->getUserId(), $account->getId(), $newScript);
		} catch (ManageSieveException $e) {
			$this->logger->error('Failed to save sieve script: ' . $e->getMessage(), [
				'exception' => $e,
				'script' => $newScript,
			]);
			throw $e;
		}
	}
}
