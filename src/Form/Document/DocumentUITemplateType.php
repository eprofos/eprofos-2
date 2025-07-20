<?php

namespace App\Form\Document;

use App\Entity\Document\DocumentUITemplate;
use App\Entity\Document\DocumentType;
use App\Repository\Document\DocumentTypeRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Document UI Template Form Type
 * 
 * Form for creating and editing document UI templates with all configuration options.
 */
class DocumentUITemplateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du modèle',
                'help' => 'Nom descriptif du modèle UI',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Modèle certificat EPROFOS'
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
                'help' => 'Identifiant unique du modèle (généré automatiquement)',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'modele-certificat-eprofos'
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
                    'placeholder' => 'Description du modèle UI...'
                ]
            ])
            
            ->add('documentType', EntityType::class, [
                'class' => DocumentType::class,
                'label' => 'Type de document',
                'help' => 'Type de document auquel ce modèle est associé (laisser vide pour un modèle global)',
                'placeholder' => 'Sélectionner un type de document',
                'required' => false,
                'choice_label' => 'name',
                'query_builder' => function (DocumentTypeRepository $repository) {
                    return $repository->createQueryBuilder('dt')
                        ->where('dt.isActive = true')
                        ->orderBy('dt.name', 'ASC');
                }
            ])
            
            ->add('htmlTemplate', TextareaType::class, [
                'label' => 'Template HTML',
                'help' => 'Code HTML du modèle avec placeholders {{variable}}',
                'required' => false,
                'attr' => [
                    'class' => 'form-control code-editor',
                    'rows' => 20,
                    'data-language' => 'html',
                    'placeholder' => '<!DOCTYPE html>
<html>
<head>
    <title>{{title}}</title>
</head>
<body>
    <header>{{header_content}}</header>
    <main>{{content}}</main>
    <footer>{{footer_content}}</footer>
</body>
</html>'
                ]
            ])
            
            ->add('cssStyles', TextareaType::class, [
                'label' => 'Styles CSS',
                'help' => 'Styles CSS personnalisés pour le modèle',
                'required' => false,
                'attr' => [
                    'class' => 'form-control code-editor',
                    'rows' => 15,
                    'data-language' => 'css',
                    'placeholder' => 'body { font-family: Arial, sans-serif; }
.header { text-align: center; }
.content { margin: 20px 0; }'
                ]
            ])
            
            ->add('orientation', ChoiceType::class, [
                'label' => 'Orientation',
                'help' => 'Orientation de la page',
                'choices' => [
                    'Portrait' => 'portrait',
                    'Paysage' => 'landscape'
                ],
                'attr' => ['class' => 'form-select']
            ])
            
            ->add('paperSize', ChoiceType::class, [
                'label' => 'Format de page',
                'help' => 'Format de papier pour le PDF',
                'choices' => [
                    'A4' => 'A4',
                    'A3' => 'A3',
                    'A5' => 'A5',
                    'Letter' => 'Letter',
                    'Legal' => 'Legal'
                ],
                'attr' => ['class' => 'form-select']
            ])
            
            ->add('isGlobal', CheckboxType::class, [
                'label' => 'Modèle global',
                'help' => 'Un modèle global peut être utilisé pour tous les types de documents',
                'required' => false,
                'attr' => ['class' => 'form-check-input']
            ])
            
            ->add('isDefault', CheckboxType::class, [
                'label' => 'Modèle par défaut',
                'help' => 'Utiliser ce modèle par défaut pour ce type de document',
                'required' => false,
                'attr' => ['class' => 'form-check-input']
            ])
            
            ->add('isActive', CheckboxType::class, [
                'label' => 'Actif',
                'help' => 'Le modèle est disponible pour utilisation',
                'required' => false,
                'attr' => ['class' => 'form-check-input']
            ])
            
            ->add('icon', ChoiceType::class, [
                'label' => 'Icône',
                'help' => 'Icône représentant le modèle',
                'required' => false,
                'placeholder' => 'Sélectionner une icône',
                'choices' => $this->getIconChoices(),
                'attr' => ['class' => 'form-select icon-picker']
            ])
            
            ->add('color', ChoiceType::class, [
                'label' => 'Couleur',
                'help' => 'Couleur de thème du modèle',
                'required' => false,
                'placeholder' => 'Sélectionner une couleur',
                'choices' => $this->getColorChoices(),
                'attr' => ['class' => 'form-select color-picker']
            ])
            
            ->add('sortOrder', IntegerType::class, [
                'label' => 'Ordre de tri',
                'help' => 'Ordre d\'affichage dans les listes',
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0,
                    'max' => 9999
                ],
                'constraints' => [
                    new Assert\PositiveOrZero(message: 'L\'ordre de tri doit être positif ou zéro.')
                ]
            ])
            
            // Advanced configuration fields
            ->add('layoutConfiguration', HiddenType::class, [
                'attr' => ['class' => 'json-field'],
                'required' => false
            ])
            
            ->add('pageSettings', HiddenType::class, [
                'attr' => ['class' => 'json-field'],
                'required' => false
            ])
            
            ->add('headerFooterConfig', HiddenType::class, [
                'attr' => ['class' => 'json-field'],
                'required' => false
            ])
            
            ->add('componentStyles', HiddenType::class, [
                'attr' => ['class' => 'json-field'],
                'required' => false
            ])
            
            ->add('variables', HiddenType::class, [
                'attr' => ['class' => 'json-field'],
                'required' => false
            ])
            
            ->add('margins', HiddenType::class, [
                'attr' => ['class' => 'json-field'],
                'required' => false
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DocumentUITemplate::class,
            'attr' => ['class' => 'ui-template-form'],
        ]);
    }

    /**
     * Get available icon choices
     */
    private function getIconChoices(): array
    {
        return [
            'Document' => 'fas fa-file-alt',
            'PDF' => 'fas fa-file-pdf',
            'Certificat' => 'fas fa-certificate',
            'Rapport' => 'fas fa-chart-line',
            'Contrat' => 'fas fa-handshake',
            'Facture' => 'fas fa-receipt',
            'En-tête' => 'fas fa-heading',
            'Modèle' => 'fas fa-layer-group',
            'Imprimante' => 'fas fa-print',
            'Layout' => 'fas fa-th-large',
            'Design' => 'fas fa-palette',
            'Configuration' => 'fas fa-cogs',
            'Standard' => 'fas fa-file',
            'Professionnel' => 'fas fa-briefcase',
            'Éducation' => 'fas fa-graduation-cap',
            'Formation' => 'fas fa-chalkboard-teacher'
        ];
    }

    /**
     * Get available color choices
     */
    private function getColorChoices(): array
    {
        return [
            'Bleu primaire' => '#007bff',
            'Bleu foncé' => '#0056b3',
            'Vert' => '#28a745',
            'Rouge' => '#dc3545',
            'Orange' => '#fd7e14',
            'Jaune' => '#ffc107',
            'Indigo' => '#6610f2',
            'Violet' => '#6f42c1',
            'Rose' => '#e83e8c',
            'Gris foncé' => '#343a40',
            'Gris' => '#6c757d',
            'Gris clair' => '#e9ecef',
            'Noir' => '#000000',
            'Blanc' => '#ffffff',
            'EPROFOS Bleu' => '#1e40af',
            'EPROFOS Vert' => '#059669'
        ];
    }
}
