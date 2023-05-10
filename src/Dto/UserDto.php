<?php

namespace App\Dto;

use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\Validator\Constraints as Assert;

class UserDto
{
    #[Serializer\Type('string')]
    #[Assert\Email(message: 'Wrong email {{ value }} .')]
    #[Assert\NotBlank(message: 'Email can not be null')]
    private ?string $username = null;

    #[Serializer\Type('string')]
    #[Assert\Length(min: 6, minMessage: 'Password must contains at least {{ limit }} symbols.')]
    private ?string $password = null;

    #[Assert\NotBlank()]
    private float $balance;

    #[Serializer\Type('array')]
    private array $roles;


    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function setRoles(array $roles): void
    {
        $this->roles = $roles;
    }

    public function getBalance(): float
    {
        return $this->balance;
    }

    public function setBalance(float $balance): void
    {
        $this->balance = $balance;
    }
}