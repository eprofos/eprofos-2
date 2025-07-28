<?php

namespace App\Form\Training;

use App\Entity\Training\Chapter;
use App\Entity\Training\Module;
use App\Repository\Training\ModuleRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ChapterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre du chapitre',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Introduction aux concepts de base'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le titre est obligatoire']),
                    new Assert\Length(['max' => 255, 'maxMessage' => 'Le titre ne peut pas dépasser 255 caractères'])
                ]
            ])
            ->add('slug', TextType::class, [
                'label' => 'Slug (URL)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Généré automatiquement si vide'
                ],
                'help' => 'Laissez vide pour générer automatiquement à partir du titre'
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Description détaillée du chapitre...'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'La description est obligatoire'])
                ]
            ])
            ->add('contentOutline', TextareaType::class, [
                'label' => 'Plan du contenu',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 5,
                    'placeholder' => 'Structure détaillée du contenu du chapitre...'
                ],
                'help' => 'Plan structuré avec les points clés et sous-points à couvrir (requis par Qualiopi)'
            ])
            ->add('learningObjectives', CollectionType::class, [
                'label' => 'Objectifs d\'apprentissage',
                'entry_type' => TextType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'required' => false,
                'entry_options' => [
                    'attr' => [
                        'class' => 'form-control',
                        'placeholder' => 'Ex: Comprendre les concepts fondamentaux...'
                    ]
                ],
                'attr' => [
                    'data-controller' => 'collection',
                    'data-collection-index-value' => 0,
                    'data-collection-prototype-name-value' => '__name__',
                    'data-add-text' => 'Ajouter un objectif'
                ],
                'help' => 'Objectifs spécifiques et mesurables pour ce chapitre (requis par Qualiopi)'
            ])
            ->add('learningOutcomes', CollectionType::class, [
                'label' => 'Résultats d\'apprentissage attendus',
                'entry_type' => TextType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'required' => false,
                'entry_options' => [
                    'attr' => [
                        'class' => 'form-control',
                        'placeholder' => 'Ex: Être capable de...'
                    ]
                ],
                'attr' => [
                    'data-controller' => 'collection',
                    'data-collection-index-value' => 0,
                    'data-collection-prototype-name-value' => '__name__',
                    'data-add-text' => 'Ajouter un résultat'
                ],
                'help' => 'Ce que les participants sauront ou pourront faire après ce chapitre'
            ])
            ->add('prerequisites', TextareaType::class, [
                'label' => 'Prérequis',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Connaissances ou compétences requises...'
                ],
                'help' => 'Prérequis spécifiques nécessaires pour ce chapitre'
            ])
            ->add('teachingMethods', TextareaType::class, [
                'label' => 'Méthodes pédagogiques',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Méthodes d\'enseignement utilisées...'
                ],
                'help' => 'Approches pédagogiques employées dans ce chapitre (requis par Qualiopi)'
            ])
            ->add('assessmentMethods', TextareaType::class, [
                'label' => 'Méthodes d\'évaluation',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Comment l\'apprentissage est évalué...'
                ],
                'help' => 'Méthodes d\'évaluation utilisées pour ce chapitre (requis par Qualiopi)'
            ])
            ->add('resources', CollectionType::class, [
                'label' => 'Ressources et matériaux',
                'entry_type' => TextType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'required' => false,
                'entry_options' => [
                    'attr' => [
                        'class' => 'form-control',
                        'placeholder' => 'Ex: Support PDF, Vidéo explicative...'
                    ]
                ],
                'attr' => [
                    'data-controller' => 'collection',
                    'data-collection-index-value' => 0,
                    'data-collection-prototype-name-value' => '__name__',
                    'data-add-text' => 'Ajouter une ressource'
                ],
                'help' => 'Matériaux pédagogiques utilisés dans ce chapitre'
            ])
            ->add('successCriteria', CollectionType::class, [
                'label' => 'Critères de réussite',
                'entry_type' => TextType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'required' => false,
                'entry_options' => [
                    'attr' => [
                        'class' => 'form-control',
                        'placeholder' => 'Ex: Obtenir 80% aux exercices...'
                    ]
                ],
                'attr' => [
                    'data-controller' => 'collection',
                    'data-collection-index-value' => 0,
                    'data-collection-prototype-name-value' => '__name__',
                    'data-add-text' => 'Ajouter un critère'
                ],
                'help' => 'Indicateurs mesurables de réussite du chapitre'
            ])
            ->add('durationMinutes', IntegerType::class, [
                'label' => 'Durée (en minutes)',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: 90'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'La durée est obligatoire']),
                    new Assert\Positive(['message' => 'La durée doit être positive'])
                ]
            ])
            ->add('orderIndex', IntegerType::class, [
                'label' => 'Ordre d\'affichage',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Position dans le module'
                ],
                'help' => 'Laissez vide pour placer à la fin'
            ])
            ->add('module', EntityType::class, [
                'class' => Module::class,
                'choice_label' => function (Module $module) {
                    return $module->getFormation()->getTitle() . ' - ' . $module->getTitle();
                },
                'placeholder' => 'Sélectionner un module',
                'query_builder' => function (ModuleRepository $repository) {
                    return $repository->createQueryBuilder('m')
                        ->join('m.formation', 'f')
                        ->where('m.isActive = true')
                        ->orderBy('f.title', 'ASC')
                        ->addOrderBy('m.orderIndex', 'ASC');
                },
                'attr' => [
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le module est obligatoire'])
                ]
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Actif',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'help' => 'Décochez pour désactiver ce chapitre'
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Chapter::class,
        ]);
    }
}
