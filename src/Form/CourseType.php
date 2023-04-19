<?php

namespace App\Form;

use App\Entity\Course;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;

class CourseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', TextType::class, [
                'label' => 'Код',
                'constraints' => [
                    new NotBlank(message: 'Символьный код не может быть пустым'),
                    new Length(['max' => 255], maxMessage: 'Значение не должно превышать {{ limit }}')
                ],
                'empty_data' => ''
            ])
            ->add('name', TextType::class, [
                'label' => 'Название',
                'constraints' => [
                    new Length(['max' => 255], maxMessage: 'Значение не должно превышать {{ limit }}'),
                    new NotBlank(message: 'Название курса не может быть пустым')
                ],
                'empty_data' => ''
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'label' => 'Описание',
                'constraints' => [
                    new Length(['max' => 1000], maxMessage: 'Значение не должно превышать {{ limit }}')
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Course::class,
        ]);
    }
}