<?php

namespace App\Tests\Controller;

use App\DataFixtures\AppFixtures;
use App\Entity\Course;
use App\Entity\Lesson;
use App\Tests\AbstractTest;

class LessonControllerTest extends AbstractTest
{
    public function testGetActionsResponseOk(): void
    {
        $client = self::getClient();
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

    public function testPostActionsResponseOk(): void
    {
        $client = self::getClient();
        $lessons = self::getEntityManager()->getRepository(Lesson::class)->findAll();
        foreach ($lessons as $lesson) {
            $client->request('POST', '/lessons/' . $lesson->getId() . '/edit');
            $this->assertResponseOk();
        }
    }

    public function testSuccessfulLessonCreating(): void
    {
        // от списка курсов переходим на страницу просмотра курса
        $client = self::getClient();
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

        // проверяем редирект
        self::assertSame($client->getResponse()->headers->get('location'), '/courses/' . $course->getId());
        $crawler = $client->followRedirect();

        $this->assertResponseOk();
        $this->assertSame($crawler->filter('.lesson')->last()->text(), 'Lesson for test');

        $link = $crawler->filter('.lesson')->last()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // проверим название и содержание
        $this->assertSame($crawler->filter('.lesson-name')->first()->text(), 'Lesson for test');
        $this->assertSame($crawler->filter('.content')->first()->text(), 'Some content in test for lesson');
    }

    public function testLessonCreatingWithEmptyName(): void
    {
        // от списка курсов переходим на страницу просмотра курса
        $client = self::getClient();
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
        // от списка курсов переходим на страницу просмотра курса
        $client = self::getClient();
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
        // от списка курсов переходим на страницу просмотра курса
        $client = self::getClient();
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

    public function testSuccessfulLessonEditing(): void
    {
        // от списка курсов переходим на страницу просмотра курса
        $client = self::getClient();
        $crawler = $client->request('GET', '/courses/');
        $this->assertResponseOk();

        // на детальную страницу курса
        $link = $crawler->filter('.course-show')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // переходим к деталям урока
        $link = $crawler->filter('.lesson')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $link = $crawler->selectLink('Редактировать')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $form = $crawler->selectButton('Сохранить')->form();

        // сохраняем редактируемый курс
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
        // от списка курсов переходим на страницу просмотра курса
        $client = self::getClient();
        $crawler = $client->request('GET', '/courses/');
        $this->assertResponseOk();

        // на детальную страницу курса
        $link = $crawler->filter('.course-show')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // переходим к деталям урока
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

        // сравнение количества уроков
        self::assertCount($countBeforeDeleting - 1, $crawler->filter('.lesson'));
    }

    protected function getFixtures(): array
    {
        return [AppFixtures::class];
    }
}