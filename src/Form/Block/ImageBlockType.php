<?php

declare(strict_types=1);

namespace App\Form\Block;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;

class ImageBlockType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('alt', TextType::class, [
                'label' => 'Alt text',
                'constraints' => [new NotBlank(message: 'Alt text is required.')],
                'attr' => ['class' => 'form-control', 'placeholder' => 'Describe the image'],
            ])
            ->add('caption', TextType::class, [
                'label' => 'Caption (optional)',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Optional caption shown below the image'],
            ])
            ->add('file', FileType::class, [
                'label' => 'Image file',
                'required' => !$options['is_edit'],
                'mapped' => false,
                'constraints' => $options['is_edit'] ? [] : [
                    new NotBlank(message: 'Please upload an image.'),
                    new File(mimeTypes: ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], mimeTypesMessage: 'Please upload a valid image (jpg, png, gif, webp).'),
                ],
                'attr' => ['class' => 'form-control'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'is_edit' => false,
        ]);
    }
}
