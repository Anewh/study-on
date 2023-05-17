<?php

namespace App\Service;

use App\Dto\UserDto;
use App\Exception\BillingUnavailableException;
use JMS\Serializer\Serializer;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

class BillingClient
{
    protected const GET = 'GET';
    protected const POST = 'POST';
    private string $host;
    private Serializer $serializer;

    public function __construct(SerializerInterface $serializer)
    {
        $this->host = $_ENV['BILLING_HOST'];

        $this->serializer = $serializer;
    }

    public function auth(array $credentials): array
    {

        $response = $this->jsonRequest(
            self::POST,
            '/auth',
            [],
            $credentials
        );
        if ($response['code'] === 401) {
            throw new BillingUnavailableException('Неправильные логин или пароль');
        }
        if ($response['code'] >= 400) {
            throw new BillingUnavailableException('Сервис временно недоступен');
        }
        return $this->parseJsonResponse($response);
    }

    public function register(array $credentials): array
    {
        $response = $this->jsonRequest(
            self::POST,
            '/register',
            [],
            $credentials
        );

        if ($response['code'] === 409) {
            throw new CustomUserMessageAuthenticationException('Пользователь с указанным email уже существует');
        }
        if ($response['code'] >= 400) {
            throw new BillingUnavailableException('Сервис временно недоступен. Попробуйте зарегистрироваться позднее');
        }
        return $this->parseJsonResponse($response);
    }

    public function getCurrentUser(string $token): UserDto
    {
        $response = $this->jsonRequest(
            self::GET,
            '/users/current',
            [],
            [],
            ['Authorization' => 'Bearer ' . $token]
        );

        if ($response['code'] === 401) {
            throw new UnauthorizedHttpException('Необходимо войти заново');
        }

        if ($response['code'] >= 400) {
            throw new BillingUnavailableException();
        }

        $userDto = $this->parseJsonResponse($response, UserDto::class);
        return $userDto;
    }

    private function parseJsonResponse(array $response, ?string $type = null)
    {
        if (null === $type) {
            return json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
        }
        return $this->serializer->deserialize($response['body'], $type, 'json');
    }

    private function jsonRequest(
        string $method,
        string $path,
        array $parameters = [],
        $data = [],
        array $headers = []
    ): array {

        $headers['Accept'] = 'application/json';
        $headers['Content-Type'] = 'application/json';
        $body = $this->serializer->serialize($data, 'json');

        if (count($parameters) > 0) {
            $path .= '?';

            $newParameters = [];
            foreach ($parameters as $name => $value) {
                $newParameters[] = $name . '=' . $value;
            }
            $path .= implode('&', $newParameters);
        }

        $ch = curl_init($this->host . $path);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        if ($method === self::POST && !empty($body)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        if (count($headers) > 0) {
            $curlHeaders = [];
            foreach ($headers as $name => $value) {
                $curlHeaders[] = $name . ': ' . $value;
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
        }
        $response = curl_exec($ch);
        if (curl_error($ch)) {
            throw new BillingUnavailableException(curl_error($ch));
        }
        $responseCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return [
            'code' => $responseCode,
            'body' => $response,
        ];
    }
}
