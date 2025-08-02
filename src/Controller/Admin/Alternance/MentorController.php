<?php

declare(strict_types=1);

namespace App\Controller\Admin\Alternance;

use App\Entity\User\Mentor;
use App\Form\User\MentorType;
use App\Repository\Alternance\AlternanceContractRepository;
use App\Repository\User\MentorRepository;
use App\Service\User\MentorAuthenticationService;
use App\Service\User\MentorService;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
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
        private LoggerInterface $logger,
    ) {}

    #[Route('', name: 'admin_alternance_mentor_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->logger->info('MentorController: Starting mentor index listing', [
            'user_email' => $this->getUser()?->getUserIdentifier(),
            'request_parameters' => $request->query->all()
        ]);

        try {
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

            $this->logger->debug('MentorController: Applied filters for mentor listing', [
                'filters' => $filters,
                'page' => $page,
                'per_page' => $perPage
            ]);

            $mentors = $this->mentorRepository->findPaginatedMentors($filters, $page, $perPage);
            $totalMentors = $this->mentorRepository->countFilteredMentors($filters);
            $totalPages = ceil($totalMentors / $perPage);

            $this->logger->debug('MentorController: Retrieved mentors from repository', [
                'mentors_count' => count($mentors),
                'total_mentors' => $totalMentors,
                'total_pages' => $totalPages
            ]);

            // Get mentor statistics
            $statistics = $this->getMentorStatistics();

            $this->logger->info('MentorController: Successfully loaded mentor index', [
                'mentors_count' => count($mentors),
                'total_mentors' => $totalMentors,
                'statistics' => $statistics
            ]);

            return $this->render('admin/alternance/mentor/index.html.twig', [
                'mentors' => $mentors,
                'current_page' => $page,
                'total_pages' => $totalPages,
                'filters' => $filters,
                'statistics' => $statistics,
            ]);

        } catch (DBALException $e) {
            $this->logger->error('MentorController: Database error during mentor index listing', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'user_email' => $this->getUser()?->getUserIdentifier(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->addFlash('error', 'Erreur de base de données lors du chargement des mentors.');
            return $this->render('admin/alternance/mentor/index.html.twig', [
                'mentors' => [],
                'current_page' => 1,
                'total_pages' => 0,
                'filters' => [],
                'statistics' => [],
            ]);

        } catch (Exception $e) {
            $this->logger->error('MentorController: Unexpected error during mentor index listing', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'user_email' => $this->getUser()?->getUserIdentifier(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->addFlash('error', 'Une erreur inattendue s\'est produite lors du chargement des mentors.');
            return $this->render('admin/alternance/mentor/index.html.twig', [
                'mentors' => [],
                'current_page' => 1,
                'total_pages' => 0,
                'filters' => [],
                'statistics' => [],
            ]);
        }
    }

    #[Route('/new', name: 'admin_alternance_mentor_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $this->logger->info('MentorController: Starting new mentor creation', [
            'user_email' => $this->getUser()?->getUserIdentifier(),
            'request_method' => $request->getMethod()
        ]);

        try {
            $mentor = new Mentor();
            $form = $this->createForm(MentorType::class, $mentor);
            $form->handleRequest($request);

            $this->logger->debug('MentorController: Form created and request handled', [
                'form_submitted' => $form->isSubmitted(),
                'form_valid' => $form->isSubmitted() ? $form->isValid() : null
            ]);

            if ($form->isSubmitted() && $form->isValid()) {
                $this->logger->info('MentorController: Form submitted and valid, creating new mentor', [
                    'mentor_email' => $mentor->getEmail(),
                    'mentor_first_name' => $mentor->getFirstName(),
                    'mentor_last_name' => $mentor->getLastName()
                ]);

                // Generate authentication credentials
                $credentials = $this->authService->generateCredentials($mentor);

                $this->logger->debug('MentorController: Authentication credentials generated', [
                    'mentor_email' => $mentor->getEmail(),
                    'credentials_generated' => true
                ]);

                $this->entityManager->persist($mentor);
                $this->entityManager->flush();

                $this->logger->info('MentorController: New mentor created successfully', [
                    'mentor_id' => $mentor->getId(),
                    'mentor_email' => $mentor->getEmail(),
                    'created_by' => $this->getUser()?->getUserIdentifier()
                ]);

                // Send invitation email
                $this->sendInvitationEmail($mentor, $credentials);

                $this->logger->info('MentorController: Invitation email sent', [
                    'mentor_id' => $mentor->getId(),
                    'mentor_email' => $mentor->getEmail()
                ]);

                $this->addFlash('success', 'Mentor créé avec succès. Un email d\'invitation a été envoyé.');

                return $this->redirectToRoute('admin_alternance_mentor_show', [
                    'id' => $mentor->getId(),
                ]);
            }

            if ($form->isSubmitted() && !$form->isValid()) {
                $this->logger->warning('MentorController: Form submitted but invalid', [
                    'form_errors' => (string) $form->getErrors(true)
                ]);
            }

            return $this->render('admin/alternance/mentor/new.html.twig', [
                'mentor' => $mentor,
                'form' => $form,
            ]);

        } catch (DBALException $e) {
            $this->logger->error('MentorController: Database error during mentor creation', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'user_email' => $this->getUser()?->getUserIdentifier(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->addFlash('error', 'Erreur de base de données lors de la création du mentor : ' . $e->getMessage());

            $mentor = new Mentor();
            $form = $this->createForm(MentorType::class, $mentor);

            return $this->render('admin/alternance/mentor/new.html.twig', [
                'mentor' => $mentor,
                'form' => $form,
            ]);

        } catch (Exception $e) {
            $this->logger->error('MentorController: Unexpected error during mentor creation', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'user_email' => $this->getUser()?->getUserIdentifier(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->addFlash('error', 'Erreur lors de la création du mentor : ' . $e->getMessage());

            $mentor = new Mentor();
            $form = $this->createForm(MentorType::class, $mentor);

            return $this->render('admin/alternance/mentor/new.html.twig', [
                'mentor' => $mentor,
                'form' => $form,
            ]);
        }
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
        $this->logger->debug('MentorController: Calculating mentor statistics');

        try {
            $statistics = [
                'total_mentors' => $this->mentorRepository->count([]),
                'active_mentors' => $this->mentorRepository->countActive(),
                'new_this_month' => $this->mentorRepository->countCreatedThisMonth(),
                'companies_count' => $this->mentorRepository->countUniqueCompanies(),
                'average_students_per_mentor' => $this->mentorRepository->getAverageStudentsPerMentor(),
                'mentors_by_company' => $this->mentorRepository->getMentorsByCompany(),
                'qualification_distribution' => $this->mentorRepository->getQualificationDistribution(),
            ];

            $this->logger->debug('MentorController: Mentor statistics calculated', [
                'statistics' => $statistics
            ]);

            return $statistics;

        } catch (Exception $e) {
            $this->logger->error('MentorController: Error calculating mentor statistics', [
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Return empty statistics to prevent breaking the page
            return [
                'total_mentors' => 0,
                'active_mentors' => 0,
                'new_this_month' => 0,
                'companies_count' => 0,
                'average_students_per_mentor' => 0,
                'mentors_by_company' => [],
                'qualification_distribution' => [],
            ];
        }
    }

    private function sendInvitationEmail(Mentor $mentor, array $credentials): void
    {
        $this->logger->info('MentorController: Preparing invitation email', [
            'mentor_id' => $mentor->getId(),
            'mentor_email' => $mentor->getEmail(),
            'credentials_provided' => !empty($credentials)
        ]);

        try {
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

            $this->logger->info('MentorController: Invitation email sent successfully', [
                'mentor_id' => $mentor->getId(),
                'mentor_email' => $mentor->getEmail(),
                'email_subject' => $email->getSubject()
            ]);

        } catch (Exception $e) {
            $this->logger->error('MentorController: Error sending invitation email', [
                'mentor_id' => $mentor->getId(),
                'mentor_email' => $mentor->getEmail(),
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }
}
