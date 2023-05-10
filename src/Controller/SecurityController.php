<?php

namespace App\Controller;

use App\Exception\CustomUserMessageAuthenticationException;
use App\Tests\Mock\BillingClientMock;
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

return [
    'token' => $token
];
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
        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', ['last_username' => $lastUsername, 'error' => $error]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route(path: '/profile', name: 'app_profile_show')]
    #[IsGranted('ROLE_USER')]
    public function profile(#[CurrentUser] ?User $user): Response
    {
        $user = $this->billingClient->getCurrentUser($user->getApiToken());

        return $this->render('profile/show.html.twig', [
            'user' => $user,
        ]);
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
                if ($e instanceof CustomUserMessageAuthenticationException) {
                    $error = $e->getMessage();
                } else {
                    $error = 'Сервис временно недоступен. Попробуйте авторизоваться позднее';
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
