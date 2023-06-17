<?php

namespace App\Tests\Controller;

use App\DataFixtures\AppFixtures;
use App\Entity\Course;
use App\Entity\Lesson;
use App\Enum\CourseType;
use App\Tests\AbstractTest;
use App\Tests\Mock\BillingClientMock;
use App\Tests\Utils\AuthAdmin;
use JMS\Serializer\SerializerInterface;

class CourseControllerTest extends AbstractTest
{
    private SerializerInterface $serializer;

    /**
     * @dataProvider urlProviderIsSuccessful
     */
    public function testPageIsSuccessful($url): void
    {
        $client = self::getClient();
        $client->request('GET', $url);
        $this->assertResponseOk();
    }

    public function urlProviderIsSuccessful(): \Generator
    {
        yield ['/courses/'];
        //  yield ['/courses/new'];
    }

    const USER_CREDENTIALS = [
        'username' => 'user@example.com',
        'password' => 'password'
    ];

    /**
     * @dataProvider urlProviderNotFound
     */
    public function testPageIsNotFound($url): void
    {
        $client = self::getClient();
        $client->request('GET', $url);
        $this->assertResponseNotFound();
    }

    public function urlProviderNotFound(): \Generator
    {
        yield ['/not-found/'];
        yield ['/courses/-1'];
    }

    public function testGetActionsResponseOk(): void
    {    
        $client = self::getClient();
        $client = $this->authAdmin($client);
        $courses = self::getEntityManager()->getRepository(Course::class)->findAll();
        
        foreach ($courses as $course) {
            // детальная страница
            $client->request('GET', '/courses/' . $course->getId());
            $this->assertResponseOk();

            // страница редактирования
            $client->request('GET', '/courses/' . $course->getId() . '/edit');
            $this->assertResponseOk();

            // страница создания урока
            $client->request('GET', '/lessons/new?course_id=' . $course->getId());
            $this->assertResponseOk();
        }
    }

    public function testUserAccessToCourses(): void
    {
        $client = $this->billingClient();
        $courses = self::getEntityManager()->getRepository(Course::class)->findAll();

        // анону недоступны страницы создания и редактирования курсов. Редиректит на страницу авторизации
        $crawler = $client->request('GET', '/courses/new');
        $this->assertResponseRedirect();

        $crawler = $client->request('GET', '/courses/' . $courses[0]->getId() . '/edit');
        $this->assertResponseRedirect();

        // обычному недоступны страницы создания и редактирования курсов
        // обычный пользователь входит на сайт
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
        $crawler = $client->request('GET', '/courses/new');
        $this->assertResponseCode(403);

        $crawler = $client->request('GET', '/courses/' . $courses[0]->getId() . '/edit');
        $this->assertResponseCode(403);

    }

    public function testNumberOfCourses(): void
    {
        $client = self::getClient();
        $crawler = $client->request('GET', '/courses/');
        $coursesCount = count(self::getEntityManager()->getRepository(Course::class)->findAll());
        // проверяем количество курсов
        self::assertCount($coursesCount, $crawler->filter('.card-body'));
    }

    public function testNumberOfCourseLessons(): void
    {
        $client = self::getClient();
        $courses = self::getEntityManager()->getRepository(Course::class)->findAll();
        foreach ($courses as $course) {
            $crawler = $client->request('GET', '/courses/' . $course->getId());
            $lessonsCount = count($course->getLessons());
            // проверяем количество уроков для каждого курса
            self::assertCount($lessonsCount, $crawler->filter('.list-group-item'));
        }
    }

    public function testCourseCreatingWithEmptyCode(): void
    {
        $client = $this->billingClient();
        $client = $this->authAdmin($client);
        // от списка курсов переходим на страницу создания курса
        $crawler = $client->request('GET', '/courses/');
        $this->assertResponseOk();

        $link = $crawler->selectLink('Новый курс')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // заполняем форму создания курса с пустым кодом и отправляем
        $submitBtn = $crawler->selectButton('Сохранить');
        $courseCreatingForm = $submitBtn->form([
            'course[code]' => '',
            'course[name]' => 'Course name for test',
            'course[description]' => 'Description course for test',
        ]);
        $client->submit($courseCreatingForm);
        //$this->assertResponseRedirect();
        $this->assertResponseCode(422);

        // Проверяем наличие сообщения об ошибке
        self::assertSelectorTextContains(
            '.invalid-feedback.d-block',
            'Символьный код не может быть пустым'
        );
    }

    public function testCourseCreatingWithEmptyName(): void
    {
        $client = $this->billingClient();
        $client = $this->authAdmin($client);
        // от списка курсов переходим на страницу создания курса
        $crawler = $client->request('GET', '/courses/');
        $this->assertResponseOk();

        $link = $crawler->selectLink('Новый курс')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // заполняем форму создания курса данными с пустым названием
        $submitBtn = $crawler->selectButton('Сохранить');
        $courseCreatingForm = $submitBtn->form([
            'course[code]' => 'PHP-TEST',
            'course[name]' => '      ',
            'course[description]' => 'Description course for test',
        ]);
        $client->submit($courseCreatingForm);
        $this->assertResponseCode(422);

        // Проверяем наличие сообщения об ошибке
        self::assertSelectorTextContains(
            '.invalid-feedback.d-block',
            'Название не может быть пустым'
        );
    }

    public function testCourseCreatingWithNotUniqueCode(): void
    {
        $client = $this->billingClient();
        $client = $this->authAdmin($client);
        // от списка курсов переходим на страницу создания курса
        $crawler = $client->request('GET', '/courses/');
        $this->assertResponseOk();

        $link = $crawler->selectLink('Новый курс')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $code = 'nympydata';

        // заполняем форму создания курса корректными данными и отправляем
        $submitBtn = $crawler->selectButton('Сохранить');
        $courseCreatingForm = $submitBtn->form([
            'course[code]' => $code,
            'course[name]' => 'Course name for test',
            'course[description]' => 'Course description for test',
        ]);
        $client->submit($courseCreatingForm);

        $course = self::getEntityManager()->getRepository(Course::class)->findOneBy([
            'code' => $code,
        ]);

        $crawler = $client->request('GET', '/courses/');
        $this->assertResponseOk();

        $link = $crawler->selectLink('Новый курс')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // заполнили форму и отправили с не уникальным кодом
        $submitBtn = $crawler->selectButton('Сохранить');
        $courseCreatingForm = $submitBtn->form([
            'course[code]' => $code,
            'course[name]' => 'Course name for test',
            'course[description]' => 'Description course for test',
        ]);
        $client->submit($courseCreatingForm);

        // Проверяем наличие сообщения об ошибке
        $this->assertResponseCode(422);
    }

    public function testCourseSuccessfulEditing(): void
    {
        $client = $this->billingClient();
        $client = $this->authAdmin($client);
        // от списка курсов переходим на страницу редактирования курса
        $crawler = $client->request('GET', '/courses/');
        $this->assertResponseOk();

        //dd($client->getResponse()->getContent());  

        $link = $crawler->filter('.course-show')->last()->link();
        //dd($client->click($link));
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // на детальной странице курса
        $link = $crawler->selectLink('Редактировать')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $submitButton = $crawler->selectButton('Сохранить');
        $form = $submitButton->form();

        // сохраняем id редактируемого курса
        $courseId = self::getEntityManager()
            ->getRepository(Course::class)
            ->findOneBy(['code' => $form['course[code]']->getValue()])->getId();

        // заполняем форму корректными данными
        $form['course[code]'] = 'lastcodeedit';
        $form['course[name]'] = 'Course name for test';
        $form['course[description]'] = 'Description course for test';
        $client->submit($form);

        // проверяем редирект
        $crawler = $client->followRedirect();

        $this->assertResponseOk();
    }

    public function testCourseFailedEditing(): void
    {
        $client = $this->billingClient();
        $client = $this->authAdmin($client);
        // со страницы списка курсов
        $crawler = $client->request('GET', '/courses/');
        $this->assertResponseOk();

        // на детальную страницу курса
        $link = $crawler->filter('.course-show')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $link = $crawler->selectLink('Редактировать')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $submitButton = $crawler->selectButton('Сохранить');
        $form = $submitButton->form();

        // пробуем сохранить курс без кода
        $form['course[code]'] = '';
        $form['course[name]'] = 'Course name for test';
        $form['course[description]'] = 'Description course for test';
        $client->submit($form);

        $this->assertResponseCode(422);

        // пробуем сохранить курс с пустым именем
        $form['course[code]'] = 'exampleuniqcode';
        $form['course[name]'] = '';
        $client->submit($form);
        $this->assertResponseCode(422);
    }

    public function testCourseDeleting(): void
    {
        $client = $this->billingClient();
        $client = $this->authAdmin($client);
        // страница со списком курсов
        $crawler = $client->request('GET', '/courses/');
        $this->assertResponseOk();

        // подсчитываем количество курсов 
        $coursesCount = count(self::getEntityManager()->getRepository(Course::class)->findAll());

        // заходим 
        $link = $crawler->filter('.course-show')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // получаем курс, который удалим
        $courseName = $crawler->filter('.course-name')->text();
        $course = self::getEntityManager()->getRepository(Course::class)->findOneBy([
            'name' => $courseName,
        ]);

        $client->submitForm('Удалить');
        self::assertSame($client->getResponse()->headers->get('location'), '/courses/');
        $crawler = $client->followRedirect();


        $coursesCountAfterDelete = count(self::getEntityManager()->getRepository(Course::class)->findAll());
        $lessonsAfterDeleteCourse = count(self::getEntityManager()->getRepository(Lesson::class)->findBy(['course' => $course]));

        self::assertSame(0, $lessonsAfterDeleteCourse);

        // проверка соответствия кол-ва курсов
        self::assertSame($coursesCount - 1, $coursesCountAfterDelete);
        self::assertCount($coursesCountAfterDelete, $crawler->filter('.card-body'));
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

        $container->set(
            'App\Service\BillingClient',
            new BillingClientMock($this->serializer)
        );
        
        return $client;
    }

    protected function getFixtures(): array
    {
        return [AppFixtures::class];
    }

    private function authAdmin($client, $stop = false)
    {
        $auth = new AuthAdmin();
        return $auth->auth($client, $stop);
    }

    public function testSubmitNewCourse(): void
    {
        $client = $this->billingClient();
        $client = $this->authAdmin($client);

        $courseRepository = self::getEntityManager()->getRepository(Course::class);
        $oldCount = $courseRepository->count([]);

        $client->request('GET', '/courses');
        $crawler = $client->followRedirect();
        
        //dd($client->getResponse()->getContent());  

        $crawler = $client->clickLink('Новый курс');

        $this->assertResponseRedirect();

        $form = $crawler
            ->selectButton('Сохранить')
            ->form();

        self::assertFalse($form->has('course[id]'));
        self::assertTrue($form->has('course[code]'));
        self::assertTrue($form->has('course[name]'));
        self::assertTrue($form->has('course[price]'));
        self::assertTrue($form->has('course[type]'));
        self::assertTrue($form->has('course[description]'));

        $crawler = $client->submitForm('Сохранить', [
            'course[name]' => 'Тестовый курс',
            'course[price]' => 1,
            'course[type]' => CourseType::BUY_NAME,
            'course[description]' => 'Описание тестового курса'
        ]);
        $this->assertResponseCode(422);

        // Если не указано имя
        $client->back();
        $crawler = $client->submitForm('Сохранить', [
            'course[code]' => 'test',
            'course[description]' => 'Описание тестового курса'
        ]);
        $this->assertResponseCode(422);

        // Если не указано описание
        $client->back();
        $crawler = $client->submitForm('Сохранить', [
            'course[code]' => 'test-1',
            'course[name]' => 'Тестовый курс1'
        ]);
        $this->assertResponseOk(); // нет ошибки
        self::assertRouteSame('app_course_index');

        // Указано всё
        $client->back();
        $client->submitForm('Сохранить', [
            'course[code]' => 'test-2',
            'course[name]' => 'Тестовый курс 2',
            'course[description]' => 'Описание тестового курса 2'
        ]);
        $this->assertResponseOk();

        // Создание курса с тем же кодом невозможно
        $client->back();
        $client->submitForm('Сохранить', [
            'course[code]' => 'test-2',
            'course[name]' => 'Тестовый курс 3',
            'course[description]' => 'Описание тестового курса 3'
        ]);
        $this->assertResponseCode(400);

        // В итоге добавилось 2 курса
        self::assertEquals($oldCount + 2, $courseRepository->count([]));
    }


    public function testSubmitNewCourseFailed(): void
    {
        $client = $this->billingClient();

        $client->request('GET', '/courses');
        $this->assertResponseRedirect();

        $client->request('GET', '/courses/new/');
        $this->assertResponseCode(403);

        $client->request('POST', '/courses/new');
        self::assertResponseRedirects('/login');

        $client->followRedirects();
        $client = $this->authAdmin($client);

        $this->expectException('InvalidArgumentException');
        $client->clickLink('Добавить курс');

        $client->request('GET', '/courses/new/');
        $this->assertResponseCode(403);

        $client->request('POST', '/courses/new');
        $this->assertResponseCode(403);
    }

    public function testEditCourse(): void
    {
        $client = $this->billingClient();
        $client = $this->authAdmin($client);

        $courseRepository = self::getEntityManager()->getRepository(Course::class);

        $client->request('GET', '/');
        //$client->request('GET', '/courses/');

        $client->request('GET', '/courses');
        $crawler = $client->followRedirect();

        // на детальную страницу курса
        $link = $crawler->filter('.course-show')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();


        $crawler = $client->clickLink('Редактировать');

        //dd($client->getResponse()->getContent());  
        $form = $crawler->filter('form')->first()->form();

        // Проверка заполненненных полей
        $values = $form->getValues();
        $courseId = 1;
        $course = $courseRepository->find($courseId);
        self::assertSame($course->getCode(), $values['course[code]']);
        self::assertSame($course->getName(), $values['course[name]']);
        self::assertEquals(10, $values['course[price]']);
        self::assertSame('rent', $values['course[type]']);
        self::assertSame($course->getDescription(), $values['course[description]']);

        $code = 'test';
        $name = 'Тестовый курс';
        $description = 'Описание тестового курса';

        // Сохранение обновленного курса
        $form['course[code]'] = $code;
        $form['course[name]'] = $name;
        $form['course[description]'] = $description;
        $client->submit($form);

        $this->assertResponseOk();
        self::assertRouteSame('app_course_index');

        // Проверка курса
        $course = $courseRepository->find($courseId);
        self::assertEquals($code, $course->getCode());
        self::assertEquals($name, $course->getName());
        self::assertEquals($description, $course->getDescription());
    }

    public function testEditCourseFailed(): void
    {
        $client = $this->billingClient();
        $crawler = $client->request('GET', '/courses/');
        $this->assertResponseOk();

        $crawler = $client->request('GET', '/courses/1/edit/');
        $this->assertResponseCode(403);

        $client->request('POST', '/courses/1/edit');
        self::assertResponseRedirects('/login');

        // Авторизован как обычный пользователь
        $crawler = $client->request('GET', '/');

        $client = $this->authAdmin($client);
        $crawler = $client->request('GET', '/courses/1');

        // Без прав админа
        $client->request('GET', '/courses/1/edit/');
        $this->assertResponseCode(403);

        $client->request('POST', '/courses/1/edit');
        $this->assertResponseCode(403);
    }

    public function testGetTransactions(): void
    {
        $client = $this->billingClient();
        $client = $this->authAdmin($client);

        $transactions = $client->getTransactions($client->generateToken(['ROLE_SUPER_ADMIN'], 'admin@example.com'));

        $crawler = $client->request('GET', '/courses/');
        $crawler = $client->clickLink('Профиль');
        $this->assertResponseOk();

        $crawler = $client->clickLink('История платежей');
        $this->assertResponseOk();

        $crawler = $client->request('GET', '/profile/transactions/');
        $this->assertResponseOk();
        self::assertEquals(count($transactions), $crawler->filter('table > tbody > tr')->count());
    }
}