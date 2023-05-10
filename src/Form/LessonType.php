<?php

namespace App\Form;

use App\Entity\Course;
use App\Entity\Lesson;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LessonType extends AbstractType
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('course_id', HiddenType::class, [
                'data' => $options['course_id'],
                'mapped' => false,
            ])
            ->add('name', TextType::class, [
                'label' => 'Название',
                'attr' => ['class ' => 'form-control'],
                'empty_data' => ''
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Содержание',
                'attr' => ['class ' => 'form-control']
            ])
            ->add('serial', NumberType::class, [
                'label' => 'Порядковый номер',
                'attr' => [
                    'max' => 10000,
                    'min' => 1,
                ]
            ])
            ->add('course', HiddenType::class)
        ;
        $builder->get('course')
            ->addModelTransformer(
                new CallbackTransformer(
                    function ($courseAsObj): string {
                        return $courseAsObj->getId();
                    },
                    function ($courseId): Course {
                        return $this->entityManager
                            ->getRepository(Course::class)
                            ->find($courseId);
                    }
                )
            )
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Lesson::class,
            'course_id' => 0,
        ]);
        $resolver->setAllowedTypes('course_id', 'int');
    }
}