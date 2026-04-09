<?php

namespace App\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Product;
use Doctrine\ORM\QueryBuilder;


class AvailableProductFilter extends AbstractFilter
{
    protected function filterProperty(string $property, $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, Operation $operation = null, array $context = []): void
    {
        // On ne déclenche ce filtre que si on reçoit le paramètre "startDate"
        if ($property !== 'startDate') {
            return;
        }

        // On récupère les deux dates
        $startDate = $value;
        $endDate = $context['filters']['endDate'] ?? null;

        // Si l'une des dates manque, on ne filtre pas
        if (!$startDate || !$endDate) {
            return;
        }

        try {
            $start = new \DateTime($startDate);
            $end = new \DateTime($endDate);
        } catch (\Exception $e) {
            // Si le format de la date est invalide, on annule
            return;
        }

        // Le nom de base de la table Product dans la requête principale (souvent "o" ou "p")
        $alias = $queryBuilder->getRootAliases()[0];
        $em = $queryBuilder->getEntityManager();

        // 1. On crée une SOUS-REQUÊTE pour trouver les ID des produits qui SONT DÉJÀ RÉSERVÉS à ces dates
        // La règle du chevauchement : (Res.startDate < Demande.endDate) ET (Res.endDate > Demande.startDate)
        $subQueryBuilder = $em->createQueryBuilder()
            ->select('p_sub.id')
            ->from(Product::class, 'p_sub')
            ->join('p_sub.reservation', 'r_sub')
            ->where('r_sub.startDate < :req_endDate')
            ->andWhere('r_sub.endDate > :req_startDate');

        // 2. On dit à la requête principale : "Garde les produits dont l'ID n'est PAS dans la sous-requête"
        $queryBuilder
            ->andWhere($queryBuilder->expr()->notIn($alias . '.id', $subQueryBuilder->getDQL()))
            ->setParameter('req_startDate', $start)
            ->setParameter('req_endDate', $end);
    }

    // Cette fonction sert juste à documenter le filtre pour Swagger (l'interface visuelle de test API)
    public function getDescription(string $resourceClass): array
    {
        return [
            'startDate' => [
                'property' => 'startDate',
                'type' => 'string',
                'required' => false,
                'description' => 'Date d\'arrivée (YYYY-MM-DD)',
            ],
            'endDate' => [
                'property' => 'endDate',
                'type' => 'string',
                'required' => false,
                'description' => 'Date de départ (YYYY-MM-DD)',
            ]
        ];
    }
}
