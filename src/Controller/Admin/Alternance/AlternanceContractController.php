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
    ) {}

    #[Route('', name: 'admin_alternance_contract_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        $search = $request->query->get('search', '');
        $status = $request->query->get('status', '');
        $perPage = 20;

        $filters = [
            'search' => $search,
            'status' => $status,
        ];

        $contracts = $this->contractRepository->findPaginatedContracts($filters, $page, $perPage);
        $totalPages = ceil($this->contractRepository->countFilteredContracts($filters) / $perPage);

        // Get statistics for dashboard
        $statistics = $this->contractRepository->getContractStatistics();

        return $this->render('admin/alternance/contract/index.html.twig', [
            'contracts' => $contracts,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'filters' => $filters,
            'statistics' => $statistics,
        ]);
    }

    #[Route('/new', name: 'admin_alternance_contract_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $contract = new AlternanceContract();
        $form = $this->createForm(AlternanceContractType::class, $contract);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Validate contract data
                $validationErrors = $this->validationService->validateContract($contract);
                if (!empty($validationErrors)) {
                    foreach ($validationErrors as $error) {
                        $this->addFlash('error', $error);
                    }

                    return $this->render('admin/alternance/contract/new.html.twig', [
                        'contract' => $contract,
                        'form' => $form,
                    ]);
                }

                // Persist the contract
                $this->entityManager->persist($contract);
                $this->entityManager->flush();

                $this->addFlash('success', 'Contrat d\'alternance créé avec succès.');

                return $this->redirectToRoute('admin_alternance_contract_show', [
                    'id' => $contract->getId(),
                ]);
            } catch (Exception $e) {
                $this->addFlash('error', 'Erreur lors de la création du contrat : ' . $e->getMessage());
            }
        }

        return $this->render('admin/alternance/contract/new.html.twig', [
            'contract' => $contract,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_alternance_contract_show', methods: ['GET'])]
    public function show(AlternanceContract $contract): Response
    {
        // Perform validation checks
        $validationErrors = $this->validationService->validateContract($contract);

        return $this->render('admin/alternance/contract/show.html.twig', [
            'contract' => $contract,
            'validation_errors' => $validationErrors,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_alternance_contract_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, AlternanceContract $contract): Response
    {
        $form = $this->createForm(AlternanceContractType::class, $contract);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Validate contract data
                $validationErrors = $this->validationService->validateContract($contract);
                if (!empty($validationErrors)) {
                    foreach ($validationErrors as $error) {
                        $this->addFlash('error', $error);
                    }

                    return $this->render('admin/alternance/contract/edit.html.twig', [
                        'contract' => $contract,
                        'form' => $form,
                    ]);
                }

                // Persist the updated contract
                $this->entityManager->flush();
                $this->addFlash('success', 'Contrat d\'alternance modifié avec succès.');

                return $this->redirectToRoute('admin_alternance_contract_show', [
                    'id' => $contract->getId(),
                ]);
            } catch (Exception $e) {
                $this->addFlash('error', 'Erreur lors de la modification du contrat : ' . $e->getMessage());
            }
        }

        return $this->render('admin/alternance/contract/edit.html.twig', [
            'contract' => $contract,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/status', name: 'admin_alternance_contract_status', methods: ['POST'])]
    public function changeStatus(Request $request, AlternanceContract $contract): Response
    {
        $newStatus = $request->request->get('status');
        $allowedStatuses = ['draft', 'pending_validation', 'validated', 'active', 'suspended', 'completed', 'terminated'];

        if (!in_array($newStatus, $allowedStatuses, true)) {
            $this->addFlash('error', 'Statut invalide.');

            return $this->redirectToRoute('admin_alternance_contract_show', ['id' => $contract->getId()]);
        }

        try {
            $contract->setStatus($newStatus);
            $this->entityManager->flush();
            $this->addFlash('success', 'Statut du contrat modifié avec succès.');
        } catch (Exception $e) {
            $this->addFlash('error', 'Erreur lors du changement de statut : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_alternance_contract_show', ['id' => $contract->getId()]);
    }

    #[Route('/{id}/delete', name: 'admin_alternance_contract_delete', methods: ['POST'])]
    public function delete(Request $request, AlternanceContract $contract): Response
    {
        if ($this->isCsrfTokenValid('delete' . $contract->getId(), $request->request->get('_token'))) {
            try {
                $this->contractService->deleteContract($contract);
                $this->addFlash('success', 'Contrat d\'alternance supprimé avec succès.');
            } catch (Exception $e) {
                $this->addFlash('error', 'Erreur lors de la suppression du contrat : ' . $e->getMessage());
            }
        }

        return $this->redirectToRoute('admin_alternance_contract_index');
    }

    #[Route('/{id}/export', name: 'admin_alternance_contract_export', methods: ['GET'])]
    public function export(AlternanceContract $contract): Response
    {
        try {
            // This would generate a PDF contract using KnpSnappyBundle or similar
            // For now, we'll return a simple response
            $content = 'Contract Export for Contract ID: ' . $contract->getId();

            $response = new Response($content);
            $response->headers->set('Content-Type', 'application/pdf');
            $response->headers->set('Content-Disposition', 'attachment; filename="contrat_alternance_' . $contract->getId() . '.pdf"');

            return $response;
        } catch (Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'export du contrat : ' . $e->getMessage());

            return $this->redirectToRoute('admin_alternance_contract_show', ['id' => $contract->getId()]);
        }
    }

    #[Route('/bulk/status', name: 'admin_alternance_contract_bulk_status', methods: ['POST'])]
    public function bulkStatusChange(Request $request): Response
    {
        $contractIds = $request->request->all('contract_ids');
        $newStatus = $request->request->get('status');

        if (empty($contractIds) || !$newStatus) {
            $this->addFlash('error', 'Veuillez sélectionner des contrats et un statut.');

            return $this->redirectToRoute('admin_alternance_contract_index');
        }

        try {
            $contracts = $this->contractRepository->findBy(['id' => $contractIds]);
            $updated = 0;

            foreach ($contracts as $contract) {
                $contract->setStatus($newStatus);
                $updated++;
            }

            $this->entityManager->flush();
            $this->addFlash('success', sprintf('%d contrat(s) mis à jour avec succès.', $updated));
        } catch (Exception $e) {
            $this->addFlash('error', 'Erreur lors de la mise à jour : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_alternance_contract_index');
    }
}
