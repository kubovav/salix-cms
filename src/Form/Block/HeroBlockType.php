<?php

declare(strict_types=1);

namespace App\Form\Block;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;

class HeroBlockType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('heading', TextType::class, [
                'label' => 'Heading',
                'constraints' => [new NotBlank(message: 'Heading is required.')],
                'attr' => ['class' => 'form-control', 'placeholder' => 'Main heading text'],
            ])
            ->add('subtext', TextareaType::class, [
                'label' => 'Subtext (optional)',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 3, 'placeholder' => 'Supporting text beneath the heading'],
            ])
            ->add('cta_text', TextType::class, [
                'label' => 'CTA button text (optional)',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'e.g. Get started'],
            ])
            ->add('cta_url', UrlType::class, [
                'label' => 'CTA button URL (optional)',
                'required' => false,
                'default_protocol' => null,
                'attr' => ['class' => 'form-control', 'placeholder' => '/page or https://...'],
            ])
            ->add('file', FileType::class, [
                'label' => 'Background image (optional)',
                'required' => false,
                'mapped' => false,
                'constraints' => [
                    new File(mimeTypes: ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], mimeTypesMessage: 'Please upload a valid image (jpg, png, gif, webp).'),
                ],
                'attr' => ['class' => 'form-control'],
            ])
            ->add('image_alt', TextType::class, [
                'label' => 'Background image alt text',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Describe the background image'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null]);
    }
}
