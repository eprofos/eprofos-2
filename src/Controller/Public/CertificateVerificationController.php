<?php

declare(strict_types=1);

namespace App\Controller\Public;

use App\Service\Student\CertificateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * CertificateVerificationController handles public certificate verification.
 *
 * Provides a public interface for verifying certificate authenticity
 * using verification codes and QR codes.
 */
#[Route('/certificate')]
class CertificateVerificationController extends AbstractController
{
    public function __construct(
        private readonly CertificateService $certificateService
    ) {
    }

    /**
     * Certificate verification page.
     */
    #[Route('/verify', name: 'certificate_verify_form', methods: ['GET'])]
    public function verifyForm(): Response
    {
        return $this->render('public/certificate/verify_form.html.twig');
    }

    /**
     * Verify certificate by verification code.
     */
    #[Route('/verify/{code}', name: 'certificate_verify', methods: ['GET'])]
    public function verify(?string $code = null, Request $request): Response
    {
        // Get verification code from URL parameter or form submission
        $verificationCode = $code ?? $request->query->get('code');

        if (!$verificationCode) {
            return $this->render('public/certificate/verification_form.html.twig', [
                'error' => 'Veuillez saisir un code de vérification.',
            ]);
        }

        $certificate = $this->certificateService->verifyCertificate($verificationCode);

        if (!$certificate) {
            return $this->render('public/certificate/verification_failed.html.twig', [
                'verification_code' => $verificationCode,
            ]);
        }

        return $this->render('public/certificate/verification_success.html.twig', [
            'certificate' => $certificate,
            'student' => $certificate->getStudent(),
            'formation' => $certificate->getFormation(),
            'enrollment' => $certificate->getEnrollment(),
        ]);
    }

    /**
     * Process verification form submission.
     */
    #[Route('/verify', name: 'certificate_verify_submit', methods: ['POST'])]
    public function verifySubmit(Request $request): Response
    {
        $verificationCode = $request->request->get('verification_code');

        if (!$verificationCode) {
            return $this->render('public/certificate/verify_form.html.twig', [
                'error' => 'Veuillez saisir un code de vérification.',
            ]);
        }

        // Redirect to the GET route with the verification code
        return $this->redirectToRoute('certificate_verify', ['code' => $verificationCode]);
    }
}
