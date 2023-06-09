<?php

namespace App\Tests\Mock;

use App\Dto\CourseDto;
use App\Dto\UserDto;
use App\Entity\Course;
use App\Enum\CourseType;
use App\Exception\BillingUnavailableException;
use App\Exception\CourseAlreadyPaidException;
use App\Exception\CustomUserMessageAuthenticationException;
use App\Security\User;
use App\Service\BillingClient;
use JMS\Serializer\Serializer;
use JMS\Serializer\SerializerBuilder;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class BillingClientMock extends BillingClient
{
    private const USER = [
        'username' => 'user@example.com',
        'password' => 'password',
        'roles' => ['ROLE_USER'],
        'balance' => 1000.0,
    ];
    private const ADMIN = [
        'username' => 'admin@example.com',
        'password' => 'password',
        'roles' => ['ROLE_USER', 'ROLE_SUPER_ADMIN'],
        'balance' => 1000.0,
    ];
    private const COURSES_DATA = [
        [
            'code' => 'nympydata',
            'type' => CourseType::FREE_NAME,
        ],
        [
            'code' => 'figmadesign',
            'type' => CourseType::RENT_NAME,
            'price' => 10,
        ],
        [
            'code' => 'molecularphysics',
            'type' => CourseType::BUY_NAME,
            'price' => 20,
        ]
    ];

    private const TRANSACTIONS_DATA = [
        0 => ['id' => 112, 'created_at' => '2023-06-07T22:40:36+00:00', 'type' => 'payment', 'course_code' => 'molecularphysics', 'amount' => 20], 
        1 => ['id' => 113, 'created_at' => '2023-06-07T22:40:36+00:00', 'expires_at' => '2023-06-14T22:40:36+00:00', 'type' => 'payment', 'course_code' => 'figmadesign', 'amount' => 10]
    ];

    private array $courses = [];
    private Serializer $serializer;

    public function __construct(SerializerInterface $serializer)
    {
        parent::__construct($serializer);

        foreach (self::COURSES_DATA as $i => $value) {
            $this->courses[$value['code']] = $value;
        }
    }

    public function auth($credentials): array
    {
        $username = $credentials['username'];
        $password = $credentials['password'];
        if ($username === self::USER['username'] && $password === self::USER['password']) {
            $token = $this->generateToken(self::USER['roles'], $username);
        } elseif ($username === self::ADMIN['username'] && $password === self::ADMIN['password']) {
            $token = $this->generateToken(self::ADMIN['roles'], $username);
        } else {
            throw new AuthenticationException('Неправильные логин или пароль');
        }

        return [
            'code' => 200,
            'token' => $token,
            'refresh_token' => 'refresh_token'
        ];
    }

    public function register($credentials): array
    {
        $this->serializer = SerializerBuilder::create()->build();
        $credentials = json_encode($credentials);
        $userDto = $this->serializer->deserialize($credentials, UserDto::class, 'json');
        $username = $userDto->getUsername();
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
        //dd($userDto);
        return $userDto;
    }
 
    public function getTransactions(
        string $token,
        ?string $transactionType = null,
        ?string $courseCode = null,
        bool $skipExpired = false
    ): array {
        return self::TRANSACTIONS_DATA;
    }

    public function generateToken(array $roles = null, string $username): string
    {
        return 'header.' . base64_encode(json_encode([
            'exp' => (new \DateTime('+ 1 hour'))->getTimestamp(),
            'username' => $username,
            'roles' => $roles,
        ], JSON_THROW_ON_ERROR)) . '.trailer';
    }

    public function getCourses(): array
    {
        return array_values($this->courses);
    }

    public function getCourse(string $code): array
    {
        $course = $this->courses[$code] ?? null;
        
        if ($course !== null) {
            return $course;
        } else {
            return throw new BillingUnavailableException();
        }
    }

    public function payCourse(string $token, string $code): array
    {
        foreach (self::TRANSACTIONS_DATA as $transaction) {
            if ($transaction['course_code'] === $code) {
                throw new CourseAlreadyPaidException();
            }
        }
        
        return [
            'success' => true
        ];
    }

    public function saveCourse(string $token, CourseDto $course, string $code = null): bool
    {
        unset($this->courses[$code]);
        $this->courses[$course->getCode()] = [
            'code' => $course->getCode(),
            'type' => $course->getType(),
            'price' => $course->getPrice(),
        ];
        return true;
    }

}