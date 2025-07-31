<?php

declare(strict_types=1);

namespace App\Controller\Public;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Legal controller for legal pages.
 *
 * Handles the display of legal information including
 * terms of service, privacy policy, and legal notices.
 */
class LegalController extends AbstractController
{
    /**
     * Display legal notices.
     */
    #[Route('/mentions-legales', name: 'public_legal_notices', methods: ['GET'])]
    public function notices(): Response
    {
        $legalInfo = [
            'company_name' => 'EPROFOS',
            'legal_form' => 'SARL',
            'capital' => '50 000 €',
            'siret' => '123 456 789 00012',
            'rcs' => 'RCS Paris B 123 456 789',
            'vat_number' => 'FR12 123456789',
            'address' => [
                'street' => '123 Avenue de la Formation',
                'postal_code' => '75001',
                'city' => 'Paris',
                'country' => 'France',
            ],
            'phone' => '+33 1 23 45 67 89',
            'email' => 'contact@eprofos.fr',
            'director' => 'Marie Dubois',
            'hosting' => [
                'provider' => 'OVH',
                'address' => '2 rue Kellermann, 59100 Roubaix, France',
            ],
        ];

        return $this->render('public/legal/notices.html.twig', [
            'legal_info' => $legalInfo,
        ]);
    }

    /**
     * Display privacy policy.
     */
    #[Route('/politique-de-confidentialite', name: 'public_legal_privacy', methods: ['GET'])]
    public function privacy(): Response
    {
        return $this->render('public/legal/privacy.html.twig');
    }

    /**
     * Display terms of service.
     */
    #[Route('/conditions-generales', name: 'public_legal_terms', methods: ['GET'])]
    public function terms(): Response
    {
        return $this->render('public/legal/terms.html.twig');
    }

    /**
     * Display cookies policy.
     */
    #[Route('/politique-cookies', name: 'public_legal_cookies', methods: ['GET'])]
    public function cookies(): Response
    {
        return $this->render('public/legal/cookies.html.twig');
    }
}
