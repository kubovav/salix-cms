<?php

namespace App\Repository;

use App\Entity\MenuItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MenuItem>
 */
class MenuItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MenuItem::class);
    }

    /**
     * @return MenuItem[] root items (no parent) for a given menu, ordered by position
     */
    public function findRootItemsForMenu(string $menuName): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.parent IS NULL')
            ->andWhere('m.menuName = :name')
            ->setParameter('name', $menuName)
            ->orderBy('m.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return MenuItem[] all items across all menus, ordered for admin listing
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('m')
            ->addSelect('COALESCE(IDENTITY(m.parent), m.id) AS HIDDEN sort_group')
            ->leftJoin('m.parent', 'p')
            ->orderBy('m.menuName', 'ASC')
            ->addOrderBy('sort_group', 'ASC')
            ->addOrderBy('p.id', 'ASC')
            ->addOrderBy('m.position', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
