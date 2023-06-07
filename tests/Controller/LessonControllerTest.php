<?php

namespace App\Tests\Controller;

use App\DataFixtures\AppFixtures;
use App\Entity\Course;
use App\Entity\Lesson;
use App\Tests\AbstractTest;
use App\Tests\Mock\BillingClientMock;
use App\Tests\Utils\AuthAdmin;
use JMS\Serializer\SerializerInterface;

class LessonControllerTest extends AbstractTest
{
    const USER_CREDENTIALS = [
        'username' => 'user@example.com',
        'password' => 'password'
    ];

    private SerializerInterface $serializer;

    public function testGetActionsResponseOk(): void
    {
        $client = $this->billingClient();
        $client = $this->authAdmin($client);
        $lessons = self::getEntityManager()->getRepository(Lesson::class)->findAll();
        foreach ($lessons as $lesson) {
            // детальная страница
            $client->request('GET', '/lessons/' . $lesson->getId());
            $this->assertResponseOk();

            // страница редактирования
            $client->request('GET', '/lessons/' . $lesson->getId() . '/edit');
            $this->assertResponseOk();
        }
    }

    public function testUserAccessToCourses(): void
    {
        $client = $this->billingClient();
        $courses = self::getEntityManager()->getRepository(Course::class)->findAll();
        $lessons = self::getEntityManager()->getRepository(Lesson::class)->findBy(['course' => $courses[0]]);

        // анон не может смотреть уроки
        $crawler = $client->request('GET', '/lessons/' . $lessons[0]->getId());
        $this->assertResponseRedirect();

        // анон не может редактировать уроки
        $crawler = $client->request('GET', '/lessons/' . $lessons[0]->getId() . '/edit');
        $this->assertResponseRedirect();

        // анон не может создавать уроки
        $crawler = $client->request('GET', '/lessons/new');
        $this->assertResponseRedirect();

        // обычному недоступны страницы создания и редактирования уроков
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

        // обычный пользователь не может редактировать уроки
        $crawler = $client->request('GET', '/lessons/' . $lessons[0]->getId() . '/edit');
        $this->assertResponseCode(403);

        // обычный пользователь не может создавать уроки
        $crawler = $client->request('GET', '/lessons/new');
        $this->assertResponseCode(403);

    }

    public function testSuccessfulLessonCreating(): void
    {
        $client = $this->billingClient();
        $client = $this->authAdmin($client);
        $crawler = $client->request('GET', '/courses/');
        $this->assertResponseOk();

        $link = $crawler->filter('.course-show')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // создание урока
        $link = $crawler->selectLink('Добавить урок')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // создаем форму с нормальными данными
        $form = $crawler->selectButton('Сохранить')->form([
            'lesson[name]' => 'Lesson for test',
            'lesson[content]' => 'Some content in test for lesson',
            'lesson[serial]' => '100',
        ]);

        $course = self::getEntityManager()
            ->getRepository(Course::class)
            ->findOneBy(['id' => $form['lesson[course]']->getValue()]);

        $client->submit($form);
        $this->assertResponseRedirect();

        // проверяем редирект
        self::assertSame($client->getResponse()->headers->get('location'), '/courses/' . $course->getId());
        $crawler = $client->followRedirect();

        $this->assertResponseOk();
    }

    public function testLessonCreatingWithEmptyName(): void
    {
        $client = $this->billingClient();
        $client = $this->authAdmin($client);
        // от списка курсов переходим на страницу просмотра курса
        $crawler = $client->request('GET', '/courses/');
        $this->assertResponseOk();

        $link = $crawler->filter('.course-show')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // создание урока
        $link = $crawler->selectLink('Добавить урок')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // заполняем форму данными с пустым названием
        $form = $crawler->selectButton('Сохранить')->form([
            'lesson[name]' => '',
            'lesson[content]' => 'Some content in test for lesson',
            'lesson[serial]' => '100',
        ]);
        $client->submit($form);
        $this->assertResponseCode(422);

        self::assertSelectorTextContains(
            '.invalid-feedback.d-block',
            'Название урока не может быть пустым'
        );
    }

    public function testLessonCreatingWithEmptyContent(): void
    {
        $client = $this->billingClient();
        $client = $this->authAdmin($client);
        // от списка курсов переходим на страницу просмотра курса
        $crawler = $client->request('GET', '/courses/');
        $this->assertResponseOk();

        $link = $crawler->filter('.course-show')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // создание урока
        $link = $crawler->selectLink('Добавить урок')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // заполняем форму данными с пустым названием
        $form = $crawler->selectButton('Сохранить')->form([
            'lesson[name]' => 'Lesson for test',
            'lesson[content]' => '',
            'lesson[serial]' => '100',
        ]);
        $client->submit($form);
        $this->assertResponseCode(422);

        self::assertSelectorTextContains(
            '.invalid-feedback.d-block',
            'Содержимое урока не может быть пустым'
        );
    }

    public function testLessonCreatingWithEmptyNumber(): void
    {
        $client = $this->billingClient();
        $client = $this->authAdmin($client);
        // от списка курсов переходим на страницу просмотра курса
        $crawler = $client->request('GET', '/courses/');
        $this->assertResponseOk();

        $link = $crawler->filter('.course-show')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // создание урока
        $link = $crawler->selectLink('Добавить урок')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // заполняем форму данными с пустым порядковым номером
        $form = $crawler->selectButton('Сохранить')->form([
            'lesson[name]' => 'Lesson for test',
            'lesson[content]' => 'Some content in test for lesson',
            'lesson[serial]' => '',
        ]);
        $client->submit($form);

        $this->assertResponseCode(422);

        self::assertSelectorTextContains(
            '.invalid-feedback.d-block',
            'Порядковый номер не может быть пустым'
        );
    }


    protected function getFixtures(): array
    {
        return [AppFixtures::class];
    }

    private function authAdmin($client)
    {
        $auth = new AuthAdmin();
        return $auth->auth($client);
    }

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

    public function testSuccessfulLessonEditing(): void
    {
        $client = $this->billingClient();
        $client = $this->authAdmin($client);
        $crawler = $client->request('GET', '/courses/');
        $this->assertResponseOk();

        $link = $crawler->filter('.course-show')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $link = $crawler->filter('.lesson')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $link = $crawler->selectLink('Редактировать')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $form = $crawler->selectButton('Сохранить')->form();

        $course = self::getEntityManager()
            ->getRepository(Course::class)
            ->findOneBy(['id' => $form['lesson[course]']->getValue()]);

        $form['lesson[name]'] = 'Test edit lesson';
        $form['lesson[content]'] = 'Test edit lesson content';
        $form['lesson[serial]'] = '9999';
        $client->submit($form);

        // проверяем редирект
        self::assertSame($client->getResponse()->headers->get('location'), '/courses/' . $course->getId());
        $crawler = $client->followRedirect();
        $this->assertResponseOk();

        // проверяем, что урок отредактирован
        $this->assertSame($crawler->filter('.lesson')->last()->text(), 'Test edit lesson');

        $link = $crawler->filter('.lesson')->last()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // проверим название и содержание
        $this->assertSame($crawler->filter('.lesson-name')->first()->text(), 'Test edit lesson');
        $this->assertSame($crawler->filter('.content')->first()->text(), 'Test edit lesson content');
    }

    public function testLessonDeleting(): void
    {
        $client = $this->billingClient();
        $client = $this->authAdmin($client);
        $crawler = $client->request('GET', '/courses/');
        $this->assertResponseOk();

        $link = $crawler->filter('.course-show')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $link = $crawler->filter('.lesson')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // сохраняем информацию о курсе
        $link = $crawler->selectLink('Редактировать')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $form = $crawler->selectButton('Сохранить')->form();
        $course = self::getEntityManager()
            ->getRepository(Course::class)
            ->findOneBy(['id' => $form['lesson[course]']->getValue()]);

        // количество до удаления
        $countBeforeDeleting = count($course->getLessons());

        $link = $crawler->filter('.course')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $link = $crawler->filter('.lesson')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();
        $client->submitForm('Удалить');
        self::assertSame($client->getResponse()->headers->get('location'), '/courses/' . $course->getId());
        $crawler = $client->followRedirect();

        self::assertCount($countBeforeDeleting - 1, $crawler->filter('.lesson'));
    }

    public function testShowPaidLesson(): void
    {
        $billingClientMock = $this->billingClient();
        $billingClientMock = $this->authAdmin($billingClientMock);

        $transactions = $billingClientMock->getTransactions($billingClientMock->generateToken(['ROLE_SUPER_ADMIN'], 'admin@example.com'));; // Курс изначально куплен

        $client = static::getClient();
        $client->followRedirects();

        $course = $this->getEntityManager()->getRepository(Course::class)->findOneBy(['code' => $transactions[0]['course_code']]);
        $client->request('GET', '/');
        $client->request('GET', '/courses/' . $course->getId());
        $this->assertResponseOk();
    }

}