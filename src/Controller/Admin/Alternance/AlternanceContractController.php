<?php

declare(strict_types=1);

namespace App\Controller\Admin\Alternance;

use App\Entity\Alternance\AlternanceContract;
use App\Form\Alternance\AlternanceContractType;
use App\Repository\Alternance\AlternanceContractRepository;
use App\Service\Alternance\AlternanceContractService;
use App\Service\Alternance\AlternanceValidationService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/alternance/contracts')]
#[IsGranted('ROLE_ADMIN')]
class AlternanceContractController extends AbstractController
{
    public function __construct(
        private AlternanceContractRepository $contractRepository,
        private AlternanceContractService $contractService,
        private AlternanceValidationService $validationService,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    #[Route('', name: 'admin_alternance_contract_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->logger->info('AlternanceContractController::index - Starting contracts listing', [
            'user_id' => $this->getUser()?->getUserIdentifier(),
            'request_uri' => $request->getRequestUri(),
        ]);

        try {
            $page = $request->query->getInt('page', 1);
            $search = $request->query->get('search', '');
            $status = $request->query->get('status', '');
            $perPage = 20;

            $filters = [
                'search' => $search,
                'status' => $status,
            ];

            $this->logger->debug('AlternanceContractController::index - Processing filters', [
                'filters' => $filters,
                'page' => $page,
                'per_page' => $perPage,
            ]);

            $contracts = $this->contractRepository->findPaginatedContracts($filters, $page, $perPage);
            $totalContracts = $this->contractRepository->countFilteredContracts($filters);
            $totalPages = ceil($totalContracts / $perPage);

            $this->logger->debug('AlternanceContractController::index - Contracts retrieved', [
                'total_contracts' => $totalContracts,
                'contracts_count' => count($contracts),
                'total_pages' => $totalPages,
            ]);

            // Get statistics for dashboard
            $statistics = $this->contractRepository->getContractStatistics();

            $this->logger->debug('AlternanceContractController::index - Statistics retrieved', [
                'statistics' => $statistics,
            ]);

            $this->logger->info('AlternanceContractController::index - Successfully completed contracts listing', [
                'total_contracts' => $totalContracts,
                'current_page' => $page,
            ]);

            return $this->render('admin/alternance/contract/index.html.twig', [
                'contracts' => $contracts,
                'current_page' => $page,
                'total_pages' => $totalPages,
                'filters' => $filters,
                'statistics' => $statistics,
            ]);
        } catch (Exception $e) {
            $this->logger->error('AlternanceContractController::index - Error occurred while listing contracts', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement des contrats d\'alternance.');

            return $this->render('admin/alternance/contract/index.html.twig', [
                'contracts' => [],
                'current_page' => 1,
                'total_pages' => 0,
                'filters' => [],
                'statistics' => [],
            ]);
        }
    }

    #[Route('/new', name: 'admin_alternance_contract_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $this->logger->info('AlternanceContractController::new - Starting contract creation', [
            'user_id' => $this->getUser()?->getUserIdentifier(),
            'method' => $request->getMethod(),
        ]);

        try {
            $contract = new AlternanceContract();
            $form = $this->createForm(AlternanceContractType::class, $contract);

            $this->logger->debug('AlternanceContractController::new - Form created successfully');

            $form->handleRequest($request);

            if ($form->isSubmitted()) {
                $this->logger->debug('AlternanceContractController::new - Form submitted', [
                    'is_valid' => $form->isValid(),
                    'form_errors' => $form->getErrors(true)->count(),
                ]);

                if ($form->isValid()) {
                    try {
                        $this->logger->debug('AlternanceContractController::new - Starting contract validation', [
                            'contract_data' => [
                                'student_id' => $contract->getStudent()?->getId(),
                                'mentor_id' => $contract->getMentor()?->getId(),
                                'start_date' => $contract->getStartDate()?->format('Y-m-d'),
                                'end_date' => $contract->getEndDate()?->format('Y-m-d'),
                                'status' => $contract->getStatus(),
                            ],
                        ]);

                        // Validate contract data
                        $validationErrors = $this->validationService->validateContract($contract);

                        if (!empty($validationErrors)) {
                            $this->logger->warning('AlternanceContractController::new - Contract validation failed', [
                                'validation_errors' => $validationErrors,
                            ]);

                            foreach ($validationErrors as $error) {
                                $this->addFlash('error', $error);
                            }

                            return $this->render('admin/alternance/contract/new.html.twig', [
                                'contract' => $contract,
                                'form' => $form,
                            ]);
                        }

                        $this->logger->debug('AlternanceContractController::new - Contract validation successful, persisting');

                        // Persist the contract
                        $this->entityManager->persist($contract);
                        $this->entityManager->flush();

                        $this->logger->info('AlternanceContractController::new - Contract created successfully', [
                            'contract_id' => $contract->getId(),
                            'student_id' => $contract->getStudent()?->getId(),
                            'user_id' => $this->getUser()?->getUserIdentifier(),
                        ]);

                        $this->addFlash('success', 'Contrat d\'alternance créé avec succès.');

                        return $this->redirectToRoute('admin_alternance_contract_show', [
                            'id' => $contract->getId(),
                        ]);
                    } catch (Exception $e) {
                        $this->logger->error('AlternanceContractController::new - Error during contract creation', [
                            'error_message' => $e->getMessage(),
                            'error_code' => $e->getCode(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                            'trace' => $e->getTraceAsString(),
                            'user_id' => $this->getUser()?->getUserIdentifier(),
                        ]);

                        $this->addFlash('error', 'Erreur lors de la création du contrat : ' . $e->getMessage());
                    }
                }
            }

            $this->logger->debug('AlternanceContractController::new - Rendering form template');

            return $this->render('admin/alternance/contract/new.html.twig', [
                'contract' => $contract,
                'form' => $form,
            ]);
        } catch (Exception $e) {
            $this->logger->error('AlternanceContractController::new - Critical error in contract creation process', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('error', 'Une erreur critique est survenue lors de la création du contrat.');

            return $this->redirectToRoute('admin_alternance_contract_index');
        }
    }

    #[Route('/{id}', name: 'admin_alternance_contract_show', methods: ['GET'])]
    public function show(AlternanceContract $contract): Response
    {
        $this->logger->info('AlternanceContractController::show - Starting contract display', [
            'contract_id' => $contract->getId(),
            'user_id' => $this->getUser()?->getUserIdentifier(),
        ]);

        try {
            $this->logger->debug('AlternanceContractController::show - Performing contract validation');

            // Perform validation checks
            $validationErrors = $this->validationService->validateContract($contract);

            $this->logger->debug('AlternanceContractController::show - Validation completed', [
                'validation_errors_count' => count($validationErrors),
                'has_errors' => !empty($validationErrors),
            ]);

            if (!empty($validationErrors)) {
                $this->logger->warning('AlternanceContractController::show - Contract has validation errors', [
                    'contract_id' => $contract->getId(),
                    'validation_errors' => $validationErrors,
                ]);
            }

            $this->logger->info('AlternanceContractController::show - Successfully displaying contract', [
                'contract_id' => $contract->getId(),
                'contract_status' => $contract->getStatus(),
            ]);

            return $this->render('admin/alternance/contract/show.html.twig', [
                'contract' => $contract,
                'validation_errors' => $validationErrors,
            ]);
        } catch (Exception $e) {
            $this->logger->error('AlternanceContractController::show - Error occurred while displaying contract', [
                'contract_id' => $contract->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de l\'affichage du contrat.');

            return $this->redirectToRoute('admin_alternance_contract_index');
        }
    }

    #[Route('/{id}/edit', name: 'admin_alternance_contract_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, AlternanceContract $contract): Response
    {
        $this->logger->info('AlternanceContractController::edit - Starting contract edition', [
            'contract_id' => $contract->getId(),
            'user_id' => $this->getUser()?->getUserIdentifier(),
            'method' => $request->getMethod(),
        ]);

        try {
            $form = $this->createForm(AlternanceContractType::class, $contract);

            $this->logger->debug('AlternanceContractController::edit - Form created successfully');

            $form->handleRequest($request);

            if ($form->isSubmitted()) {
                $this->logger->debug('AlternanceContractController::edit - Form submitted', [
                    'is_valid' => $form->isValid(),
                    'form_errors' => $form->getErrors(true)->count(),
                ]);

                if ($form->isValid()) {
                    try {
                        $this->logger->debug('AlternanceContractController::edit - Starting contract validation', [
                            'contract_id' => $contract->getId(),
                            'contract_status' => $contract->getStatus(),
                        ]);

                        // Validate contract data
                        $validationErrors = $this->validationService->validateContract($contract);

                        if (!empty($validationErrors)) {
                            $this->logger->warning('AlternanceContractController::edit - Contract validation failed', [
                                'contract_id' => $contract->getId(),
                                'validation_errors' => $validationErrors,
                            ]);

                            foreach ($validationErrors as $error) {
                                $this->addFlash('error', $error);
                            }

                            return $this->render('admin/alternance/contract/edit.html.twig', [
                                'contract' => $contract,
                                'form' => $form,
                            ]);
                        }

                        $this->logger->debug('AlternanceContractController::edit - Contract validation successful, persisting changes');

                        // Persist the updated contract
                        $this->entityManager->flush();

                        $this->logger->info('AlternanceContractController::edit - Contract updated successfully', [
                            'contract_id' => $contract->getId(),
                            'user_id' => $this->getUser()?->getUserIdentifier(),
                        ]);

                        $this->addFlash('success', 'Contrat d\'alternance modifié avec succès.');

                        return $this->redirectToRoute('admin_alternance_contract_show', [
                            'id' => $contract->getId(),
                        ]);
                    } catch (Exception $e) {
                        $this->logger->error('AlternanceContractController::edit - Error during contract update', [
                            'contract_id' => $contract->getId(),
                            'error_message' => $e->getMessage(),
                            'error_code' => $e->getCode(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                            'trace' => $e->getTraceAsString(),
                            'user_id' => $this->getUser()?->getUserIdentifier(),
                        ]);

                        $this->addFlash('error', 'Erreur lors de la modification du contrat : ' . $e->getMessage());
                    }
                }
            }

            $this->logger->debug('AlternanceContractController::edit - Rendering edit form template');

            return $this->render('admin/alternance/contract/edit.html.twig', [
                'contract' => $contract,
                'form' => $form,
            ]);
        } catch (Exception $e) {
            $this->logger->error('AlternanceContractController::edit - Critical error in contract edition process', [
                'contract_id' => $contract->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('error', 'Une erreur critique est survenue lors de la modification du contrat.');

            return $this->redirectToRoute('admin_alternance_contract_show', ['id' => $contract->getId()]);
        }
    }

    #[Route('/{id}/status', name: 'admin_alternance_contract_status', methods: ['POST'])]
    public function changeStatus(Request $request, AlternanceContract $contract): Response
    {
        $this->logger->info('AlternanceContractController::changeStatus - Starting status change', [
            'contract_id' => $contract->getId(),
            'current_status' => $contract->getStatus(),
            'user_id' => $this->getUser()?->getUserIdentifier(),
        ]);

        try {
            $newStatus = $request->request->get('status');
            $allowedStatuses = ['draft', 'pending_validation', 'validated', 'active', 'suspended', 'completed', 'terminated'];

            $this->logger->debug('AlternanceContractController::changeStatus - Processing status change request', [
                'contract_id' => $contract->getId(),
                'requested_status' => $newStatus,
                'allowed_statuses' => $allowedStatuses,
            ]);

            if (!in_array($newStatus, $allowedStatuses, true)) {
                $this->logger->warning('AlternanceContractController::changeStatus - Invalid status requested', [
                    'contract_id' => $contract->getId(),
                    'requested_status' => $newStatus,
                    'allowed_statuses' => $allowedStatuses,
                ]);

                $this->addFlash('error', 'Statut invalide.');

                return $this->redirectToRoute('admin_alternance_contract_show', ['id' => $contract->getId()]);
            }

            $oldStatus = $contract->getStatus();
            $contract->setStatus($newStatus);
            $this->entityManager->flush();

            $this->logger->info('AlternanceContractController::changeStatus - Status changed successfully', [
                'contract_id' => $contract->getId(),
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'user_id' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('success', 'Statut du contrat modifié avec succès.');
        } catch (Exception $e) {
            $this->logger->error('AlternanceContractController::changeStatus - Error during status change', [
                'contract_id' => $contract->getId(),
                'requested_status' => $request->request->get('status'),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('error', 'Erreur lors du changement de statut : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_alternance_contract_show', ['id' => $contract->getId()]);
    }

    #[Route('/{id}/delete', name: 'admin_alternance_contract_delete', methods: ['POST'])]
    public function delete(Request $request, AlternanceContract $contract): Response
    {
        $this->logger->info('AlternanceContractController::delete - Starting contract deletion', [
            'contract_id' => $contract->getId(),
            'user_id' => $this->getUser()?->getUserIdentifier(),
        ]);

        try {
            $csrfToken = $request->request->get('_token');
            $expectedToken = 'delete' . $contract->getId();

            $this->logger->debug('AlternanceContractController::delete - Validating CSRF token', [
                'contract_id' => $contract->getId(),
                'has_token' => !empty($csrfToken),
            ]);

            if ($this->isCsrfTokenValid($expectedToken, $csrfToken)) {
                $this->logger->debug('AlternanceContractController::delete - CSRF token valid, proceeding with deletion', [
                    'contract_id' => $contract->getId(),
                ]);

                $this->contractService->deleteContract($contract);

                $this->logger->info('AlternanceContractController::delete - Contract deleted successfully', [
                    'contract_id' => $contract->getId(),
                    'user_id' => $this->getUser()?->getUserIdentifier(),
                ]);

                $this->addFlash('success', 'Contrat d\'alternance supprimé avec succès.');
            } else {
                $this->logger->warning('AlternanceContractController::delete - Invalid CSRF token', [
                    'contract_id' => $contract->getId(),
                    'user_id' => $this->getUser()?->getUserIdentifier(),
                ]);

                $this->addFlash('error', 'Token de sécurité invalide.');
            }
        } catch (Exception $e) {
            $this->logger->error('AlternanceContractController::delete - Error during contract deletion', [
                'contract_id' => $contract->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('error', 'Erreur lors de la suppression du contrat : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_alternance_contract_index');
    }

    #[Route('/{id}/export', name: 'admin_alternance_contract_export', methods: ['GET'])]
    public function export(AlternanceContract $contract): Response
    {
        $this->logger->info('AlternanceContractController::export - Starting contract export', [
            'contract_id' => $contract->getId(),
            'user_id' => $this->getUser()?->getUserIdentifier(),
        ]);

        try {
            $this->logger->debug('AlternanceContractController::export - Generating contract export', [
                'contract_id' => $contract->getId(),
                'contract_status' => $contract->getStatus(),
            ]);

            // This would generate a PDF contract using KnpSnappyBundle or similar
            // For now, we'll return a simple response
            $content = 'Contract Export for Contract ID: ' . $contract->getId();

            $this->logger->debug('AlternanceContractController::export - Export content generated', [
                'contract_id' => $contract->getId(),
                'content_length' => strlen($content),
            ]);

            $filename = 'contrat_alternance_' . $contract->getId() . '.pdf';

            $response = new Response($content);
            $response->headers->set('Content-Type', 'application/pdf');
            $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

            $this->logger->info('AlternanceContractController::export - Contract exported successfully', [
                'contract_id' => $contract->getId(),
                'filename' => $filename,
                'user_id' => $this->getUser()?->getUserIdentifier(),
            ]);

            return $response;
        } catch (Exception $e) {
            $this->logger->error('AlternanceContractController::export - Error during contract export', [
                'contract_id' => $contract->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('error', 'Erreur lors de l\'export du contrat : ' . $e->getMessage());

            return $this->redirectToRoute('admin_alternance_contract_show', ['id' => $contract->getId()]);
        }
    }

    #[Route('/bulk/status', name: 'admin_alternance_contract_bulk_status', methods: ['POST'])]
    public function bulkStatusChange(Request $request): Response
    {
        $this->logger->info('AlternanceContractController::bulkStatusChange - Starting bulk status change', [
            'user_id' => $this->getUser()?->getUserIdentifier(),
        ]);

        try {
            $contractIds = $request->request->all('contract_ids');
            $newStatus = $request->request->get('status');

            $this->logger->debug('AlternanceContractController::bulkStatusChange - Processing bulk status change request', [
                'contract_ids' => $contractIds,
                'new_status' => $newStatus,
                'contracts_count' => count($contractIds),
            ]);

            if (empty($contractIds) || !$newStatus) {
                $this->logger->warning('AlternanceContractController::bulkStatusChange - Missing required parameters', [
                    'has_contract_ids' => !empty($contractIds),
                    'has_status' => !empty($newStatus),
                ]);

                $this->addFlash('error', 'Veuillez sélectionner des contrats et un statut.');

                return $this->redirectToRoute('admin_alternance_contract_index');
            }

            $contracts = $this->contractRepository->findBy(['id' => $contractIds]);
            $updated = 0;

            $this->logger->debug('AlternanceContractController::bulkStatusChange - Found contracts to update', [
                'requested_count' => count($contractIds),
                'found_count' => count($contracts),
            ]);

            foreach ($contracts as $contract) {
                $oldStatus = $contract->getStatus();
                $contract->setStatus($newStatus);
                $updated++;

                $this->logger->debug('AlternanceContractController::bulkStatusChange - Updated contract status', [
                    'contract_id' => $contract->getId(),
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                ]);
            }

            $this->entityManager->flush();

            $this->logger->info('AlternanceContractController::bulkStatusChange - Bulk status change completed successfully', [
                'updated_count' => $updated,
                'new_status' => $newStatus,
                'user_id' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('success', sprintf('%d contrat(s) mis à jour avec succès.', $updated));
        } catch (Exception $e) {
            $this->logger->error('AlternanceContractController::bulkStatusChange - Error during bulk status change', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
                'requested_status' => $request->request->get('status'),
                'contract_ids' => $request->request->all('contract_ids'),
            ]);

            $this->addFlash('error', 'Erreur lors de la mise à jour : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_alternance_contract_index');
    }
}
