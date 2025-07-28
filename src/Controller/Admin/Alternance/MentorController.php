<?php

declare(strict_types=1);

namespace App\Controller\Admin\Alternance;

use App\Entity\User\Mentor;
use App\Form\User\MentorType;
use App\Repository\Alternance\AlternanceContractRepository;
use App\Repository\User\MentorRepository;
use App\Service\User\MentorAuthenticationService;
use App\Service\User\MentorService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/alternance/mentors')]
#[IsGranted('ROLE_ADMIN')]
class MentorController extends AbstractController
{
    public function __construct(
        private MentorRepository $mentorRepository,
        private AlternanceContractRepository $contractRepository,
        private MentorService $mentorService,
        private MentorAuthenticationService $authService,
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer,
    ) {}

    #[Route('', name: 'admin_alternance_mentor_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        $search = $request->query->get('search', '');
        $company = $request->query->get('company', '');
        $status = $request->query->get('status', '');
        $perPage = 20;

        $filters = [
            'search' => $search,
            'company' => $company,
            'status' => $status,
        ];

        $mentors = $this->mentorRepository->findPaginatedMentors($filters, $page, $perPage);
        $totalPages = ceil($this->mentorRepository->countFilteredMentors($filters) / $perPage);

        // Get mentor statistics
        $statistics = $this->getMentorStatistics();

        return $this->render('admin/alternance/mentor/index.html.twig', [
            'mentors' => $mentors,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'filters' => $filters,
            'statistics' => $statistics,
        ]);
    }

    #[Route('/new', name: 'admin_alternance_mentor_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $mentor = new Mentor();
        $form = $this->createForm(MentorType::class, $mentor);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Generate authentication credentials
                $credentials = $this->authService->generateCredentials($mentor);

                $this->entityManager->persist($mentor);
                $this->entityManager->flush();

                // Send invitation email
                $this->sendInvitationEmail($mentor, $credentials);

                $this->addFlash('success', 'Mentor créé avec succès. Un email d\'invitation a été envoyé.');

                return $this->redirectToRoute('admin_alternance_mentor_show', [
                    'id' => $mentor->getId(),
                ]);
            } catch (Exception $e) {
                $this->addFlash('error', 'Erreur lors de la création du mentor : ' . $e->getMessage());
            }
        }

        return $this->render('admin/alternance/mentor/new.html.twig', [
            'mentor' => $mentor,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_alternance_mentor_show', methods: ['GET'])]
    public function show(Mentor $mentor): Response
    {
        // Get mentor's contracts and performance metrics
        $contracts = $this->contractRepository->findBy(['mentor' => $mentor]);
        $performance = $this->mentorService->calculatePerformanceMetrics($mentor);
        $recentActivity = $this->mentorService->getRecentActivity($mentor);

        return $this->render('admin/alternance/mentor/show.html.twig', [
            'mentor' => $mentor,
            'contracts' => $contracts,
            'performance' => $performance,
            'recent_activity' => $recentActivity,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_alternance_mentor_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Mentor $mentor): Response
    {
        $form = $this->createForm(MentorType::class, $mentor);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->entityManager->flush();
                $this->addFlash('success', 'Mentor modifié avec succès.');

                return $this->redirectToRoute('admin_alternance_mentor_show', [
                    'id' => $mentor->getId(),
                ]);
            } catch (Exception $e) {
                $this->addFlash('error', 'Erreur lors de la modification du mentor : ' . $e->getMessage());
            }
        }

        return $this->render('admin/alternance/mentor/edit.html.twig', [
            'mentor' => $mentor,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/performance', name: 'admin_alternance_mentor_performance', methods: ['GET'])]
    public function performance(Mentor $mentor, Request $request): Response
    {
        $period = $request->query->get('period', '12'); // months
        $performance = $this->mentorService->getDetailedPerformance($mentor, (int) $period);

        return $this->render('admin/alternance/mentor/performance.html.twig', [
            'mentor' => $mentor,
            'performance' => $performance,
            'period' => $period,
        ]);
    }

    #[Route('/{id}/activate', name: 'admin_alternance_mentor_activate', methods: ['POST'])]
    public function activate(Mentor $mentor): Response
    {
        try {
            $mentor->setIsActive(true);
            $this->entityManager->flush();
            $this->addFlash('success', 'Mentor activé avec succès.');
        } catch (Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'activation : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_alternance_mentor_show', ['id' => $mentor->getId()]);
    }

    #[Route('/{id}/deactivate', name: 'admin_alternance_mentor_deactivate', methods: ['POST'])]
    public function deactivate(Mentor $mentor): Response
    {
        try {
            // Check if mentor has active contracts
            $activeContracts = $this->contractRepository->findActiveContractsByMentor($mentor);
            if (!empty($activeContracts)) {
                $this->addFlash('error', 'Impossible de désactiver un mentor avec des contrats actifs.');

                return $this->redirectToRoute('admin_alternance_mentor_show', ['id' => $mentor->getId()]);
            }

            $mentor->setIsActive(false);
            $this->entityManager->flush();
            $this->addFlash('success', 'Mentor désactivé avec succès.');
        } catch (Exception $e) {
            $this->addFlash('error', 'Erreur lors de la désactivation : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_alternance_mentor_show', ['id' => $mentor->getId()]);
    }

    #[Route('/{id}/resend-invitation', name: 'admin_alternance_mentor_resend_invitation', methods: ['POST'])]
    public function resendInvitation(Mentor $mentor): Response
    {
        try {
            // Reset credentials and send new invitation
            $credentials = $this->authService->resetCredentials($mentor);
            $this->sendInvitationEmail($mentor, $credentials);

            $this->addFlash('success', 'Invitation renvoyée avec succès.');
        } catch (Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'envoi de l\'invitation : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_alternance_mentor_show', ['id' => $mentor->getId()]);
    }

    #[Route('/{id}/delete', name: 'admin_alternance_mentor_delete', methods: ['POST'])]
    public function delete(Request $request, Mentor $mentor): Response
    {
        if ($this->isCsrfTokenValid('delete' . $mentor->getId(), $request->request->get('_token'))) {
            try {
                // Check if mentor has any contracts
                $contracts = $this->contractRepository->findBy(['mentor' => $mentor]);
                if (!empty($contracts)) {
                    $this->addFlash('error', 'Impossible de supprimer un mentor avec des contrats associés.');

                    return $this->redirectToRoute('admin_alternance_mentor_show', ['id' => $mentor->getId()]);
                }

                $this->entityManager->remove($mentor);
                $this->entityManager->flush();
                $this->addFlash('success', 'Mentor supprimé avec succès.');
            } catch (Exception $e) {
                $this->addFlash('error', 'Erreur lors de la suppression : ' . $e->getMessage());
            }
        }

        return $this->redirectToRoute('admin_alternance_mentor_index');
    }

    #[Route('/bulk/actions', name: 'admin_alternance_mentor_bulk_actions', methods: ['POST'])]
    public function bulkActions(Request $request): Response
    {
        $mentorIds = $request->request->all('mentor_ids');
        $action = $request->request->get('action');

        if (empty($mentorIds) || !$action) {
            $this->addFlash('error', 'Veuillez sélectionner des mentors et une action.');

            return $this->redirectToRoute('admin_alternance_mentor_index');
        }

        try {
            $mentors = $this->mentorRepository->findBy(['id' => $mentorIds]);
            $processed = 0;

            foreach ($mentors as $mentor) {
                switch ($action) {
                    case 'activate':
                        $mentor->setIsActive(true);
                        $processed++;
                        break;

                    case 'deactivate':
                        // Check for active contracts
                        $activeContracts = $this->contractRepository->findActiveContractsByMentor($mentor);
                        if (empty($activeContracts)) {
                            $mentor->setIsActive(false);
                            $processed++;
                        }
                        break;

                    case 'resend_invitation':
                        $credentials = $this->authService->resetCredentials($mentor);
                        $this->sendInvitationEmail($mentor, $credentials);
                        $processed++;
                        break;
                }
            }

            $this->entityManager->flush();
            $this->addFlash('success', sprintf('%d mentor(s) traité(s) avec succès.', $processed));
        } catch (Exception $e) {
            $this->addFlash('error', 'Erreur lors du traitement : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_alternance_mentor_index');
    }

    #[Route('/export', name: 'admin_alternance_mentor_export', methods: ['GET'])]
    public function export(Request $request): Response
    {
        $format = $request->query->get('format', 'csv');
        $filters = [
            'company' => $request->query->get('company', ''),
            'status' => $request->query->get('status', ''),
        ];

        try {
            $mentors = $this->mentorRepository->findForExport($filters);
            $data = $this->mentorService->exportMentors($mentors, $format);

            $response = new Response($data);
            $response->headers->set('Content-Type', $format === 'csv' ? 'text/csv' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $response->headers->set('Content-Disposition', 'attachment; filename="mentors_export.' . $format . '"');

            return $response;
        } catch (Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'export : ' . $e->getMessage());

            return $this->redirectToRoute('admin_alternance_mentor_index');
        }
    }

    private function getMentorStatistics(): array
    {
        return [
            'total_mentors' => $this->mentorRepository->count([]),
            'active_mentors' => $this->mentorRepository->countActive(),
            'new_this_month' => $this->mentorRepository->countCreatedThisMonth(),
            'companies_count' => $this->mentorRepository->countUniqueCompanies(),
            'average_students_per_mentor' => $this->mentorRepository->getAverageStudentsPerMentor(),
            'mentors_by_company' => $this->mentorRepository->getMentorsByCompany(),
            'qualification_distribution' => $this->mentorRepository->getQualificationDistribution(),
        ];
    }

    private function sendInvitationEmail(Mentor $mentor, array $credentials): void
    {
        $email = (new Email())
            ->from('noreply@eprofos.com')
            ->to($mentor->getEmail())
            ->subject('Invitation à rejoindre la plateforme EPROFOS')
            ->html($this->renderView('emails/mentor_invitation.html.twig', [
                'mentor' => $mentor,
                'credentials' => $credentials,
            ]))
        ;

        $this->mailer->send($email);
    }
}
