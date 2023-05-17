<?php

namespace App\Controller;

use App\Exception\BillingUnavailableException;
use Exception;
use App\Security\User;
use App\Form\RegisterForm;
use App\Service\BillingClient;
use App\Security\BillingAuthenticator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class SecurityController extends AbstractController
{
    private BillingClient $billingClient;
    public function __construct(BillingClient $billingClient)
    {
        $this->billingClient = $billingClient;
    }

    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_course_index');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', ['last_username' => $lastUsername, 'error' => $error]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
    }

    #[Route(path: '/profile', name: 'app_profile_show')]
    #[IsGranted('ROLE_USER')]
    public function profile(#[CurrentUser] ?User $user): Response
    {
        try {
            $user = $this->billingClient->getCurrentUser($user->getApiToken());

            return $this->render('profile/show.html.twig', [
                'user' => $user,
            ]);
        } catch (BillingUnavailableException | \JsonException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_course_index');
        }
    }

    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserAuthenticatorInterface $userAuthenticator,
        BillingAuthenticator $billingAuthenticator,
        AuthenticationUtils $authenticationUtils
    ): Response {

        if ($this->getUser()) {
            return $this->redirectToRoute('app_profile_show');
        }

        $user = new User();
        $form = $this->createForm(RegisterForm::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $token = $this->billingClient->register([
                    'username' => $form->get('email')->getData(),
                    'password' => $form->get('password')->getData()
                ])['token'];
            } catch (Exception $e) {
                if ($e instanceof  BillingUnavailableException) {
                    $error = 'Сервис временно недоступен. Попробуйте зайти позже';
                } else {
                    $error = $e->getMessage();
                }
                return $this->render('security/register.html.twig', [
                    'registerForm' => $form->createView(),
                    'error' => $error,
                ]);
            }
            $user->setApiToken($token);
            return $userAuthenticator->authenticateUser($user, $billingAuthenticator, $request);
        }
        return $this->render('security/register.html.twig', [
            'registerForm' => $form->createView(),
            'error' => $authenticationUtils->getLastAuthenticationError()
        ]);
    }
}
