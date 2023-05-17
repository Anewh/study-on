<?php

namespace App\Tests\Controller;

use App\DataFixtures\AppFixtures;
use App\Entity\Course;
use App\Entity\Lesson;
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
        $client = $this->authAdmin();
        // $client = self::getClient();
        $courses = self::getEntityManager()->getRepository(Course::class)->findAll();
        //dd($courses);
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

        $crawler = $client->request('GET', '/courses/' . $courses[0]->getId() .'/edit');
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

        $crawler = $client->request('GET', '/courses/' . $courses[0]->getId() .'/edit');
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

    public function testSuccessfulCourseCreating(): void
    {
        $client = $this->authAdmin();
        // от списка курсов переходим на страницу создания курса
        $crawler = $client->request('GET', '/courses/');
        $this->assertResponseOk();

        $link = $crawler->selectLink('Новый курс')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $code = 'unique-code' . rand();
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

        // проверяем редирект
        self::assertSame($client->getResponse()->headers->get('location'), '/courses/' . $course->getId());

        $this->assertResponseRedirect();
        $crawler = $client->followRedirect();

        // проверяем корректность отображения данных 
        $this->assertSame($crawler->filter('.course-name')->text(), $course->getName());
        $this->assertSame($crawler->filter('.card-text')->text(), $course->getDescription());
    }

    public function testCourseCreatingWithEmptyCode(): void
    {
        $client = $this->authAdmin();
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
        $client = $this->authAdmin();
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
        $client = $this->authAdmin();
        // от списка курсов переходим на страницу создания курса
        $crawler = $client->request('GET', '/courses/');
        $this->assertResponseOk();

        $link = $crawler->selectLink('Новый курс')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $code = 'my_example_code';

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
        $client = $this->authAdmin();
        // от списка курсов переходим на страницу редактирования курса
        $crawler = $client->request('GET', '/courses/');
        $this->assertResponseOk();

        $link = $crawler->filter('.course-show')->first()->link();
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
        $this->assertRouteSame('app_course_show', ['id' => $courseId]);
        $this->assertResponseOk();

        // проверяем изменение данных
        $this->assertSame($crawler->filter('.course-name')->text(), 'Course name for test');
        $this->assertSame($crawler->filter('.card-text')->text(), 'Description course for test');
        $client->submitForm('Удалить');
        self::assertSame($client->getResponse()->headers->get('location'), '/courses/');
        $this->assertResponseRedirect();
    }

    public function testCourseFailedEditing(): void
    {
        $client = $this->authAdmin();
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
        $client = $this->authAdmin();
        // страница со списком курсов
        $crawler = $client->request('GET', '/courses/');
        $this->assertResponseOk();

        // подсчитываем количество курсов 
        $coursesCount = count(self::getEntityManager()->getRepository(Course::class)->findAll());

        // $this->assertSame($crawler->filter('.course-name')->text(), 'Course name for test');

        // заходим 
        $link = $crawler->filter('.course-show')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // получаем курс, который удалим
        $courseName = $crawler->filter('.course-name')->text();
        $course = self::getEntityManager()->getRepository(Course::class)->findOneBy([
            'name' => $courseName,
        ]);
        // dd($course);

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

        $container->set(BillingClient::class,
           new BillingClientMock($this->serializer));

        return $client;
    }

    protected function getFixtures(): array
    {
        return [AppFixtures::class];
    }

    private function authAdmin()
    {
        $auth = new AuthAdmin();
        return $auth->auth();
    }
    
}
