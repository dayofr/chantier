<?php

namespace App\Filter;

use ApiPlatform\Doctrine\Common\Filter\OpenApiFilterTrait;
use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\BackwardCompatibleFilterDescriptionTrait;
use ApiPlatform\Metadata\OpenApiParameterFilterInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;

/**
 * ?param=true → propriété nulle, ?param=false → non nulle.
 * Remplace ExistsFilter, qui exige un ManagerRegistry et logue une alerte quand il est déclaré en ligne.
 */
final class IsNullFilter implements FilterInterface, OpenApiParameterFilterInterface
{
    use BackwardCompatibleFilterDescriptionTrait;
    use OpenApiFilterTrait;

    public function apply(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $parameter = $context['parameter'];
        $value = filter_var($parameter->getValue(), \FILTER_VALIDATE_BOOL, \FILTER_NULL_ON_FAILURE);
        if (null === $value || null === $property = $parameter->getProperty()) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $queryBuilder->andWhere(\sprintf('%s.%s IS %s', $alias, $property, $value ? 'NULL' : 'NOT NULL'));
    }
}
