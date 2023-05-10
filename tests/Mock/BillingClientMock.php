<?php

namespace App\Tests\Mock;

use App\Dto\UserDto;
use App\Exception\BillingUnavailableException;
use App\Exception\CustomUserMessageAuthenticationException;
use App\Security\User;
use App\Service\BillingClient;
use JMS\Serializer\Serializer;
use JMS\Serializer\SerializerBuilder;

class BillingClientMock extends BillingClient
{
    const USER = [
        'username' => 'user@sexample.com',
        'password' => 'password',
        'roles' => ['ROLE_USER'],
        'balance' => 1000.0,
    ];
    const ADMIN = [
        'username' => 'admin@sexample.com',
        'password' => 'password',
        'roles' => ['ROLE_USER', 'ROLE_SUPER_ADMIN'],
        'balance' => 1000.0,
    ];

    private Serializer $serializer;

    public function __construct()
    {
        $this->serializer = SerializerBuilder::create()->build();
    }

    public function auth($credentials): array
    {
        $credentials = json_decode($credentials, true, 512, JSON_THROW_ON_ERROR);
        $username = $credentials['username'];
        $password = $credentials['password'];
        if ($username === self::USER['username'] && $password === self::USER['password']) {
            $token = $this->generateToken(self::USER['roles'], $username);
        } elseif ($username === self::ADMIN['username'] && $password === self::ADMIN['password']) {
            $token = $this->generateToken(self::ADMIN['roles'], $username);
        } else {
            throw new BillingUnavailableException('Неправильные логин или пароль');
        }
        return [
            'token' => $token
        ];
    }

    public function register($credentials): array
    {
        $userDto = $this->serializer->deserialize($credentials, UserDto::class, 'json');
        $username = $userDto->username;
        if ($username === self::ADMIN['username'] || $username === self::USER['username']) {
            throw new CustomUserMessageAuthenticationException('Email уже используется');
        }
        $token = $this->generateToken(self::USER['roles'], $username);
        return [
            "token" => $token,
            "roles" => [0 => "ROLE_USER"]
        ];
    }

    public function getCurrentUser(string $token): UserDto
    {
        $parts = explode('.', $token);
        $payload = json_decode(base64_decode($parts[1]), true, 512, JSON_THROW_ON_ERROR);
        $email = $payload['username'];

        $userDto = new UserDto();
        $userDto->setUsername($email);
        if ($email === self::ADMIN['username']) {
            $userDto->setRoles(self::ADMIN['roles']);
            $userDto->setBalance(self::ADMIN['balance']);
        } else if ($email === self::USER['username']) {
            $userDto->setRoles(self::USER['roles']);
            $userDto->setBalance(self::USER['balance']);
        } else {
            throw new BillingUnavailableException('Ошибка авторизации');
        }
        return $userDto;
    }

    private function generateToken(array $roles, string $username): string
    {
        return 'header.' . base64_encode(json_encode([
            'exp' => (new \DateTime('+ 1 hour'))->getTimestamp(),
            'username' => $username,
            'roles' => $roles,
        ], JSON_THROW_ON_ERROR)) . '.trailer';
    }
}