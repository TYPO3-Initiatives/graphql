<?php
declare(strict_types = 1);

namespace TYPO3\CMS\Core\GraphQL\Database;

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

use GraphQL\Type\Definition\ResolveInfo;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\GraphQL\ResolverHandlerInterface;
use TYPO3\CMS\Core\GraphQL\Type\FilterExpressionType;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Webmozart\Assert\Assert;

/**
 * @internal
 */
class EntityFilterHandler implements ResolverHandlerInterface
{
    /**
     * @var string
     */
    const ARGUMENT_NAME = 'filter';

    /**
     * @inheritdoc
     */
    public function beforeResolve($source, array $arguments, array $context, ResolveInfo $info): void
    {
        Assert::keyExists($context, 'builder');
        Assert::isInstanceOf($context['builder'], QueryBuilder::class);

        $builder = $context['builder'];
        $expression = $arguments[self::ARGUMENT_NAME] ?? null;
        $processor = GeneralUtility::makeInstance(FilterExpressionProcessor::class, $info, $expression, $builder);

        $condition = $processor->process();

        if ($condition !== null) {
            $builder->andWhere($condition);
        }
    }

    /**
     * @inheritdoc
     */
    public function afterResolve($source, array $arguments, array $context, ResolveInfo $info, ?array $data): ?array
    {
        return $data;
    }

    /**
     * @inheritdoc
     */
    public function getArguments(): array
    {
        return [
            [
                'name' => self::ARGUMENT_NAME,
                'type' => FilterExpressionType::instance(),
            ],
        ];
    }
}