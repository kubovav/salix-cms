<?php

declare(strict_types=1);

namespace App\Form\Block;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class CtaBlockType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('heading', TextType::class, [
                'label' => 'Heading',
                'constraints' => [new NotBlank(message: 'Heading is required.')],
                'attr' => ['class' => 'form-control', 'placeholder' => 'Call to action heading'],
            ])
            ->add('button_text', TextType::class, [
                'label' => 'Button text',
                'constraints' => [new NotBlank(message: 'Button text is required.')],
                'attr' => ['class' => 'form-control', 'placeholder' => 'e.g. Learn more'],
            ])
            ->add('button_url', UrlType::class, [
                'label' => 'Button URL',
                'constraints' => [new NotBlank(message: 'Button URL is required.')],
                'default_protocol' => null,
                'attr' => ['class' => 'form-control', 'placeholder' => '/page or https://...'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null]);
    }
}
