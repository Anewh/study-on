<?php

namespace App\Tests\Utils;

use App\Service\BillingClient;
use App\Tests\AbstractTest;
use App\Tests\Mock\BillingClientMock;
use JMS\Serializer\SerializerInterface;

class AuthAdmin extends AbstractTest
{
    private SerializerInterface $serializer;
    const USER_CREDENTIALS = [
        'username' => 'admin@example.com',
        'password' => 'password'
    ];

    public function setSerializer(SerializerInterface $serializer): void
    {
        $this->serializer = $serializer;
    }

    public function auth($client)
    {
        //$client = $this->billingClient();
        $crawler = $client->request('GET', '/');
        $crawler = $client->followRedirect();
        $link = $crawler->selectLink('Вход')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // авторизация с нормальными данными
        $submitBtn = $crawler->selectButton('Войти');
        $login = $submitBtn->form([
            'email' => self::USER_CREDENTIALS['username'],
            'password' => self::USER_CREDENTIALS['password'],
        ]);
        $client->submit($login);
        $this->assertResponseRedirect();
        $crawler = $client->followRedirect();
        self::assertEquals('/courses/', $client->getRequest()->getPathInfo());
        return $client;
    }

    // private function billingClient()
    // {
    //     // по какой-то невероятной причине у меня этот способ работает корректно, а предложенный в доке - нет
    //     $client = static::createClient();
    //     $client->disableReboot();
    //     self::$client = $client;
    //     // обязательно нужно вынести контейнер в отдельную переменную
    //     $container = static::getContainer();
    //     $this->serializer = $container->get(SerializerInterface::class);

    //     $container->set(BillingClient::class,
    //        new BillingClientMock($this->serializer));

    //     return $client;
    // }

}