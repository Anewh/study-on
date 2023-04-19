<?php

namespace App\Tests\Controller;

use App\DataFixtures\AppFixtures;
use App\Entity\Course;
use App\Tests\AbstractTest;

class CourseControllerTest extends AbstractTest
{
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
        // yield ['/'];
        yield ['/courses/'];
        yield ['/courses/new'];
    }

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

    public function testPostActionsResponseOk(): void
    {
        $client = self::getClient();
        $courses = self::getEntityManager()->getRepository(Course::class)->findAll();
        foreach ($courses as $course) {
            // детальная страница
            $client->request('POST', '/courses/' . $course->getId() . '/edit');
            $this->assertResponseOk();

            // страница добавления урока
            $client->request('POST', '/lessons/new?course_id=' . $course->getId());
            $this->assertResponseOk();
        }
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
        // от списка курсов переходим на страницу создания курса
        $client = self::getClient();
        $crawler = $client->request('GET', '/courses/');
        $this->assertResponseOk();

        $link = $crawler->selectLink('Новый курс')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // заполняем форму создания курса корректными данными и отправляем
        $submitBtn = $crawler->selectButton('Сохранить');
        $courseCreatingForm = $submitBtn->form([
            'course[code]' => 'unique-code1',
            'course[name]' => 'Course name for test',
            'course[description]' => 'Course description for test',
        ]);
        $client->submit($courseCreatingForm);

        $course = self::getEntityManager()->getRepository(Course::class)->findOneBy([
            'code' => 'unique-code1',
        ]);

        // проверяем редирект
        self::assertSame($client->getResponse()->headers->get('location'), '/courses/' . $course->getId());
        $crawler = $client->followRedirect();
        $this->assertResponseOk();

        // проверяем корректность отображения данных 
        $this->assertSame($crawler->filter('.course-name')->text(), $course->getName());
        $this->assertSame($crawler->filter('.card-text')->text(), $course->getDescription());
    }

    public function testCourseCreatingWithEmptyCode(): void
    {
        // от списка курсов переходим на страницу создания курса
        $client = self::getClient();
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
        $this->assertResponseCode(422);   

        // Проверяем наличие сообщения об ошибке
        self::assertSelectorTextContains(
            '.invalid-feedback.d-block',
            'Символьный код не может быть пустым'
        );

    }

    public function testCourseCreatingWithEmptyName(): void
    {
        // от списка курсов переходим на страницу создания курса
        $client = self::getClient();
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
            'Название курса не может быть пустым'
        );
    }

    public function testCourseCreatingWithNotUniqueCode(): void
    {
        // от списка курсов переходим на страницу создания курса
        $client = self::getClient();
        $crawler = $client->request('GET', '/courses/');
        $this->assertResponseOk();

        $link = $crawler->selectLink('Новый курс')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // заполнили форму и отправили с не уникальным кодом
        $submitBtn = $crawler->selectButton('Сохранить');
        $courseCreatingForm = $submitBtn->form([
            'course[code]' => 'nympydata',
            'course[name]' => 'Course name for test',
            'course[description]' => 'Description course for test',
        ]);
        $client->submit($courseCreatingForm);

        // Проверяем наличие сообщения об ошибке
        $this->assertResponseCode(422);
    }

    public function testCourseSuccessfulEditing(): void
    {
        // от списка курсов переходим на страницу редактирования курса
        $client = self::getClient();
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
    }

    public function testCourseFailedEditing(): void
    {
        // со страницы списка курсов
        $client = self::getClient();
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

        // пробуем сохранить курс с существующим кодом
        $form['course[code]'] = 'figmadesign';
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
        // страница со списком курсов
        $client = self::getClient();
        $crawler = $client->request('GET', '/courses/');
        $this->assertResponseOk();

        // подсчитываем количество курсов 
        $coursesCount = count(self::getEntityManager()->getRepository(Course::class)->findAll());

        // заходим 
        $link = $crawler->filter('.course-show')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $client->submitForm('Удалить');
        self::assertSame($client->getResponse()->headers->get('location'), '/courses/');
        $crawler = $client->followRedirect();

        $coursesCountAfterDelete = count(self::getEntityManager()->getRepository(Course::class)->findAll());
        
        // проверка соответствия кол-ва курсов
        self::assertSame($coursesCount - 1, $coursesCountAfterDelete);
        //dd($coursesCountAfterDelete);
        self::assertCount($coursesCountAfterDelete, $crawler->filter('.card-body'));
    }

    protected function getFixtures(): array
    {
        return [AppFixtures::class];
    }
}