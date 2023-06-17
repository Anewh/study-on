<?php

namespace App\Security;

use App\Exception\BillingUnavailableException;
use App\Exception\CustomUserMessageAuthenticationException;
use App\Service\BillingClient;
use DateTime;
use JsonException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class UserProvider implements UserProviderInterface, PasswordUpgraderInterface
{
    private BillingClient $billingClient;

    private const SERVICE_UNAVAILABLE = 'Сервис временно недоступен. Попробуйте авторизоваться позже';

    public function __construct(BillingClient $billingClient)
    {
        $this->billingClient = $billingClient;
    }
    /**
     * Symfony calls this method if you use features like switch_user
     * or remember_me.
     *
     * If you're not using these features, you do not need to implement
     * this method.
     *
     * @throws UserNotFoundException if the user is not found
     */
    public function loadUserByIdentifier($identifier): UserInterface
    {
        try {
            $userDto = $this->billingClient->getCurrentUser($identifier);
        } catch (BillingUnavailableException $e) {
            throw new CustomUserMessageAuthenticationException(self::SERVICE_UNAVAILABLE);
        }
        return User::fromDto($userDto)->setApiToken($identifier);
    }

    /**
     * @deprecated since Symfony 5.3, loadUserByIdentifier() is used instead
     */
    public function loadUserByUsername($username): UserInterface
    {
        return $this->loadUserByIdentifier($username);
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', get_class($user)));
        }

        try {
            $tokenPayload = explode(".", $user->getApiToken())[1];
            $tokenPayload = json_decode(base64_decode($tokenPayload), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new CustomUserMessageAuthenticationException(self::SERVICE_UNAVAILABLE);
        }
        
        $tokenExpiredTime = (new DateTime())->setTimestamp($tokenPayload['exp']);

        if ($tokenExpiredTime <= new DateTime() && $user->getRefreshToken()!== null) {
            try {
                $tokens = $this->billingClient->refreshToken($user->getRefreshToken());
            } catch (BillingUnavailableException|JsonException $e) {
                throw new CustomUserMessageAuthenticationException(self::SERVICE_UNAVAILABLE);
            }
            $user->setApiToken($tokens['token'])
                ->setRefreshToken($tokens['refresh_token']);
        }

        return $user;
    }

    /**
     * Tells Symfony to use this provider for this User class.
     */
    public function supportsClass(string $class): bool
    {
        return User::class === $class || is_subclass_of($class, User::class);
    }

    /**
     * Upgrades the hashed password of a user, typically for using a better hash algorithm.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        // TODO: when hashed passwords are in use, this method should:
        // 1. persist the new password in the user storage
        // 2. update the $user object with $user->setPassword($newHashedPassword);
    }
}
