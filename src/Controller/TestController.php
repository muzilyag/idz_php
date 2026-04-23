<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TestController extends AbstractController
{
    #[Route('/test', name: 'app_test')]
    public function index(): Response
    {
        $projectData = [
            'name' => 'Изучение Symfony',
            'framework' => 'Symfony 7',
            'status' => 'В процессе'
        ];

        return $this->render('test/index.html.twig', [
            'data' => $projectData,
        ]);
    }
}
