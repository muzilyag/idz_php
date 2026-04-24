<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\UserRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Bundle\SecurityBundle\Security;

class AuthController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $customError = null;
        if ($error) {
            $customError = 'Введён неверный логин или пароль';
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $customError,
        ]);
    }

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager, Security $security): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        if (!$request->isMethod('POST')) {
            return $this->render('security/register.html.twig', [
                'error' => null,
            ]);
        }

        $username = trim((string) $request->request->get('username', ''));
        $password = (string) $request->request->get('password', '');

        if ($username === '' || $password === '') {
            return $this->render('security/register.html.twig', [
                'error' => 'Заполните все поля',
            ]);
        }

        $existing = $entityManager->getRepository(User::class)->findOneBy([
            'username' => $username,
        ]);

        if ($existing) {
            return $this->render('security/register.html.twig', [
                'error' => 'Пользователь уже существует',
            ]);
        }

        $user = new User();
        $user->setUsername($username);
        $user->setPassword($passwordHasher->hashPassword($user, $password));
        $user->setRoles([UserRole::USER->value]);

        $entityManager->persist($user);
        $entityManager->flush();

        $security->login($user);

        return $this->redirectToRoute('app_home');
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
    }
}
