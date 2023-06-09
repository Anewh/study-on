<?php

namespace App\Security;

use App\Dto\UserDto;
use Symfony\Component\Security\Core\User\UserInterface;

class User implements UserInterface
{
    private $email;
    private $roles = [];
    private $apiToken;

    private ?string $refreshToken = null;

    public static function fromDto(UserDto $userDto): User
    {
        return (new self())
            ->setEmail($userDto->getUsername())
            ->setRoles($userDto->getRoles());
    }
    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getRefreshToken(): ?string
    {
        return $this->refreshToken;
    }

    public function setRefreshToken(string $refreshToken): self
    {
        $this->refreshToken = $refreshToken;

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials()
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function getApiToken(): string
    {
        return $this->apiToken;
    }

    public function setApiToken(string $apiToken): self
    {
        $this->apiToken = $apiToken;

        return $this;
    }


    public static function jwtDecode(string $token): array
    {
        $parts = explode('.', $token);
        $payload = json_decode(base64_decode($parts[1]), true, 512, JSON_THROW_ON_ERROR);
        //dd($payload);
        return [$payload['exp'], $payload['username'], $payload['roles']];
    }



}
