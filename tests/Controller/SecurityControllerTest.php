<?php

namespace App\Tests\Controller;

use App\Tests\AbstractTest;
use App\Tests\Mock\BillingClientMock;
use Symfony\Component\Panther\PantherTestCase;

class SecurityControllerTest extends AbstractTest
{
    const USER_CREDENTIALS = [
        'username' => 'user@example.com',
        'password' => 'password'
    ];

    public function testAuthAndLogout(): void
    {
        $client = $this->billingClient();
        $crawler = $client->request('GET', '/');
        $crawler = $client->followRedirect();

        $link = $crawler->selectLink('Вход')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $submitBtn = $crawler->selectButton('Войти');
        $login = $submitBtn->form([
            'email' => self::USER_CREDENTIALS['username'],
            'password' => self::USER_CREDENTIALS['password'],
        ]);
        $client->submit($login);

        $this->assertResponseRedirect();
        $crawler = $client->followRedirect();
        self::assertEquals('/courses/', $client->getRequest()->getPathInfo());

        $link = $crawler->selectLink('Выход')->link();
        $crawler = $client->click($link);

        $this->assertResponseRedirect();
        $link = $crawler->selectLink('Авторизация')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $submitBtn = $crawler->selectButton('Войти');
        $login = $submitBtn->form([
            'email' => self::USER_CREDENTIALS['username'],
            'password' => 'magic',
        ]);
        $client->submit($login);

        $this->assertResponseRedirect();
        $crawler = $client->followRedirect();

        self::assertSelectorTextContains(
            '.alert.alert-danger',
            'Ошибка авторизации. Проверьте правильность введенных данных!'
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
        
        $form = $crawler->selectButton('Зарегистрироваться')->form();
        $login = $crawler->selectButton('Зарегистрироваться')->form([
            'register_form[email]' => self::USER_CREDENTIALS['username'],
            'register_form[password][first]' => self::USER_CREDENTIALS['password'],
            'register_form[password][second]' => self::USER_CREDENTIALS['password'],
        ]);
        $client->submit($login);
        $this->assertResponseOk();

        self::assertEquals('/register', $client->getRequest()->getPathInfo());

        self::assertSelectorTextContains(
            '.alert.alert-danger',
            'Email уже используется'
        );

        $login = $crawler->selectButton('Зарегистрироваться')->form([
            'register_form[email]' => 'test@example.com',
            'register_form[password][first]' => 'password',
            'register_form[password][second]' => 'password',
        ]);
        $client->submit($login);
        $this->assertResponseOk();
    }

    private function billingClient()
    {
        $client = self::getClient();
        $client->disableReboot();
        $client->getContainer()->set(
                BillingClient::class,
                new BillingClientMock()
        );
        return $client;
    }
}
