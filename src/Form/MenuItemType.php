<?php

namespace App\Form;

use App\Entity\ContentPage;
use App\Entity\MenuItem;
use App\Repository\ContentPageRepository;
use App\Repository\MenuItemRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class MenuItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var MenuItem|null $currentItem */
        $currentItem = $options['data'] ?? null;
        $currentId = $currentItem?->getId();

        $builder
            ->add('label', TextType::class, [
                'constraints' => [
                    new NotBlank(message: 'Label is required.'),
                    new Length(max: 255),
                ],
                'attr' => ['class' => 'form-control', 'placeholder' => 'e.g. About Us'],
            ])
            ->add('menuName', ChoiceType::class, [
                'choices' => [
                    'Main navigation' => 'main',
                    'Footer' => 'footer',
                ],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('page', EntityType::class, [
                'class' => ContentPage::class,
                'choice_label' => 'title',
                'placeholder' => '— link to a CMS page —',
                'required' => false,
                'query_builder' => fn (ContentPageRepository $repo) => $repo->createQueryBuilder('p')
                    ->orderBy('p.title', 'ASC'),
                'attr' => ['class' => 'form-select'],
                'help' => 'Choose a CMS page, or leave empty and fill in the URL field below.',
            ])
            ->add('url', UrlType::class, [
                'required' => false,
                'default_protocol' => null,
                'attr' => ['class' => 'form-control', 'placeholder' => 'https://example.com or /custom-path'],
                'help' => 'Used when no CMS page is selected. Supports absolute and relative URLs.',
            ])
            ->add('parent', EntityType::class, [
                'class' => MenuItem::class,
                'choice_label' => fn (MenuItem $item) => '[' . $item->getMenuName() . '] ' . $item->getLabel(),
                'placeholder' => '— top-level item —',
                'required' => false,
                'query_builder' => function (MenuItemRepository $repo) use ($currentId) {
                    $qb = $repo->createQueryBuilder('m')
                        ->where('m.parent IS NULL')
                        ->orderBy('m.menuName', 'ASC')
                        ->addOrderBy('m.position', 'ASC');

                    if ($currentId !== null) {
                        $qb->andWhere('m.id != :self')->setParameter('self', $currentId);
                    }

                    return $qb;
                },
                'attr' => ['class' => 'form-select'],
                'help' => 'Assign to a parent item to create a dropdown sub-menu entry.',
            ])
            ->add('position', IntegerType::class, [
                'attr' => ['class' => 'form-control', 'min' => 0],
                'help' => 'Lower numbers appear first. Items with the same number are sorted by creation order.',
            ])
            ->add('enabled', CheckboxType::class, [
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MenuItem::class,
        ]);
    }
}
