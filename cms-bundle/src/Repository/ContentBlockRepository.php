<?php

declare(strict_types=1);

namespace Salix\Cms\Repository;

use Salix\Cms\Entity\ContentBlock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ContentBlock>
 */
class ContentBlockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContentBlock::class);
    }
}
