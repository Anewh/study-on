<?php

namespace App\Tests\Controller;

use App\Service\BillingClient;
use App\Tests\AbstractTest;
use App\Tests\Mock\BillingClientMock;
use App\Tests\Utils\AuthAdmin;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\Panther\PantherTestCase;

class SecurityControllerTest extends AbstractTest
{
    private SerializerInterface $serializer;

    const USER_CREDENTIALS = [
        'username' => 'admin@example.com',
        'password' => 'password'
    ];

    private function billingClient()
    {
        // по какой-то невероятной причине у меня этот способ работает корректно, а предложенный в доке - нет
        $client = static::createClient();
        $client->disableReboot();
        self::$client = $client;
        // обязательно нужно вынести контейнер в отдельную переменную
        $container = static::getContainer();
        $this->serializer = $container->get(SerializerInterface::class);

        $container->set(BillingClient::class,
           new BillingClientMock($this->serializer));

        return $client;
    }

    public function testAuthAndLogout(): void
    {
        $client = $this->billingClient();
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
        
        // выход из учетной записи
        $link = $crawler->selectLink('Выход')->link();
        $crawler = $client->click($link);
        // сначала редирект на '/'
        $this->assertResponseRedirect();
        $crawler = $client->followRedirect();
        // потом редирект на '/courses/'
        $this->assertResponseRedirect();
        $crawler = $client->followRedirect();

        // переходим к форме авторизации
        $link = $crawler->selectLink('Вход')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();
        self::assertEquals('/login', $client->getRequest()->getPathInfo());
        $link = $crawler->selectLink('Вход')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // попытка войти с неправильным паролем
        $submitBtn = $crawler->selectButton('Войти');
        $login = $submitBtn->form([
            'email' => self::USER_CREDENTIALS['username'],
            'password' => 'ЯКОНЧЕНЫЙБЕГИТЕ',
        ]);
        
        $client->submit($login);

        $this->assertResponseRedirect();
        $crawler = $client->followRedirect();
    
        self::assertSelectorTextContains(
            '.alert.alert-danger',
            'Неправильные логин или пароль'
        );
    }

    public function testRegisterAndLogout(): void
    {
        $client = $this->billingClient();
        
        $crawler = $client->request('GET', '/');
        $crawler = $client->followRedirect();

        $link = $crawler->selectLink('Регистрация')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();
        
        // попытка зарегистрироваться под существующей учетной записью
        $reg = $crawler->selectButton('Зарегистрироваться')->form([
            'register_form[email]' => self::USER_CREDENTIALS['username'],
            'register_form[password][first]' => self::USER_CREDENTIALS['password'],
            'register_form[password][second]' => self::USER_CREDENTIALS['password'],
        ]);
        $client->submit($reg);
        self::assertEquals('/register', $client->getRequest()->getPathInfo());
        
        self::assertSelectorTextContains(
            '.alert.alert-danger',
            'Email уже используется'
        );

        // регистрация с корректными данными
        $reg = $crawler->selectButton('Зарегистрироваться')->form([
            'register_form[email]' => 'test@example.com',
            'register_form[password][first]' => 'password',
            'register_form[password][second]' => 'password',
        ]);
        $client->submit($reg);
        $this->assertResponseRedirect();
        $crawler = $client->followRedirect();
        self::assertEquals('/courses/', $client->getRequest()->getPathInfo());
    }

    public function testCheckProfile(): void
    {
        $client = $this->billingClient();
        // попытка анона зайти на страницу профиля
        $crawler = $client->request('GET', '/profile');
        $this->assertResponseRedirect();
        $crawler = $client->followRedirect();
        // анона перенаправляет на страницу авторизации 
        self::assertEquals('/login', $client->getRequest()->getPathInfo());
        // админ пробует зайти в профиль
        
        $submitBtn = $crawler->selectButton('Войти');
        $login = $submitBtn->form([
            'email' => self::USER_CREDENTIALS['username'],
            'password' => self::USER_CREDENTIALS['password'],
        ]);
        $client->submit($login);
        $this->assertResponseRedirect();
        $crawler = $client->followRedirect();

        // админ зашел на страницу профиля
        self::assertEquals('/profile', $client->getRequest()->getPathInfo());
        $this->assertResponseOk();
    }
}
