<?php
declare(strict_types = 1);

namespace TYPO3\CMS\GraphQL;

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

use Hoa\Visitor\Element;
use Hoa\Visitor\Visit;

/**
 * @internal
 */
class ExpressionNodeVisitor implements Visit
{
    /**
     * @var array
     */
    protected $map;

    public function __construct(array $map)
    {
        $this->map = $map;
    }

    public function visit(Element $element, &$handle = null, $eldnah = null)
    {
        $result = null;

        if (isset($this->map[$element->getId()])) {
            $result = call_user_func($this->map[$element->getId()], $element);
        }

        if ($result !== false) {
            foreach ($element->getChildren() as $child) {
                $child->accept($this);
            }
        }
    }
}