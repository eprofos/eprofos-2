<?php

declare(strict_types=1);

namespace App\Controller\Admin\Assessment;

use App\Entity\Assessment\Question;
use App\Entity\Assessment\Questionnaire;
use App\Entity\Assessment\QuestionnaireResponse;
use App\Entity\Assessment\QuestionOption;
use App\Repository\Assessment\QuestionnaireRepository;
use App\Repository\Training\FormationRepository;
use App\Service\Assessment\QuestionnaireEmailService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Throwable;

/**
 * Admin controller for managing questionnaires (Qualiopi criteria 2.8).
 */
#[Route('/admin/questionnaires')]
class QuestionnaireController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private QuestionnaireRepository $questionnaireRepository,
        private FormationRepository $formationRepository,
        private SluggerInterface $slugger,
        private QuestionnaireEmailService $emailService,
        private LoggerInterface $logger,
    ) {}

    #[Route('', name: 'admin_questionnaire_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->logger->info('Starting questionnaire index view', [
            'user_id' => $this->getUser()?->getUserIdentifier(),
            'request_params' => $request->query->all(),
        ]);

        try {
            $search = $request->query->get('search', '');
            $type = $request->query->get('type', '');
            $status = $request->query->get('status', '');

            $this->logger->debug('Building questionnaire query with filters', [
                'search' => $search,
                'type' => $type,
                'status' => $status,
            ]);

            $queryBuilder = $this->questionnaireRepository->createQueryBuilder('q')
                ->leftJoin('q.formation', 'f')
                ->leftJoin('q.responses', 'r')
                ->addSelect('f')
                ->addSelect('COUNT(DISTINCT r.id) as responseCount')
                ->groupBy('q.id', 'f.id')
            ;

            if ($search) {
                $queryBuilder->andWhere('q.title LIKE :search OR q.description LIKE :search')
                    ->setParameter('search', '%' . $search . '%')
                ;
                $this->logger->debug('Applied search filter', ['search_term' => $search]);
            }

            if ($type) {
                $queryBuilder->andWhere('q.type = :type')
                    ->setParameter('type', $type)
                ;
                $this->logger->debug('Applied type filter', ['type' => $type]);
            }

            if ($status) {
                $queryBuilder->andWhere('q.status = :status')
                    ->setParameter('status', $status)
                ;
                $this->logger->debug('Applied status filter', ['status' => $status]);
            }

            $questionnaires = $queryBuilder->orderBy('q.createdAt', 'DESC')
                ->getQuery()
                ->getResult()
            ;

            $this->logger->info('Successfully retrieved questionnaires', [
                'count' => count($questionnaires),
                'filters_applied' => array_filter(compact('search', 'type', 'status')),
            ]);

            return $this->render('admin/questionnaire/index.html.twig', [
                'questionnaires' => $questionnaires,
                'search' => $search,
                'current_type' => $type,
                'current_status' => $status,
                'types' => Questionnaire::TYPES,
                'statuses' => [
                    Questionnaire::STATUS_DRAFT => 'Brouillon',
                    Questionnaire::STATUS_ACTIVE => 'Actif',
                    Questionnaire::STATUS_ARCHIVED => 'Archivé',
                ],
            ]);
        } catch (Throwable $e) {
            $this->logger->error('Error in questionnaire index view', [
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
                'request_params' => $request->query->all(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement des questionnaires.');

            return $this->render('admin/questionnaire/index.html.twig', [
                'questionnaires' => [],
                'search' => '',
                'current_type' => '',
                'current_status' => '',
                'types' => Questionnaire::TYPES,
                'statuses' => [
                    Questionnaire::STATUS_DRAFT => 'Brouillon',
                    Questionnaire::STATUS_ACTIVE => 'Actif',
                    Questionnaire::STATUS_ARCHIVED => 'Archivé',
                ],
            ]);
        }
    }

    #[Route('/new', name: 'admin_questionnaire_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $this->logger->info('Starting questionnaire creation', [
            'user_id' => $this->getUser()?->getUserIdentifier(),
            'method' => $request->getMethod(),
        ]);

        try {
            $questionnaire = new Questionnaire();

            if ($request->isMethod('POST')) {
                $this->logger->debug('Processing questionnaire creation form', [
                    'form_data' => $request->request->all(),
                ]);

                $this->handleQuestionnaireForm($request, $questionnaire);

                $this->entityManager->persist($questionnaire);
                $this->entityManager->flush();

                $this->logger->info('Questionnaire created successfully', [
                    'questionnaire_id' => $questionnaire->getId(),
                    'title' => $questionnaire->getTitle(),
                    'type' => $questionnaire->getType(),
                    'user_id' => $this->getUser()?->getUserIdentifier(),
                ]);

                $this->addFlash('success', 'Le questionnaire a été créé avec succès.');

                return $this->redirectToRoute('admin_questionnaire_show', ['id' => $questionnaire->getId()]);
            }

            $formations = $this->formationRepository->findBy(['isActive' => true], ['title' => 'ASC']);

            $this->logger->debug('Loaded formations for questionnaire creation', [
                'formations_count' => count($formations),
            ]);

            return $this->render('admin/questionnaire/new.html.twig', [
                'questionnaire' => $questionnaire,
                'formations' => $formations,
                'types' => Questionnaire::TYPES,
            ]);
        } catch (Throwable $e) {
            $this->logger->error('Error in questionnaire creation', [
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
                'method' => $request->getMethod(),
                'form_data' => $request->isMethod('POST') ? $request->request->all() : null,
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la création du questionnaire.');

            if ($request->isMethod('POST')) {
                return $this->redirectToRoute('admin_questionnaire_index');
            }

            return $this->render('admin/questionnaire/new.html.twig', [
                'questionnaire' => new Questionnaire(),
                'formations' => [],
                'types' => Questionnaire::TYPES,
            ]);
        }
    }

    #[Route('/{id}', name: 'admin_questionnaire_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Questionnaire $questionnaire): Response
    {
        $this->logger->info('Displaying questionnaire details', [
            'questionnaire_id' => $questionnaire->getId(),
            'title' => $questionnaire->getTitle(),
            'user_id' => $this->getUser()?->getUserIdentifier(),
        ]);

        try {
            $statistics = [
                'total_questions' => $questionnaire->getQuestionCount(),
                'total_responses' => $questionnaire->getResponseCount(),
                'completed_responses' => $questionnaire->getCompletedResponseCount(),
                'completion_rate' => $questionnaire->getCompletionRate(),
                'step_count' => $questionnaire->getStepCount(),
            ];

            $this->logger->debug('Calculated questionnaire statistics', [
                'questionnaire_id' => $questionnaire->getId(),
                'statistics' => $statistics,
            ]);

            return $this->render('admin/questionnaire/show.html.twig', [
                'questionnaire' => $questionnaire,
                'statistics' => $statistics,
            ]);
        } catch (Throwable $e) {
            $this->logger->error('Error displaying questionnaire', [
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'questionnaire_id' => $questionnaire->getId(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement du questionnaire.');

            return $this->redirectToRoute('admin_questionnaire_index');
        }
    }

    #[Route('/{id}/edit', name: 'admin_questionnaire_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Questionnaire $questionnaire): Response
    {
        $this->logger->info('Starting questionnaire edit', [
            'questionnaire_id' => $questionnaire->getId(),
            'title' => $questionnaire->getTitle(),
            'user_id' => $this->getUser()?->getUserIdentifier(),
            'method' => $request->getMethod(),
        ]);

        try {
            if ($request->isMethod('POST')) {
                $this->logger->debug('Processing questionnaire edit form', [
                    'questionnaire_id' => $questionnaire->getId(),
                    'form_data' => $request->request->all(),
                ]);

                $this->handleQuestionnaireForm($request, $questionnaire);

                $this->entityManager->flush();

                $this->logger->info('Questionnaire updated successfully', [
                    'questionnaire_id' => $questionnaire->getId(),
                    'title' => $questionnaire->getTitle(),
                    'type' => $questionnaire->getType(),
                    'user_id' => $this->getUser()?->getUserIdentifier(),
                ]);

                $this->addFlash('success', 'Le questionnaire a été modifié avec succès.');

                return $this->redirectToRoute('admin_questionnaire_show', ['id' => $questionnaire->getId()]);
            }

            $formations = $this->formationRepository->findBy(['isActive' => true], ['title' => 'ASC']);

            $this->logger->debug('Loaded formations for questionnaire edit', [
                'formations_count' => count($formations),
                'questionnaire_id' => $questionnaire->getId(),
            ]);

            return $this->render('admin/questionnaire/edit.html.twig', [
                'questionnaire' => $questionnaire,
                'formations' => $formations,
                'types' => Questionnaire::TYPES,
            ]);
        } catch (Throwable $e) {
            $this->logger->error('Error in questionnaire edit', [
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'questionnaire_id' => $questionnaire->getId(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
                'method' => $request->getMethod(),
                'form_data' => $request->isMethod('POST') ? $request->request->all() : null,
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la modification du questionnaire.');

            if ($request->isMethod('POST')) {
                return $this->redirectToRoute('admin_questionnaire_show', ['id' => $questionnaire->getId()]);
            }

            return $this->render('admin/questionnaire/edit.html.twig', [
                'questionnaire' => $questionnaire,
                'formations' => [],
                'types' => Questionnaire::TYPES,
            ]);
        }
    }

    #[Route('/{id}/delete', name: 'admin_questionnaire_delete', methods: ['POST'])]
    public function delete(Request $request, Questionnaire $questionnaire): Response
    {
        $questionnaireId = $questionnaire->getId();
        $questionnaireTitle = $questionnaire->getTitle();

        $this->logger->info('Starting questionnaire deletion', [
            'questionnaire_id' => $questionnaireId,
            'title' => $questionnaireTitle,
            'user_id' => $this->getUser()?->getUserIdentifier(),
            'csrf_token' => $request->request->get('_token'),
        ]);

        try {
            if ($this->isCsrfTokenValid('delete' . $questionnaire->getId(), $request->request->get('_token'))) {
                $this->entityManager->remove($questionnaire);
                $this->entityManager->flush();

                $this->logger->info('Questionnaire deleted successfully', [
                    'questionnaire_id' => $questionnaireId,
                    'title' => $questionnaireTitle,
                    'user_id' => $this->getUser()?->getUserIdentifier(),
                ]);

                $this->addFlash('success', 'Le questionnaire a été supprimé avec succès.');
            } else {
                $this->logger->warning('Invalid CSRF token for questionnaire deletion', [
                    'questionnaire_id' => $questionnaireId,
                    'user_id' => $this->getUser()?->getUserIdentifier(),
                    'csrf_token' => $request->request->get('_token'),
                ]);

                $this->addFlash('error', 'Token CSRF invalide.');
            }
        } catch (Throwable $e) {
            $this->logger->error('Error deleting questionnaire', [
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'questionnaire_id' => $questionnaireId,
                'title' => $questionnaireTitle,
                'user_id' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la suppression du questionnaire.');
        }

        return $this->redirectToRoute('admin_questionnaire_index');
    }

    #[Route('/{id}/duplicate', name: 'admin_questionnaire_duplicate', methods: ['POST'])]
    public function duplicate(Request $request, Questionnaire $questionnaire): Response
    {
        $this->logger->info('Starting questionnaire duplication', [
            'source_questionnaire_id' => $questionnaire->getId(),
            'title' => $questionnaire->getTitle(),
            'user_id' => $this->getUser()?->getUserIdentifier(),
        ]);

        try {
            if (!$this->isCsrfTokenValid('duplicate' . $questionnaire->getId(), $request->request->get('_token'))) {
                $this->logger->warning('Invalid CSRF token for questionnaire duplication', [
                    'questionnaire_id' => $questionnaire->getId(),
                    'user_id' => $this->getUser()?->getUserIdentifier(),
                    'csrf_token' => $request->request->get('_token'),
                ]);

                $this->addFlash('error', 'Token CSRF invalide.');

                return $this->redirectToRoute('admin_questionnaire_index');
            }

            $newQuestionnaire = new Questionnaire();
            $newQuestionnaire->setTitle($questionnaire->getTitle() . ' (Copie)')
                ->setDescription($questionnaire->getDescription())
                ->setType($questionnaire->getType())
                ->setStatus(Questionnaire::STATUS_DRAFT)
                ->setIsMultiStep($questionnaire->isMultiStep())
                ->setQuestionsPerStep($questionnaire->getQuestionsPerStep())
                ->setAllowBackNavigation($questionnaire->isAllowBackNavigation())
                ->setShowProgressBar($questionnaire->isShowProgressBar())
                ->setRequireAllQuestions($questionnaire->isRequireAllQuestions())
                ->setTimeLimitMinutes($questionnaire->getTimeLimitMinutes())
                ->setWelcomeMessage($questionnaire->getWelcomeMessage())
                ->setCompletionMessage($questionnaire->getCompletionMessage())
                ->setEmailSubject($questionnaire->getEmailSubject())
                ->setEmailTemplate($questionnaire->getEmailTemplate())
                ->setFormation($questionnaire->getFormation())
            ;

            $newQuestionnaire->generateSlug($this->slugger);

            $this->logger->debug('Created new questionnaire instance for duplication', [
                'new_title' => $newQuestionnaire->getTitle(),
                'source_id' => $questionnaire->getId(),
            ]);

            $this->entityManager->persist($newQuestionnaire);

            // Duplicate questions and their options
            $questionCount = 0;
            $optionCount = 0;

            foreach ($questionnaire->getQuestions() as $question) {
                $newQuestion = new Question();
                $newQuestion->setQuestionnaire($newQuestionnaire)
                    ->setQuestionText($question->getQuestionText())
                    ->setType($question->getType())
                    ->setOrderIndex($question->getOrderIndex())
                    ->setIsRequired($question->isRequired())
                    ->setIsActive($question->isActive())
                    ->setHelpText($question->getHelpText())
                    ->setPlaceholder($question->getPlaceholder())
                    ->setMinLength($question->getMinLength())
                    ->setMaxLength($question->getMaxLength())
                    ->setValidationRules($question->getValidationRules())
                    ->setAllowedFileTypes($question->getAllowedFileTypes())
                    ->setMaxFileSize($question->getMaxFileSize())
                    ->setPoints($question->getPoints())
                ;

                $this->entityManager->persist($newQuestion);
                $questionCount++;

                // Duplicate options
                foreach ($question->getOptions() as $option) {
                    $newOption = new QuestionOption();
                    $newOption->setQuestion($newQuestion)
                        ->setOptionText($option->getOptionText())
                        ->setOrderIndex($option->getOrderIndex())
                        ->setIsCorrect($option->isCorrect())
                        ->setIsActive($option->isActive())
                        ->setPoints($option->getPoints())
                        ->setExplanation($option->getExplanation())
                    ;

                    $this->entityManager->persist($newOption);
                    $optionCount++;
                }
            }

            $this->entityManager->flush();

            $this->logger->info('Questionnaire duplicated successfully', [
                'source_questionnaire_id' => $questionnaire->getId(),
                'new_questionnaire_id' => $newQuestionnaire->getId(),
                'new_title' => $newQuestionnaire->getTitle(),
                'questions_duplicated' => $questionCount,
                'options_duplicated' => $optionCount,
                'user_id' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('success', 'Le questionnaire a été dupliqué avec succès.');

            return $this->redirectToRoute('admin_questionnaire_show', ['id' => $newQuestionnaire->getId()]);
        } catch (Throwable $e) {
            $this->logger->error('Error duplicating questionnaire', [
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'source_questionnaire_id' => $questionnaire->getId(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la duplication du questionnaire.');

            return $this->redirectToRoute('admin_questionnaire_index');
        }
    }

    #[Route('/{id}/activate', name: 'admin_questionnaire_activate', methods: ['POST'])]
    public function activate(Request $request, Questionnaire $questionnaire): Response
    {
        $this->logger->info('Starting questionnaire activation', [
            'questionnaire_id' => $questionnaire->getId(),
            'title' => $questionnaire->getTitle(),
            'current_status' => $questionnaire->getStatus(),
            'user_id' => $this->getUser()?->getUserIdentifier(),
        ]);

        try {
            if (!$this->isCsrfTokenValid('activate' . $questionnaire->getId(), $request->request->get('_token'))) {
                $this->logger->warning('Invalid CSRF token for questionnaire activation', [
                    'questionnaire_id' => $questionnaire->getId(),
                    'user_id' => $this->getUser()?->getUserIdentifier(),
                    'csrf_token' => $request->request->get('_token'),
                ]);

                $this->addFlash('error', 'Token CSRF invalide.');

                return $this->redirectToRoute('admin_questionnaire_index');
            }

            if ($questionnaire->getQuestionCount() === 0) {
                $this->logger->warning('Attempted to activate questionnaire without questions', [
                    'questionnaire_id' => $questionnaire->getId(),
                    'title' => $questionnaire->getTitle(),
                    'user_id' => $this->getUser()?->getUserIdentifier(),
                ]);

                $this->addFlash('error', 'Impossible d\'activer un questionnaire sans questions.');

                return $this->redirectToRoute('admin_questionnaire_show', ['id' => $questionnaire->getId()]);
            }

            $questionnaire->setStatus(Questionnaire::STATUS_ACTIVE);
            $this->entityManager->flush();

            $this->logger->info('Questionnaire activated successfully', [
                'questionnaire_id' => $questionnaire->getId(),
                'title' => $questionnaire->getTitle(),
                'question_count' => $questionnaire->getQuestionCount(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('success', 'Le questionnaire a été activé avec succès.');

            return $this->redirectToRoute('admin_questionnaire_show', ['id' => $questionnaire->getId()]);
        } catch (Throwable $e) {
            $this->logger->error('Error activating questionnaire', [
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'questionnaire_id' => $questionnaire->getId(),
                'title' => $questionnaire->getTitle(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de l\'activation du questionnaire.');

            return $this->redirectToRoute('admin_questionnaire_show', ['id' => $questionnaire->getId()]);
        }
    }

    #[Route('/{id}/archive', name: 'admin_questionnaire_archive', methods: ['POST'])]
    public function archive(Request $request, Questionnaire $questionnaire): Response
    {
        $this->logger->info('Starting questionnaire archiving', [
            'questionnaire_id' => $questionnaire->getId(),
            'title' => $questionnaire->getTitle(),
            'current_status' => $questionnaire->getStatus(),
            'user_id' => $this->getUser()?->getUserIdentifier(),
        ]);

        try {
            if (!$this->isCsrfTokenValid('archive' . $questionnaire->getId(), $request->request->get('_token'))) {
                $this->logger->warning('Invalid CSRF token for questionnaire archiving', [
                    'questionnaire_id' => $questionnaire->getId(),
                    'user_id' => $this->getUser()?->getUserIdentifier(),
                    'csrf_token' => $request->request->get('_token'),
                ]);

                $this->addFlash('error', 'Token CSRF invalide.');

                return $this->redirectToRoute('admin_questionnaire_index');
            }

            $questionnaire->setStatus(Questionnaire::STATUS_ARCHIVED);
            $this->entityManager->flush();

            $this->logger->info('Questionnaire archived successfully', [
                'questionnaire_id' => $questionnaire->getId(),
                'title' => $questionnaire->getTitle(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('success', 'Le questionnaire a été archivé avec succès.');

            return $this->redirectToRoute('admin_questionnaire_show', ['id' => $questionnaire->getId()]);
        } catch (Throwable $e) {
            $this->logger->error('Error archiving questionnaire', [
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'questionnaire_id' => $questionnaire->getId(),
                'title' => $questionnaire->getTitle(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de l\'archivage du questionnaire.');

            return $this->redirectToRoute('admin_questionnaire_show', ['id' => $questionnaire->getId()]);
        }
    }

    #[Route('/send', name: 'admin_questionnaire_send', methods: ['GET', 'POST'])]
    public function send(Request $request): Response
    {
        $this->logger->info('Starting questionnaire send process', [
            'user_id' => $this->getUser()?->getUserIdentifier(),
            'method' => $request->getMethod(),
            'preselected_questionnaire' => $request->query->get('questionnaire'),
        ]);

        try {
            $questionnaires = $this->questionnaireRepository->findActive();
            $formations = $this->formationRepository->findBy(['isActive' => true], ['title' => 'ASC']);

            $this->logger->debug('Loaded questionnaires and formations for send form', [
                'active_questionnaires_count' => count($questionnaires),
                'active_formations_count' => count($formations),
            ]);

            // Pre-select questionnaire if provided in URL
            $preselectedQuestionnaireId = $request->query->get('questionnaire');
            $preselectedQuestionnaire = null;
            if ($preselectedQuestionnaireId) {
                $preselectedQuestionnaire = $this->questionnaireRepository->find($preselectedQuestionnaireId);
                if (!$preselectedQuestionnaire || !$preselectedQuestionnaire->isActive()) {
                    $this->logger->warning('Invalid preselected questionnaire', [
                        'questionnaire_id' => $preselectedQuestionnaireId,
                        'exists' => $preselectedQuestionnaire !== null,
                        'is_active' => $preselectedQuestionnaire?->isActive() ?? false,
                    ]);
                    $preselectedQuestionnaire = null;
                }
            }

            if ($request->isMethod('POST')) {
                $data = $request->request->all();

                $this->logger->debug('Processing questionnaire send form', [
                    'form_data' => array_merge($data, ['email' => '***HIDDEN***']), // Hide email for security
                    'questionnaire_id' => $data['questionnaire_id'] ?? null,
                    'formation_id' => $data['formation_id'] ?? null,
                ]);

                $questionnaire = $this->questionnaireRepository->find($data['questionnaire_id'] ?? 0);
                if (!$questionnaire || !$questionnaire->isActive()) {
                    $this->logger->warning('Invalid questionnaire selected for sending', [
                        'questionnaire_id' => $data['questionnaire_id'] ?? 0,
                        'exists' => $questionnaire !== null,
                        'is_active' => $questionnaire?->isActive() ?? false,
                    ]);

                    $this->addFlash('error', 'Questionnaire non trouvé ou inactif.');

                    return $this->redirectToRoute('admin_questionnaire_send');
                }

                $formation = null;
                if (!empty($data['formation_id'])) {
                    $formation = $this->formationRepository->find($data['formation_id']);
                    $this->logger->debug('Formation selected for questionnaire', [
                        'formation_id' => $data['formation_id'],
                        'formation_found' => $formation !== null,
                        'formation_title' => $formation?->getTitle(),
                    ]);
                }

                // Create questionnaire response
                $response = new QuestionnaireResponse();
                $response->setQuestionnaire($questionnaire)
                    ->setFormation($formation)
                    ->setFirstName($data['first_name'] ?? '')
                    ->setLastName($data['last_name'] ?? '')
                    ->setEmail($data['email'] ?? '')
                    ->setPhone($data['phone'] ?? null)
                    ->setCompany($data['company'] ?? null)
                ;

                $this->entityManager->persist($response);
                $this->entityManager->flush();

                $this->logger->info('Questionnaire response created for sending', [
                    'response_id' => $response->getId(),
                    'questionnaire_id' => $questionnaire->getId(),
                    'formation_id' => $formation?->getId(),
                    'recipient_email' => $response->getEmail(),
                    'user_id' => $this->getUser()?->getUserIdentifier(),
                ]);

                // Send email
                try {
                    $this->emailService->sendQuestionnaireLink($response);

                    $this->logger->info('Questionnaire email sent successfully', [
                        'response_id' => $response->getId(),
                        'questionnaire_id' => $questionnaire->getId(),
                        'recipient_email' => $response->getEmail(),
                        'user_id' => $this->getUser()?->getUserIdentifier(),
                    ]);

                    $this->addFlash('success', 'Le questionnaire a été envoyé avec succès à ' . $response->getEmail());
                } catch (Exception $e) {
                    $this->logger->error('Error sending questionnaire email', [
                        'error_message' => $e->getMessage(),
                        'error_trace' => $e->getTraceAsString(),
                        'response_id' => $response->getId(),
                        'questionnaire_id' => $questionnaire->getId(),
                        'recipient_email' => $response->getEmail(),
                        'user_id' => $this->getUser()?->getUserIdentifier(),
                    ]);

                    $this->addFlash('error', 'Erreur lors de l\'envoi de l\'email : ' . $e->getMessage());
                }

                return $this->redirectToRoute('admin_questionnaire_send');
            }

            return $this->render('admin/questionnaire/send.html.twig', [
                'questionnaires' => $questionnaires,
                'formations' => $formations,
                'preselected_questionnaire' => $preselectedQuestionnaire,
            ]);
        } catch (Throwable $e) {
            $this->logger->error('Error in questionnaire send process', [
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
                'method' => $request->getMethod(),
                'form_data' => $request->isMethod('POST') ? 
                    array_merge($request->request->all(), ['email' => '***HIDDEN***']) : null,
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du processus d\'envoi du questionnaire.');

            return $this->render('admin/questionnaire/send.html.twig', [
                'questionnaires' => [],
                'formations' => [],
                'preselected_questionnaire' => null,
            ]);
        }
    }

    private function handleQuestionnaireForm(Request $request, Questionnaire $questionnaire): void
    {
        $this->logger->debug('Starting questionnaire form handling', [
            'questionnaire_id' => $questionnaire->getId() ?: 'new',
            'existing_title' => $questionnaire->getTitle(),
            'user_id' => $this->getUser()?->getUserIdentifier(),
        ]);

        try {
            $data = $request->request->all();

            $questionnaire->setTitle($data['title'] ?? '')
                ->setDescription($data['description'] ?? null)
                ->setType($data['type'] ?? 'positioning')
                ->setIsMultiStep($data['is_multi_step'] ?? false)
                ->setQuestionsPerStep((int) ($data['questions_per_step'] ?? 5))
                ->setAllowBackNavigation($data['allow_back_navigation'] ?? false)
                ->setShowProgressBar($data['show_progress_bar'] ?? false)
                ->setRequireAllQuestions($data['require_all_questions'] ?? false)
                ->setTimeLimitMinutes($data['time_limit_minutes'] ? (int) $data['time_limit_minutes'] : null)
                ->setWelcomeMessage($data['welcome_message'] ?? null)
                ->setCompletionMessage($data['completion_message'] ?? null)
                ->setEmailSubject($data['email_subject'] ?? null)
                ->setEmailTemplate($data['email_template'] ?? null)
            ;

            if (!empty($data['formation_id'])) {
                $formation = $this->formationRepository->find($data['formation_id']);
                $questionnaire->setFormation($formation);

                $this->logger->debug('Formation associated with questionnaire', [
                    'formation_id' => $data['formation_id'],
                    'formation_found' => $formation !== null,
                    'formation_title' => $formation?->getTitle(),
                ]);
            } else {
                $questionnaire->setFormation(null);
                $this->logger->debug('No formation associated with questionnaire');
            }

            $questionnaire->generateSlug($this->slugger);

            $this->logger->info('Questionnaire form processed successfully', [
                'questionnaire_id' => $questionnaire->getId() ?: 'new',
                'title' => $questionnaire->getTitle(),
                'type' => $questionnaire->getType(),
                'formation_id' => $questionnaire->getFormation()?->getId(),
                'is_multi_step' => $questionnaire->isMultiStep(),
                'questions_per_step' => $questionnaire->getQuestionsPerStep(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
            ]);
        } catch (Throwable $e) {
            $this->logger->error('Error handling questionnaire form', [
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'questionnaire_id' => $questionnaire->getId() ?: 'new',
                'form_data' => $request->request->all(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
            ]);

            throw $e; // Re-throw to be caught by calling method
        }
    }
}
