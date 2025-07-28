<?php

declare(strict_types=1);

namespace App\Form\User;

use App\Entity\User\Teacher;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;

/**
 * Teacher Form Type for admin management.
 *
 * Complete form for teacher creation and editing in admin interface
 */
class TeacherType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'attr' => [
                    'placeholder' => 'Prénom du formateur',
                    'class' => 'form-control',
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'attr' => [
                    'placeholder' => 'Nom du formateur',
                    'class' => 'form-control',
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => [
                    'placeholder' => 'email@exemple.com',
                    'class' => 'form-control',
                ],
            ])
            ->add('phone', TextType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'attr' => [
                    'placeholder' => '+33 1 23 45 67 89',
                    'class' => 'form-control',
                ],
            ])
            ->add('birthDate', DateType::class, [
                'label' => 'Date de naissance',
                'required' => false,
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('address', TextType::class, [
                'label' => 'Adresse',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Adresse complète',
                    'class' => 'form-control',
                ],
            ])
            ->add('postalCode', TextType::class, [
                'label' => 'Code postal',
                'required' => false,
                'attr' => [
                    'placeholder' => '75001',
                    'class' => 'form-control',
                ],
            ])
            ->add('city', TextType::class, [
                'label' => 'Ville',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Paris',
                    'class' => 'form-control',
                ],
            ])
            ->add('country', TextType::class, [
                'label' => 'Pays',
                'required' => false,
                'attr' => [
                    'placeholder' => 'France',
                    'class' => 'form-control',
                    'value' => 'France',
                ],
            ])
            ->add('specialty', TextType::class, [
                'label' => 'Spécialité',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Informatique, Marketing, Langues...',
                    'class' => 'form-control',
                ],
                'help' => 'Domaine d\'expertise principal du formateur',
            ])
            ->add('title', TextType::class, [
                'label' => 'Titre/Qualification',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Dr, Professeur, Ingénieur...',
                    'class' => 'form-control',
                ],
            ])
            ->add('biography', TextareaType::class, [
                'label' => 'Biographie',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Biographie et présentation du formateur...',
                    'class' => 'form-control',
                    'rows' => 5,
                ],
            ])
            ->add('qualifications', TextType::class, [
                'label' => 'Qualifications',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Diplômes et certifications',
                    'class' => 'form-control',
                ],
            ])
            ->add('yearsOfExperience', IntegerType::class, [
                'label' => 'Années d\'expérience',
                'required' => false,
                'attr' => [
                    'placeholder' => '5',
                    'class' => 'form-control',
                    'min' => 0,
                    'max' => 50,
                ],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Actif',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input',
                ],
                'help' => 'Le formateur peut-il se connecter et enseigner ?',
            ])
            ->add('emailVerified', CheckboxType::class, [
                'label' => 'Email vérifié',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input',
                ],
                'help' => 'Marquer l\'email comme vérifié',
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => 'Nouveau mot de passe',
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'placeholder' => 'Laisser vide pour ne pas changer',
                    'class' => 'form-control',
                    'autocomplete' => 'new-password',
                ],
                'help' => 'Laisser vide pour conserver le mot de passe actuel',
                'constraints' => [
                    new Length([
                        'min' => 6,
                        'minMessage' => 'Le mot de passe doit contenir au moins {{ limit }} caractères',
                        'max' => 4096,
                    ]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Teacher::class,
        ]);
    }
}
