<?php
declare(strict_types = 1);

namespace TYPO3\CMS\GraphQL\Exception;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use GraphQL\Error\Error;
use Throwable;
use TYPO3\CMS\Core\Exception;

/**
 * @api
 */
class ExecutionException extends Exception
{
    /**
     * @var array
     */
    protected $errors;

    public function __construct(string $message = "", int $code = 0, Throwable $previous = null, Error ...$errors)
    {
        parent::__construct($message, $code, $previous);

        $this->errors = $errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}