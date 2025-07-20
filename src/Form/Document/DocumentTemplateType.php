<?php

namespace App\Form\Document;

use App\Entity\Document\DocumentTemplate;
use App\Entity\Document\DocumentType;
use App\Repository\Document\DocumentTypeRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Document Template Form
 * 
 * Form for creating and editing document templates.
 * Includes fields for template content, placeholders, metadata defaults,
 * and configuration options.
 */
class DocumentTemplateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du modèle',
                'help' => 'Nom descriptif du modèle de document',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Contrat de formation standard'
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le nom est obligatoire.'),
                    new Assert\Length(
                        min: 3,
                        max: 255,
                        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères.',
                        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.'
                    )
                ]
            ])
            
            ->add('slug', TextType::class, [
                'label' => 'Slug',
                'help' => 'Identifiant unique pour le modèle (généré automatiquement)',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'contrat-formation-standard'
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le slug est obligatoire.'),
                    new Assert\Regex(
                        pattern: '/^[a-z0-9\-\/]+$/',
                        message: 'Le slug ne peut contenir que des lettres minuscules, chiffres, tirets et slashes.'
                    )
                ]
            ])
            
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'help' => 'Description détaillée du modèle et de son utilisation',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Description du modèle, cas d\'usage, instructions spéciales...'
                ]
            ])
            
            ->add('documentType', EntityType::class, [
                'class' => DocumentType::class,
                'choice_label' => 'name',
                'choice_value' => 'id',
                'label' => 'Type de document',
                'help' => 'Type de document associé à ce modèle',
                'placeholder' => 'Sélectionnez un type de document',
                'attr' => [
                    'class' => 'form-select'
                ],
                'query_builder' => function (DocumentTypeRepository $repository) {
                    return $repository->createQueryBuilder('dt')
                        ->where('dt.isActive = true')
                        ->orderBy('dt.name', 'ASC');
                },
                'constraints' => [
                    new Assert\NotNull(message: 'Le type de document est obligatoire.')
                ]
            ])
            
            ->add('templateContent', TextareaType::class, [
                'label' => 'Contenu du modèle',
                'help' => 'Contenu du modèle avec placeholders {{nom_placeholder}}',
                'required' => false,
                'attr' => [
                    'class' => 'form-control template-editor',
                    'rows' => 15,
                    'placeholder' => 'Contenu du modèle avec placeholders: {{date}}, {{nom_formation}}, {{duree}}, etc.',
                    'data-editor' => 'rich-text'
                ]
            ])
            
            ->add('icon', ChoiceType::class, [
                'label' => 'Icône',
                'help' => 'Icône représentant le modèle',
                'required' => false,
                'placeholder' => 'Choisir une icône',
                'choices' => $this->getIconChoices(),
                'attr' => [
                    'class' => 'form-select icon-select',
                    'data-icon-select' => 'true'
                ]
            ])
            
            ->add('color', ColorType::class, [
                'label' => 'Couleur',
                'help' => 'Couleur associée au modèle pour la catégorisation visuelle',
                'required' => false,
                'attr' => [
                    'class' => 'form-control form-control-color'
                ]
            ])
            
            ->add('isActive', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Actif' => true,
                    'Inactif' => false
                ],
                'expanded' => true,
                'multiple' => false,
                'data' => true,
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'help' => 'Un modèle inactif ne peut pas être utilisé pour créer de nouveaux documents'
            ])
            
            ->add('isDefault', ChoiceType::class, [
                'label' => 'Modèle par défaut',
                'choices' => [
                    'Oui' => true,
                    'Non' => false
                ],
                'expanded' => true,
                'multiple' => false,
                'data' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'help' => 'Le modèle par défaut sera sélectionné automatiquement pour ce type de document'
            ])
            
            ->add('sortOrder', IntegerType::class, [
                'label' => 'Ordre de tri',
                'help' => 'Ordre d\'affichage dans les listes (plus petit = affiché en premier)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0,
                    'max' => 9999
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DocumentTemplate::class,
            'attr' => [
                'novalidate' => 'novalidate'
            ]
        ]);
    }

    /**
     * Get available icon choices
     */
    private function getIconChoices(): array
    {
        return [
            // Documents
            'Document' => 'fas fa-file-alt',
            'Contrat' => 'fas fa-file-contract',
            'Facture' => 'fas fa-file-invoice',
            'Rapport' => 'fas fa-file-chart-column',
            'Certificat' => 'fas fa-certificate',
            'Diplôme' => 'fas fa-graduation-cap',
            
            // Types de formation
            'Formation' => 'fas fa-chalkboard-teacher',
            'Cours' => 'fas fa-book-open',
            'Module' => 'fas fa-puzzle-piece',
            'Évaluation' => 'fas fa-clipboard-check',
            'Quiz' => 'fas fa-question-circle',
            
            // Administration
            'Administratif' => 'fas fa-cog',
            'Légal' => 'fas fa-balance-scale',
            'Financier' => 'fas fa-euro-sign',
            'RH' => 'fas fa-users',
            
            // Communication
            'Email' => 'fas fa-envelope',
            'Lettre' => 'fas fa-mail-bulk',
            'Annonce' => 'fas fa-bullhorn',
            'Newsletter' => 'fas fa-newspaper',
            
            // Technique
            'Configuration' => 'fas fa-tools',
            'Modèle' => 'fas fa-layer-group',
            'Patron' => 'fas fa-copy',
            'Standard' => 'fas fa-star'
        ];
    }
}
