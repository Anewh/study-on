<?php

namespace App\Form;

use App\Entity\Lesson;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;

class LessonType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('course_id', HiddenType::class, [
                'data' => $options['course_id'],
                'mapped' => false,
            ])
            ->add('name', TextType::class, [
                'label' => 'Название',
                'required' => true,
                'constraints' => [new Length(['max' => 255])],
                'attr' => ['class ' => 'form-control']
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Содержание',
                'required' => true,
                'constraints' => [new Length(['max' => 1000])],
                'attr' => ['class ' => 'form-control']
            ])
            ->add('serial', NumberType::class, [
                'label' => 'Порядковый номер'
            ]);
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