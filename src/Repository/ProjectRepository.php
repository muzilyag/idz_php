<?php

namespace App\Repository;

use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    public function searchProjects(string $searchName, string $searchPlace): array
    {
        $qb = $this->createQueryBuilder('p');

        if (!empty($searchName)) {
            $qb->andWhere('p.projectName LIKE :name')->setParameter('name', '%' . $searchName . '%');
        }

        if (!empty($searchPlace)) {
            $qb->andWhere('p.place LIKE :place')->setParameter('place', '%' . $searchPlace . '%');
        }

        return $qb->getQuery()->getResult();
    }
}
