<?php

namespace App\Entity;

use App\Repository\LessonRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: LessonRepository::class)]
class Lesson
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\Length(
        max: 255,
        maxMessage: 'Название урока не должно превышать {{ limit }} символов'
    )]
    #[Assert\NotBlank(
        message: "Название урока не может быть пустым"
    )]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(
        message: "Содержимое урока не может быть пустым"
    )]
    private ?string $content = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    #[Assert\Length(
        max: 10000,
        maxMessage: 'Порядковый номер урока не может быть более, чем {{ limit }}',
    )]
    private ?int $serial = null;

    #[ORM\ManyToOne(inversedBy: 'lessons')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Course $course = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getSerial(): ?int
    {
        return $this->serial;
    }

    public function setSerial(?int $serial): self
    {
        $this->serial = $serial;

        return $this;
    }

    public function getCourse(): ?Course
    {
        return $this->course;
    }

    public function setCourse(?Course $course): self
    {
        $this->course = $course;

        return $this;
    }
}
