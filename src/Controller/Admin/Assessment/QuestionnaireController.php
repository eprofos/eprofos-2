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
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

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
    ) {}

    #[Route('', name: 'admin_questionnaire_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $search = $request->query->get('search', '');
        $type = $request->query->get('type', '');
        $status = $request->query->get('status', '');

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
        }

        if ($type) {
            $queryBuilder->andWhere('q.type = :type')
                ->setParameter('type', $type)
            ;
        }

        if ($status) {
            $queryBuilder->andWhere('q.status = :status')
                ->setParameter('status', $status)
            ;
        }

        $questionnaires = $queryBuilder->orderBy('q.createdAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;

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
    }

    #[Route('/new', name: 'admin_questionnaire_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $questionnaire = new Questionnaire();

        if ($request->isMethod('POST')) {
            $this->handleQuestionnaireForm($request, $questionnaire);

            $this->entityManager->persist($questionnaire);
            $this->entityManager->flush();

            $this->addFlash('success', 'Le questionnaire a été créé avec succès.');

            return $this->redirectToRoute('admin_questionnaire_show', ['id' => $questionnaire->getId()]);
        }

        $formations = $this->formationRepository->findBy(['isActive' => true], ['title' => 'ASC']);

        return $this->render('admin/questionnaire/new.html.twig', [
            'questionnaire' => $questionnaire,
            'formations' => $formations,
            'types' => Questionnaire::TYPES,
        ]);
    }

    #[Route('/{id}', name: 'admin_questionnaire_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Questionnaire $questionnaire): Response
    {
        $statistics = [
            'total_questions' => $questionnaire->getQuestionCount(),
            'total_responses' => $questionnaire->getResponseCount(),
            'completed_responses' => $questionnaire->getCompletedResponseCount(),
            'completion_rate' => $questionnaire->getCompletionRate(),
            'step_count' => $questionnaire->getStepCount(),
        ];

        return $this->render('admin/questionnaire/show.html.twig', [
            'questionnaire' => $questionnaire,
            'statistics' => $statistics,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_questionnaire_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Questionnaire $questionnaire): Response
    {
        if ($request->isMethod('POST')) {
            $this->handleQuestionnaireForm($request, $questionnaire);

            $this->entityManager->flush();

            $this->addFlash('success', 'Le questionnaire a été modifié avec succès.');

            return $this->redirectToRoute('admin_questionnaire_show', ['id' => $questionnaire->getId()]);
        }

        $formations = $this->formationRepository->findBy(['isActive' => true], ['title' => 'ASC']);

        return $this->render('admin/questionnaire/edit.html.twig', [
            'questionnaire' => $questionnaire,
            'formations' => $formations,
            'types' => Questionnaire::TYPES,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_questionnaire_delete', methods: ['POST'])]
    public function delete(Request $request, Questionnaire $questionnaire): Response
    {
        if ($this->isCsrfTokenValid('delete' . $questionnaire->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($questionnaire);
            $this->entityManager->flush();

            $this->addFlash('success', 'Le questionnaire a été supprimé avec succès.');
        } else {
            $this->addFlash('error', 'Token CSRF invalide.');
        }

        return $this->redirectToRoute('admin_questionnaire_index');
    }

    #[Route('/{id}/duplicate', name: 'admin_questionnaire_duplicate', methods: ['POST'])]
    public function duplicate(Request $request, Questionnaire $questionnaire): Response
    {
        if (!$this->isCsrfTokenValid('duplicate' . $questionnaire->getId(), $request->request->get('_token'))) {
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

        $this->entityManager->persist($newQuestionnaire);

        // Duplicate questions and their options
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
            }
        }

        $this->entityManager->flush();

        $this->addFlash('success', 'Le questionnaire a été dupliqué avec succès.');

        return $this->redirectToRoute('admin_questionnaire_show', ['id' => $newQuestionnaire->getId()]);
    }

    #[Route('/{id}/activate', name: 'admin_questionnaire_activate', methods: ['POST'])]
    public function activate(Request $request, Questionnaire $questionnaire): Response
    {
        if (!$this->isCsrfTokenValid('activate' . $questionnaire->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');

            return $this->redirectToRoute('admin_questionnaire_index');
        }

        if ($questionnaire->getQuestionCount() === 0) {
            $this->addFlash('error', 'Impossible d\'activer un questionnaire sans questions.');

            return $this->redirectToRoute('admin_questionnaire_show', ['id' => $questionnaire->getId()]);
        }

        $questionnaire->setStatus(Questionnaire::STATUS_ACTIVE);
        $this->entityManager->flush();

        $this->addFlash('success', 'Le questionnaire a été activé avec succès.');

        return $this->redirectToRoute('admin_questionnaire_show', ['id' => $questionnaire->getId()]);
    }

    #[Route('/{id}/archive', name: 'admin_questionnaire_archive', methods: ['POST'])]
    public function archive(Request $request, Questionnaire $questionnaire): Response
    {
        if (!$this->isCsrfTokenValid('archive' . $questionnaire->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');

            return $this->redirectToRoute('admin_questionnaire_index');
        }

        $questionnaire->setStatus(Questionnaire::STATUS_ARCHIVED);
        $this->entityManager->flush();

        $this->addFlash('success', 'Le questionnaire a été archivé avec succès.');

        return $this->redirectToRoute('admin_questionnaire_show', ['id' => $questionnaire->getId()]);
    }

    #[Route('/send', name: 'admin_questionnaire_send', methods: ['GET', 'POST'])]
    public function send(Request $request): Response
    {
        $questionnaires = $this->questionnaireRepository->findActive();
        $formations = $this->formationRepository->findBy(['isActive' => true], ['title' => 'ASC']);

        // Pre-select questionnaire if provided in URL
        $preselectedQuestionnaireId = $request->query->get('questionnaire');
        $preselectedQuestionnaire = null;
        if ($preselectedQuestionnaireId) {
            $preselectedQuestionnaire = $this->questionnaireRepository->find($preselectedQuestionnaireId);
            if (!$preselectedQuestionnaire || !$preselectedQuestionnaire->isActive()) {
                $preselectedQuestionnaire = null;
            }
        }

        if ($request->isMethod('POST')) {
            $data = $request->request->all();

            $questionnaire = $this->questionnaireRepository->find($data['questionnaire_id'] ?? 0);
            if (!$questionnaire || !$questionnaire->isActive()) {
                $this->addFlash('error', 'Questionnaire non trouvé ou inactif.');

                return $this->redirectToRoute('admin_questionnaire_send');
            }

            $formation = null;
            if (!empty($data['formation_id'])) {
                $formation = $this->formationRepository->find($data['formation_id']);
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

            // Send email
            try {
                $this->emailService->sendQuestionnaireLink($response);
                $this->addFlash('success', 'Le questionnaire a été envoyé avec succès à ' . $response->getEmail());
            } catch (Exception $e) {
                $this->addFlash('error', 'Erreur lors de l\'envoi de l\'email : ' . $e->getMessage());
            }

            return $this->redirectToRoute('admin_questionnaire_send');
        }

        return $this->render('admin/questionnaire/send.html.twig', [
            'questionnaires' => $questionnaires,
            'formations' => $formations,
            'preselected_questionnaire' => $preselectedQuestionnaire,
        ]);
    }

    private function handleQuestionnaireForm(Request $request, Questionnaire $questionnaire): void
    {
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
        } else {
            $questionnaire->setFormation(null);
        }

        $questionnaire->generateSlug($this->slugger);
    }
}
