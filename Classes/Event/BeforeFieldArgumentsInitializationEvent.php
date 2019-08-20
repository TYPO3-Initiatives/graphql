<?php
declare(strict_types = 1);

namespace TYPO3\CMS\GraphQL\Event;

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

use GraphQL\Type\Definition\Type;
use TYPO3\CMS\Core\Configuration\MetaModel\ElementInterface;

/**
 * @internal
 */
final class BeforeFieldArgumentsInitializationEvent
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var ElementInterface
     */
    private $element;

    /**
     * @var Type
     */
    private $type;

    /**
     * @var array
     */
    private $arguments;

    public function __construct(string $name, ElementInterface $element, Type $type)
    {
        $this->name = $name;
        $this->element = $element;
        $this->type = $type;
        $this->arguments = [];
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getElement(): ElementInterface
    {
        return $this->element;
    }

    public function getType(): Type
    {
        return $this->type;
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function addArgument(string $name, Type $type): void
    {
        $this->arguments[] = [
            'name' => $name,
            'type' => $type,
        ];
    }
}
