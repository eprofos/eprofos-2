<?php

namespace App\Form\Document;

use App\Entity\Document\DocumentCategory;
use App\Repository\Document\DocumentCategoryRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Form type for DocumentCategory entity
 * 
 * Provides form fields for creating and editing document categories
 * with hierarchical parent selection and proper validation.
 */
class DocumentCategoryType extends AbstractType
{
    /**
     * Build the document category form
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var DocumentCategory|null $category */
        $category = $options['data'];

        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom de la catégorie',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Politiques internes'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Le nom de la catégorie est obligatoire.'
                    ]),
                    new Length([
                        'min' => 2,
                        'max' => 255,
                        'minMessage' => 'Le nom doit contenir au moins {{ limit }} caractères.',
                        'maxMessage' => 'Le nom ne peut pas dépasser {{ limit }} caractères.'
                    ])
                ]
            ])
            ->add('slug', TextType::class, [
                'label' => 'Slug',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: politiques-internes'
                ],
                'help' => 'URL-friendly identifier (généré automatiquement si vide)',
                'constraints' => [
                    new Length([
                        'max' => 500,
                        'maxMessage' => 'Le slug ne peut pas dépasser {{ limit }} caractères.'
                    ]),
                    new Regex([
                        'pattern' => '/^[a-z0-9\-\/]*$/',
                        'message' => 'Le slug ne peut contenir que des lettres minuscules, chiffres, tirets et slashes.'
                    ])
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Description de la catégorie'
                ],
                'constraints' => [
                    new Length([
                        'max' => 1000,
                        'maxMessage' => 'La description ne peut pas dépasser {{ limit }} caractères.'
                    ])
                ]
            ])
            ->add('parent', EntityType::class, [
                'class' => DocumentCategory::class,
                'choice_label' => function (DocumentCategory $category) {
                    return str_repeat('-- ', $category->getLevel()) . $category->getName();
                },
                'placeholder' => 'Aucune (catégorie racine)',
                'required' => false,
                'label' => 'Catégorie parente',
                'attr' => [
                    'class' => 'form-select'
                ],
                'help' => 'Catégorie parente pour créer une hiérarchie',
                'query_builder' => function (DocumentCategoryRepository $repository) use ($category) {
                    $qb = $repository->createQueryBuilder('c')
                        ->where('c.isActive = :active')
                        ->setParameter('active', true)
                        ->orderBy('c.level', 'ASC')
                        ->addOrderBy('c.sortOrder', 'ASC')
                        ->addOrderBy('c.name', 'ASC');

                    // Exclude self and descendants to prevent circular references
                    if ($category && $category->getId()) {
                        $qb->andWhere('c.id != :current_id')
                           ->setParameter('current_id', $category->getId());

                        // TODO: Also exclude descendants - this would require a more complex query
                    }

                    return $qb;
                }
            ])
            ->add('icon', TextType::class, [
                'label' => 'Icône',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: fas fa-folder'
                ],
                'help' => 'Classe CSS pour l\'icône (Font Awesome, Bootstrap Icons, etc.)',
                'constraints' => [
                    new Length([
                        'max' => 100,
                        'maxMessage' => 'L\'icône ne peut pas dépasser {{ limit }} caractères.'
                    ])
                ]
            ])
            ->add('color', TextType::class, [
                'label' => 'Couleur',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'type' => 'color',
                    'placeholder' => '#007bff'
                ],
                'help' => 'Couleur associée à la catégorie',
                'constraints' => [
                    new Length([
                        'max' => 50,
                        'maxMessage' => 'La couleur ne peut pas dépasser {{ limit }} caractères.'
                    ])
                ]
            ])
            ->add('sortOrder', IntegerType::class, [
                'label' => 'Ordre de tri',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '0'
                ],
                'help' => 'Ordre d\'affichage dans la catégorie parente (0 = automatique)',
                'constraints' => [
                    new Range([
                        'min' => 0,
                        'max' => 999,
                        'notInRangeMessage' => 'L\'ordre doit être compris entre {{ min }} et {{ max }}.'
                    ])
                ]
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Catégorie active',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'help' => 'Une catégorie inactive ne sera pas visible'
            ]);
    }

    /**
     * Configure form options
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DocumentCategory::class,
            'attr' => [
                'novalidate' => 'novalidate'
            ]
        ]);
    }
}
