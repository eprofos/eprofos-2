<?php

namespace App\DataFixtures;

use App\Entity\ContactRequest;
use App\Entity\Formation;
use App\Entity\Service;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * ContactRequest fixtures for EPROFOS platform
 * 
 * Creates sample contact requests for testing purposes including
 * different types of requests (quote, advice, information, quick_registration)
 * with various statuses and realistic data.
 */
class ContactRequestFixtures extends Fixture implements DependentFixtureInterface
{
    /**
     * Load contact request fixtures
     */
    public function load(ObjectManager $manager): void
    {
        // Get some formations and services for testing
        $formations = $manager->getRepository(Formation::class)->findAll();
        $services = $manager->getRepository(Service::class)->findAll();

        $contactRequests = [
            [
                'type' => 'quote',
                'firstName' => 'Marie',
                'lastName' => 'Dubois',
                'email' => 'marie.dubois@entreprise.com',
                'phone' => '0123456789',
                'company' => 'TechCorp Solutions',
                'subject' => 'Demande de devis formation développement web',
                'message' => 'Bonjour, nous souhaitons former 5 développeurs de notre équipe au développement web avec Symfony. Pourriez-vous nous faire parvenir un devis détaillé ?',
                'status' => 'pending',
                'formation' => 0, // Will use first formation
                'createdDaysAgo' => 2,
            ],
            [
                'type' => 'advice',
                'firstName' => 'Pierre',
                'lastName' => 'Martin',
                'email' => 'p.martin@consulting.fr',
                'phone' => '0234567890',
                'company' => 'Martin Consulting',
                'subject' => 'Conseil en transformation digitale',
                'message' => 'Notre PME de 50 salariés souhaite entamer sa transformation digitale. Nous aurions besoin de conseils pour définir notre stratégie et identifier les formations nécessaires.',
                'status' => 'in_progress',
                'service' => 1, // Will use second service
                'createdDaysAgo' => 5,
            ],
            [
                'type' => 'information',
                'firstName' => 'Sophie',
                'lastName' => 'Leroy',
                'email' => 'sophie.leroy@gmail.com',
                'phone' => '0345678901',
                'company' => null,
                'subject' => 'Information sur les formations en marketing digital',
                'message' => 'Bonjour, je suis en reconversion professionnelle et je m\'intéresse aux formations en marketing digital. Pourriez-vous me donner plus d\'informations sur les prérequis et les débouchés ?',
                'status' => 'completed',
                'formation' => 7, // Marketing digital formation
                'createdDaysAgo' => 10,
            ],
            [
                'type' => 'quick_registration',
                'firstName' => 'Thomas',
                'lastName' => 'Rousseau',
                'email' => 'thomas.rousseau@startup.io',
                'phone' => '0456789012',
                'company' => 'InnovTech Startup',
                'subject' => 'Inscription rapide formation cybersécurité',
                'message' => 'Je souhaite m\'inscrire rapidement à la prochaine session de formation en cybersécurité. Mon entreprise a un besoin urgent de renforcer ses compétences dans ce domaine.',
                'status' => 'pending',
                'formation' => 1, // Cybersecurity formation
                'createdDaysAgo' => 1,
            ],
            [
                'type' => 'quote',
                'firstName' => 'Isabelle',
                'lastName' => 'Moreau',
                'email' => 'i.moreau@industrie.com',
                'phone' => '0567890123',
                'company' => 'Industrie Plus',
                'subject' => 'Formation lean management pour nos équipes',
                'message' => 'Nous souhaitons former nos managers aux méthodes lean. Nous avons 15 personnes à former. Pouvez-vous nous proposer une formation intra-entreprise ?',
                'status' => 'in_progress',
                'formation' => 9, // Lean management formation
                'createdDaysAgo' => 7,
            ],
            [
                'type' => 'advice',
                'firstName' => 'Laurent',
                'lastName' => 'Petit',
                'email' => 'laurent.petit@rh-conseil.fr',
                'phone' => '0678901234',
                'company' => 'RH Conseil & Associés',
                'subject' => 'Accompagnement en gestion des talents',
                'message' => 'Notre cabinet RH souhaite développer son expertise en gestion des talents. Nous cherchons un accompagnement pour structurer notre offre de services.',
                'status' => 'completed',
                'service' => 10, // Coaching service
                'createdDaysAgo' => 15,
            ],
            [
                'type' => 'information',
                'firstName' => 'Céline',
                'lastName' => 'Bernard',
                'email' => 'celine.bernard@freelance.com',
                'phone' => null,
                'company' => null,
                'subject' => 'Formations éligibles CPF',
                'message' => 'Bonjour, je suis consultante freelance et je souhaiterais savoir quelles sont vos formations éligibles au CPF, particulièrement en management et leadership.',
                'status' => 'pending',
                'formation' => 3, // Leadership formation
                'createdDaysAgo' => 3,
            ],
            [
                'type' => 'quote',
                'firstName' => 'Nicolas',
                'lastName' => 'Girard',
                'email' => 'n.girard@finance-corp.com',
                'phone' => '0789012345',
                'company' => 'Finance Corp',
                'subject' => 'Audit de compétences équipe comptabilité',
                'message' => 'Nous souhaitons faire réaliser un audit de compétences pour notre équipe comptabilité (8 personnes) afin d\'identifier les besoins en formation.',
                'status' => 'cancelled',
                'service' => 0, // Audit service
                'createdDaysAgo' => 20,
            ],
        ];

        foreach ($contactRequests as $index => $requestData) {
            $contactRequest = new ContactRequest();
            $contactRequest->setType($requestData['type']);
            $contactRequest->setFirstName($requestData['firstName']);
            $contactRequest->setLastName($requestData['lastName']);
            $contactRequest->setEmail($requestData['email']);
            $contactRequest->setPhone($requestData['phone']);
            $contactRequest->setCompany($requestData['company']);
            $contactRequest->setSubject($requestData['subject']);
            $contactRequest->setMessage($requestData['message']);
            $contactRequest->setStatus($requestData['status']);

            // Set creation date in the past
            $createdAt = new \DateTime();
            $createdAt->modify('-' . $requestData['createdDaysAgo'] . ' days');
            $contactRequest->setCreatedAt($createdAt);

            // Set formation or service if specified
            if (isset($requestData['formation']) && !empty($formations)) {
                $formationIndex = $requestData['formation'];
                if (isset($formations[$formationIndex])) {
                    $contactRequest->setFormation($formations[$formationIndex]);
                }
            }

            if (isset($requestData['service']) && !empty($services)) {
                $serviceIndex = $requestData['service'];
                if (isset($services[$serviceIndex])) {
                    $contactRequest->setService($services[$serviceIndex]);
                }
            }

            // Set processed date for completed/cancelled requests
            if (in_array($requestData['status'], ['completed', 'cancelled', 'in_progress'])) {
                $processedAt = clone $createdAt;
                $processedAt->modify('+1 day');
                $contactRequest->setProcessedAt($processedAt);
            }

            // Add admin notes for processed requests
            if ($requestData['status'] === 'completed') {
                $contactRequest->setAdminNotes('Demande traitée avec succès. Client satisfait.');
            } elseif ($requestData['status'] === 'cancelled') {
                $contactRequest->setAdminNotes('Demande annulée par le client pour raisons budgétaires.');
            } elseif ($requestData['status'] === 'in_progress') {
                $contactRequest->setAdminNotes('En cours de traitement. Devis en préparation.');
            }

            $manager->persist($contactRequest);
        }

        $manager->flush();
    }

    /**
     * Define fixture dependencies
     */
    public function getDependencies(): array
    {
        return [
            FormationFixtures::class,
            ServiceFixtures::class,
        ];
    }
}