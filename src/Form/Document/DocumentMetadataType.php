<?php

declare(strict_types=1);

namespace App\Form\Document;

use App\Entity\Document\Document;
use App\Entity\Document\DocumentMetadata;
use App\Repository\Document\DocumentRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Document Metadata Form.
 *
 * Form for creating and editing document metadata.
 * Includes fields for key, value, data type, and validation rules.
 */
class DocumentMetadataType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('document', EntityType::class, [
                'class' => Document::class,
                'choice_label' => static fn (Document $document) => $document->getTitle() . ' (' . ($document->getDocumentType()?->getName() ?? 'Sans type') . ')',
                'choice_value' => 'id',
                'label' => 'Document',
                'help' => 'Document auquel cette métadonnée est associée',
                'placeholder' => 'Sélectionnez un document',
                'attr' => [
                    'class' => 'form-select',
                ],
                'query_builder' => static fn (DocumentRepository $repository) => $repository->createQueryBuilder('d')
                    ->leftJoin('d.documentType', 'dt')
                    ->addSelect('dt')
                    ->orderBy('d.title', 'ASC'),
                'constraints' => [
                    new Assert\NotNull(message: 'Le document est obligatoire.'),
                ],
            ])

            ->add('metaKey', TextType::class, [
                'label' => 'Clé de métadonnée',
                'help' => 'Identifiant unique de la métadonnée (lettres minuscules, chiffres et underscores)',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'ex: duree_formation, niveau_requis, responsable_pedagogique',
                    'pattern' => '[a-z0-9_]+',
                    'list' => 'metadata-keys-list',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'La clé est obligatoire.'),
                    new Assert\Length(
                        min: 1,
                        max: 100,
                        minMessage: 'La clé doit contenir au moins {{ limit }} caractère.',
                        maxMessage: 'La clé ne peut pas dépasser {{ limit }} caractères.',
                    ),
                    new Assert\Regex(
                        pattern: '/^[a-z0-9_]+$/',
                        message: 'La clé ne peut contenir que des lettres minuscules, chiffres et underscores.',
                    ),
                ],
            ])

            ->add('metaValue', TextareaType::class, [
                'label' => 'Valeur',
                'help' => 'Valeur de la métadonnée selon le type de données sélectionné',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Valeur de la métadonnée...',
                ],
            ])

            ->add('dataType', ChoiceType::class, [
                'label' => 'Type de données',
                'help' => 'Type de données pour la validation et l\'affichage',
                'choices' => [
                    'Texte court' => DocumentMetadata::TYPE_STRING,
                    'Texte long' => DocumentMetadata::TYPE_TEXT,
                    'Nombre entier' => DocumentMetadata::TYPE_INTEGER,
                    'Nombre décimal' => DocumentMetadata::TYPE_FLOAT,
                    'Booléen (Oui/Non)' => DocumentMetadata::TYPE_BOOLEAN,
                    'Date' => DocumentMetadata::TYPE_DATE,
                    'Date et heure' => DocumentMetadata::TYPE_DATETIME,
                    'JSON' => DocumentMetadata::TYPE_JSON,
                    'Fichier' => DocumentMetadata::TYPE_FILE,
                    'URL' => DocumentMetadata::TYPE_URL,
                ],
                'attr' => [
                    'class' => 'form-select',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le type de données est obligatoire.'),
                ],
            ])

            ->add('displayName', TextType::class, [
                'label' => 'Nom d\'affichage',
                'help' => 'Nom convivial pour l\'affichage (optionnel, la clé sera utilisée si vide)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'ex: Durée de formation, Niveau requis, Responsable pédagogique',
                ],
            ])

            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'help' => 'Description détaillée de cette métadonnée et de son utilisation',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Description de l\'utilisation de cette métadonnée...',
                ],
            ])

            ->add('isRequired', ChoiceType::class, [
                'label' => 'Obligatoire',
                'help' => 'Cette métadonnée est-elle obligatoire ?',
                'choices' => [
                    'Oui' => true,
                    'Non' => false,
                ],
                'expanded' => true,
                'multiple' => false,
                'data' => false,
                'attr' => [
                    'class' => 'form-check-input',
                ],
            ])

            ->add('isSearchable', ChoiceType::class, [
                'label' => 'Recherchable',
                'help' => 'Cette métadonnée peut-elle être utilisée pour la recherche ?',
                'choices' => [
                    'Oui' => true,
                    'Non' => false,
                ],
                'expanded' => true,
                'multiple' => false,
                'data' => true,
                'attr' => [
                    'class' => 'form-check-input',
                ],
            ])

            ->add('isEditable', ChoiceType::class, [
                'label' => 'Modifiable',
                'help' => 'Cette métadonnée peut-elle être modifiée après création ?',
                'choices' => [
                    'Oui' => true,
                    'Non' => false,
                ],
                'expanded' => true,
                'multiple' => false,
                'data' => true,
                'attr' => [
                    'class' => 'form-check-input',
                ],
            ])

            ->add('sortOrder', IntegerType::class, [
                'label' => 'Ordre de tri',
                'help' => 'Ordre d\'affichage dans les listes (plus petit = affiché en premier)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0,
                    'max' => 9999,
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DocumentMetadata::class,
            'attr' => [
                'novalidate' => 'novalidate',
            ],
        ]);
    }
}
