<?php

namespace App\Form;

use App\Entity\User\Mentor;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PositiveOrZero;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Mentor Registration Form
 * 
 * Form for new mentor registration with company information and expertise validation.
 */
class MentorRegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Personal Information
            ->add('email', EmailType::class, [
                'label' => 'Email professionnel',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'votre@email.com'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez saisir un email']),
                ],
                'help' => 'Utilisez de préférence votre email professionnel'
            ])
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Votre prénom'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez saisir votre prénom']),
                    new Length([
                        'min' => 2,
                        'max' => 100,
                        'minMessage' => 'Le prénom doit contenir au moins {{ limit }} caractères',
                        'maxMessage' => 'Le prénom ne peut pas dépasser {{ limit }} caractères',
                    ]),
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Votre nom'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez saisir votre nom']),
                    new Length([
                        'min' => 2,
                        'max' => 100,
                        'minMessage' => 'Le nom doit contenir au moins {{ limit }} caractères',
                        'maxMessage' => 'Le nom ne peut pas dépasser {{ limit }} caractères',
                    ]),
                ],
            ])
            ->add('phone', TelType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '01 23 45 67 89'
                ],
                'help' => 'Numéro de téléphone professionnel de préférence'
            ])

            // Professional Information
            ->add('position', TextType::class, [
                'label' => 'Poste / Fonction',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'ex: Responsable RH, Directeur commercial...'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez saisir votre poste']),
                    new Length([
                        'min' => 2,
                        'max' => 150,
                        'minMessage' => 'Le poste doit contenir au moins {{ limit }} caractères',
                        'maxMessage' => 'Le poste ne peut pas dépasser {{ limit }} caractères',
                    ]),
                ],
            ])

            // Company Information
            ->add('companyName', TextType::class, [
                'label' => 'Nom de l\'entreprise',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Raison sociale de votre entreprise'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez saisir le nom de l\'entreprise']),
                    new Length([
                        'min' => 2,
                        'max' => 200,
                        'minMessage' => 'Le nom de l\'entreprise doit contenir au moins {{ limit }} caractères',
                        'maxMessage' => 'Le nom de l\'entreprise ne peut pas dépasser {{ limit }} caractères',
                    ]),
                ],
            ])
            ->add('companySiret', TextType::class, [
                'label' => 'SIRET de l\'entreprise',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '12345678901234 (14 chiffres)',
                    'maxlength' => '14'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez saisir le SIRET']),
                    new Length([
                        'exactly' => 14,
                        'exactMessage' => 'Le SIRET doit contenir exactement {{ limit }} chiffres',
                    ]),
                    new Regex([
                        'pattern' => '/^\d{14}$/',
                        'message' => 'Le SIRET doit contenir uniquement des chiffres'
                    ])
                ],
                'help' => 'Numéro SIRET à 14 chiffres de votre entreprise'
            ])

            // Expertise and Experience
            ->add('expertiseDomains', ChoiceType::class, [
                'label' => 'Domaines d\'expertise',
                'choices' => Mentor::EXPERTISE_DOMAINS,
                'multiple' => true,
                'expanded' => false,
                'attr' => [
                    'class' => 'form-select',
                    'data-controller' => 'choices',
                    'multiple' => 'multiple'
                ],
                'constraints' => [
                    new Count([
                        'min' => 1,
                        'minMessage' => 'Veuillez sélectionner au moins un domaine d\'expertise'
                    ])
                ],
                'help' => 'Sélectionnez un ou plusieurs domaines dans lesquels vous pouvez encadrer des alternants'
            ])
            ->add('experienceYears', IntegerType::class, [
                'label' => 'Années d\'expérience professionnelle',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'ex: 5',
                    'min' => '0',
                    'max' => '50'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez saisir votre expérience']),
                    new PositiveOrZero(['message' => 'L\'expérience doit être un nombre positif']),
                ],
                'help' => 'Nombre d\'années d\'expérience dans votre domaine'
            ])
            ->add('educationLevel', ChoiceType::class, [
                'label' => 'Niveau de formation',
                'choices' => Mentor::EDUCATION_LEVELS,
                'attr' => [
                    'class' => 'form-select'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez sélectionner votre niveau de formation']),
                ],
                'placeholder' => 'Sélectionnez votre niveau',
                'help' => 'Votre plus haut niveau de formation obtenu'
            ])

            // Password
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'Les mots de passe doivent correspondre.',
                'options' => ['attr' => ['class' => 'form-control']],
                'required' => true,
                'first_options' => [
                    'label' => 'Mot de passe',
                    'attr' => ['placeholder' => 'Choisissez un mot de passe sécurisé']
                ],
                'second_options' => [
                    'label' => 'Confirmer le mot de passe',
                    'attr' => ['placeholder' => 'Confirmez votre mot de passe']
                ],
                'mapped' => false,
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez saisir un mot de passe']),
                    new Length([
                        'min' => 8,
                        'minMessage' => 'Le mot de passe doit contenir au moins {{ limit }} caractères',
                        'max' => 4096,
                    ]),
                ],
                'help' => 'Minimum 8 caractères avec lettres, chiffres et caractères spéciaux recommandés'
            ])

            // Terms and Conditions
            ->add('agreeTerms', CheckboxType::class, [
                'label' => 'J\'accepte les conditions générales d\'utilisation',
                'mapped' => false,
                'attr' => [
                    'class' => 'form-check-input',
                ],
                'label_attr' => [
                    'class' => 'form-check-label',
                ],
                'constraints' => [
                    new IsTrue([
                        'message' => 'Vous devez accepter les conditions générales d\'utilisation.',
                    ]),
                ],
            ])
            ->add('agreeMentorTerms', CheckboxType::class, [
                'label' => 'J\'accepte les conditions spécifiques au statut de mentor entreprise',
                'mapped' => false,
                'attr' => [
                    'class' => 'form-check-input',
                ],
                'label_attr' => [
                    'class' => 'form-check-label',
                ],
                'constraints' => [
                    new IsTrue([
                        'message' => 'Vous devez accepter les conditions spécifiques aux mentors.',
                    ]),
                ],
                'help' => 'En tant que mentor, vous vous engagez à accompagner les alternants selon les standards de qualité EPROFOS'
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Mentor::class,
            'validation_groups' => ['Default', 'registration'],
        ]);
    }
}
