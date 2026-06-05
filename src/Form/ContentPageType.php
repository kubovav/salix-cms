<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\ContentPage;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class ContentPageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'constraints' => [
                    new NotBlank(message: 'Title is required.'),
                    new Length(max: 255),
                ],
                'attr' => ['class' => 'form-control', 'placeholder' => 'Page title'],
            ])
            ->add('slug', TextType::class, [
                'constraints' => [
                    new NotBlank(message: 'Slug is required.'),
                    new Length(max: 180),
                    new Regex(
                        pattern: '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                        message: 'Slug may only contain lowercase letters, numbers, and hyphens.',
                    ),
                ],
                'attr' => ['class' => 'form-control', 'placeholder' => 'my-article-slug'],
            ])
            ->add('content', TextareaType::class, [
                'constraints' => [
                    new NotBlank(message: 'Content is required.'),
                ],
                'attr' => ['class' => 'form-control', 'rows' => 12],
            ])
            ->add('published', CheckboxType::class, [
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ContentPage::class,
        ]);
    }
}
