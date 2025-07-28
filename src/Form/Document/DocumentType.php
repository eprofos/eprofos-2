<?php

declare(strict_types=1);

namespace App\Form\Document;

use App\Entity\Document\Document;
use App\Entity\Document\DocumentCategory;
use App\Entity\Document\DocumentType as DocumentTypeEntity;
use App\Form\DataTransformer\TagsTransformer;
use App\Repository\Document\DocumentCategoryRepository;
use App\Repository\Document\DocumentTypeRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Form type for Document entity.
 *
 * Provides a complex form with dynamic fields based on document type.
 * Handles type-specific validation and field visibility.
 */
class DocumentType extends AbstractType
{
    /**
     * Build the document form with dynamic fields.
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Document|null $document */
        $document = $options['data'];

        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'help' => 'Le titre du document (visible publiquement)',
                'attr' => [
                    'placeholder' => 'Saisissez le titre du document',
                    'class' => 'form-control',
                    'maxlength' => 255,
                ],
                'constraints' => [
                    new NotBlank(message: 'Le titre est obligatoire.'),
                    new Length(
                        min: 3,
                        max: 255,
                        minMessage: 'Le titre doit contenir au moins {{ limit }} caractères.',
                        maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères.',
                    ),
                ],
            ])
            ->add('slug', TextType::class, [
                'label' => 'Slug',
                'help' => 'URL-friendly version du titre (généré automatiquement si vide)',
                'required' => false,
                'attr' => [
                    'placeholder' => 'URL-friendly version du titre',
                    'class' => 'form-control',
                    'maxlength' => 500,
                ],
                'constraints' => [
                    new Length(
                        min: 3,
                        max: 500,
                        minMessage: 'Le slug doit contenir au moins {{ limit }} caractères.',
                        maxMessage: 'Le slug ne peut pas dépasser {{ limit }} caractères.',
                    ),
                    new Regex(
                        pattern: '/^[a-z0-9\-\/]+$/',
                        message: 'Le slug ne peut contenir que des lettres minuscules, chiffres, tirets et slashes.',
                    ),
                ],
            ])
            ->add('documentType', EntityType::class, [
                'class' => DocumentTypeEntity::class,
                'choice_label' => 'name',
                'label' => 'Type de document',
                'help' => 'Sélectionnez le type de document',
                'placeholder' => 'Choisissez un type...',
                'attr' => ['class' => 'form-select'],
                'query_builder' => static fn (DocumentTypeRepository $repo) => $repo->createQueryBuilder('dt')
                    ->where('dt.isActive = :active')
                    ->setParameter('active', true)
                    ->orderBy('dt.sortOrder', 'ASC')
                    ->addOrderBy('dt.name', 'ASC'),
                'constraints' => [
                    new NotBlank(message: 'Le type de document est obligatoire.'),
                ],
            ])
            ->add('category', EntityType::class, [
                'class' => DocumentCategory::class,
                'choice_label' => 'name',
                'label' => 'Catégorie',
                'help' => 'Catégorie pour organiser le document',
                'required' => false,
                'placeholder' => 'Aucune catégorie',
                'attr' => ['class' => 'form-select'],
                'query_builder' => static fn (DocumentCategoryRepository $repo) => $repo->createQueryBuilder('dc')
                    ->where('dc.isActive = :active')
                    ->setParameter('active', true)
                    ->orderBy('dc.level', 'ASC')
                    ->addOrderBy('dc.sortOrder', 'ASC')
                    ->addOrderBy('dc.name', 'ASC'),
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'help' => 'Description courte du document (visible dans les listes)',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Description du document...',
                    'class' => 'form-control',
                    'rows' => 3,
                    'maxlength' => 1000,
                ],
                'constraints' => [
                    new Length(
                        max: 1000,
                        maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères.',
                    ),
                ],
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Contenu',
                'help' => 'Contenu principal du document',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Contenu du document...',
                    'class' => 'form-control editor',
                    'rows' => 15,
                    'data-editor' => 'true',
                ],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'help' => 'Statut actuel du document',
                'choices' => Document::STATUSES,
                'attr' => ['class' => 'form-select'],
                'constraints' => [
                    new NotBlank(message: 'Le statut est obligatoire.'),
                ],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Document actif',
                'help' => 'Décochez pour désactiver temporairement le document',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
            ])
            ->add('isPublic', CheckboxType::class, [
                'label' => 'Document public',
                'help' => 'Cochez pour rendre le document visible publiquement',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
            ])
            ->add('tags', TextType::class, [
                'label' => 'Tags',
                'help' => 'Mots-clés séparés par des virgules',
                'required' => false,
                'attr' => [
                    'placeholder' => 'tag1, tag2, tag3...',
                    'class' => 'form-control',
                    'data-tags' => 'true',
                ],
            ])
            ->add('expiresAt', DateTimeType::class, [
                'label' => 'Date d\'expiration',
                'help' => 'Date optionnelle d\'expiration du document',
                'required' => false,
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control',
                    'type' => 'datetime-local',
                ],
            ])
            ->add('tags', TextType::class, [
                'label' => 'Tags',
                'help' => 'Mots-clés séparés par des virgules',
                'required' => false,
                'attr' => [
                    'placeholder' => 'tag1, tag2, tag3...',
                    'class' => 'form-control',
                    'data-tags' => 'true',
                ],
            ])
        ;

        // Add version management fields (only for existing documents)
        if ($document && $document->getId()) {
            $builder
                ->add('versionType', ChoiceType::class, [
                    'label' => 'Type de modification',
                    'help' => 'Indiquez le type de changement pour la gestion des versions',
                    'mapped' => false,
                    'required' => false,
                    'choices' => [
                        'Modification mineure (correction, mise à jour légère)' => 'minor',
                        'Modification majeure (changement significatif)' => 'major',
                        'Pas de nouvelle version (modification technique)' => 'none',
                    ],
                    'data' => 'minor', // Default to minor
                    'attr' => ['class' => 'form-select'],
                    'expanded' => true,
                    'multiple' => false,
                ])
                ->add('versionMessage', TextareaType::class, [
                    'label' => 'Message de version',
                    'help' => 'Décrivez les modifications apportées (obligatoire pour les nouvelles versions)',
                    'mapped' => false,
                    'required' => false,
                    'attr' => [
                        'placeholder' => 'Ex: Mise à jour des procédures, correction des liens, ajout de nouvelles sections...',
                        'class' => 'form-control',
                        'rows' => 3,
                        'maxlength' => 500,
                    ],
                    'constraints' => [
                        new Length(
                            max: 500,
                            maxMessage: 'Le message de version ne peut pas dépasser {{ limit }} caractères.',
                        ),
                    ],
                ])
            ;
        }

        // Add transformer for tags field to convert between array and string
        $builder->get('tags')->addModelTransformer(new TagsTransformer());

        // Add dynamic fields based on document type
        $builder->addEventListener(FormEvents::PRE_SET_DATA, [$this, 'onPreSetData']);
        $builder->addEventListener(FormEvents::PRE_SUBMIT, [$this, 'onPreSubmit']);
    }

    /**
     * Handle pre-set data to modify form based on document type.
     */
    public function onPreSetData(FormEvent $event): void
    {
        $document = $event->getData();
        $form = $event->getForm();

        if (!$document instanceof Document) {
            return;
        }

        $documentType = $document->getDocumentType();
        if (!$documentType) {
            return;
        }

        // Modify status choices based on document type
        $this->addStatusChoices($form, $documentType);

        // Add expiration field if document type supports it
        $this->addExpirationField($form, $documentType);

        // Add custom metadata fields
        $this->addMetadataFields($form, $documentType);
    }

    /**
     * Handle pre-submit to process dynamic fields.
     */
    public function onPreSubmit(FormEvent $event): void
    {
        $data = $event->getData();
        $form = $event->getForm();

        if (!isset($data['documentType']) || !$data['documentType']) {
            return;
        }

        // For simplicity, we'll handle this in the controller
        // In a more complex scenario, you could fetch the document type here
        // and modify the form accordingly
    }

    /**
     * Configure form options.
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Document::class,
            'validation_groups' => ['Default', 'Document'],
            'attr' => [
                'novalidate' => 'novalidate',
                'data-form' => 'document',
            ],
        ]);
    }

    /**
     * Form type name for Twig.
     */
    public function getBlockPrefix(): string
    {
        return 'document';
    }

    /**
     * Add status choices based on document type configuration.
     *
     * @param mixed $form
     */
    private function addStatusChoices($form, DocumentTypeEntity $documentType): void
    {
        $allowedStatuses = $documentType->getAllowedStatuses();

        if ($allowedStatuses && !empty($allowedStatuses)) {
            $statusChoices = [];
            foreach ($allowedStatuses as $status) {
                if (isset(Document::STATUSES[$status])) {
                    $statusChoices[$status] = Document::STATUSES[$status];
                }
            }

            if (!empty($statusChoices)) {
                $form->add('status', ChoiceType::class, [
                    'label' => 'Statut',
                    'help' => 'Statut actuel du document (limité par le type)',
                    'choices' => array_flip($statusChoices),
                    'attr' => ['class' => 'form-select'],
                    'constraints' => [
                        new NotBlank(message: 'Le statut est obligatoire.'),
                    ],
                ]);
            }
        }
    }

    /**
     * Add expiration field if document type supports expiration.
     *
     * @param mixed $form
     */
    private function addExpirationField($form, DocumentTypeEntity $documentType): void
    {
        if (!$documentType->isHasExpiration()) {
            $form->remove('expiresAt');
        }
    }

    /**
     * Add custom metadata fields based on document type configuration.
     *
     * @param mixed $form
     */
    private function addMetadataFields($form, DocumentTypeEntity $documentType): void
    {
        $requiredMetadata = $documentType->getRequiredMetadata();

        if (!$requiredMetadata || empty($requiredMetadata)) {
            return;
        }

        foreach ($requiredMetadata as $metaKey => $metaConfig) {
            if (!is_array($metaConfig)) {
                continue;
            }

            $fieldType = TextType::class;
            $fieldOptions = [
                'label' => $metaConfig['label'] ?? ucfirst($metaKey),
                'help' => $metaConfig['help'] ?? null,
                'required' => $metaConfig['required'] ?? false,
                'mapped' => false,
                'attr' => [
                    'class' => 'form-control',
                    'data-meta-key' => $metaKey,
                ],
            ];

            // Handle different field types
            switch ($metaConfig['type'] ?? 'text') {
                case 'textarea':
                    $fieldType = TextareaType::class;
                    $fieldOptions['attr']['rows'] = $metaConfig['rows'] ?? 4;
                    break;

                case 'choice':
                    $fieldType = ChoiceType::class;
                    $fieldOptions['choices'] = array_flip($metaConfig['choices'] ?? []);
                    $fieldOptions['attr']['class'] = 'form-select';
                    break;

                case 'checkbox':
                    $fieldType = CheckboxType::class;
                    $fieldOptions['attr']['class'] = 'form-check-input';
                    break;

                case 'datetime':
                    $fieldType = DateTimeType::class;
                    $fieldOptions['widget'] = 'single_text';
                    $fieldOptions['attr']['type'] = 'datetime-local';
                    break;
            }

            $form->add('meta_' . $metaKey, $fieldType, $fieldOptions);
        }
    }
}
