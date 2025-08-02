<?php

declare(strict_types=1);

namespace App\Controller\Admin\Alternance;

use App\Entity\Alternance\AlternanceContract;
use App\Entity\User\Mentor;
use App\Repository\Alternance\AlternanceContractRepository;
use App\Repository\User\MentorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/alternance/companies')]
#[IsGranted('ROLE_ADMIN')]
class CompanyController extends AbstractController
{
    public function __construct(
        private MentorRepository $mentorRepository,
        private AlternanceContractRepository $contractRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    #[Route('', name: 'admin_alternance_company_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->logger->info('CompanyController::index - Starting company listing', [
            'user_email' => $this->getUser()?->getUserIdentifier(),
            'request_params' => $request->query->all(),
        ]);

        try {
            $page = $request->query->getInt('page', 1);
            $search = $request->query->get('search', '');
            $perPage = 20;

            $this->logger->debug('CompanyController::index - Processing request parameters', [
                'page' => $page,
                'search' => $search,
                'per_page' => $perPage,
            ]);

            // Get unique companies from mentors
            $companies = $this->getCompaniesData($search, $page, $perPage);
            $totalCompanies = $this->countCompanies($search);
            $totalPages = ceil($totalCompanies / $perPage);

            $this->logger->debug('CompanyController::index - Companies data retrieved', [
                'companies_count' => count($companies),
                'total_companies' => $totalCompanies,
                'total_pages' => $totalPages,
                'search_term' => $search,
            ]);

            // Get company statistics
            $statistics = $this->getCompanyStatistics();

            $this->logger->info('CompanyController::index - Successfully retrieved company data', [
                'companies_on_page' => count($companies),
                'total_companies' => $totalCompanies,
                'statistics_keys' => array_keys($statistics),
            ]);

            return $this->render('admin/alternance/company/index.html.twig', [
                'companies' => $companies,
                'current_page' => $page,
                'total_pages' => $totalPages,
                'search' => $search,
                'statistics' => $statistics,
            ]);
        } catch (Exception $e) {
            $this->logger->error('CompanyController::index - Error occurred while listing companies', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_params' => $request->query->all(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement de la liste des entreprises.');

            return $this->render('admin/alternance/company/index.html.twig', [
                'companies' => [],
                'current_page' => 1,
                'total_pages' => 1,
                'search' => '',
                'statistics' => [
                    'total_companies' => 0,
                    'total_mentors' => 0,
                    'total_contracts' => 0,
                    'active_contracts' => 0,
                ],
            ]);
        }
    }

    #[Route('/{siret}', name: 'admin_alternance_company_show', methods: ['GET'])]
    public function show(string $siret): Response
    {
        $this->logger->info('CompanyController::show - Starting company detail view', [
            'siret' => $siret,
            'user_email' => $this->getUser()?->getUserIdentifier(),
        ]);

        try {
            // Find company data by SIRET
            $mentors = $this->mentorRepository->findBy(['companySiret' => $siret]);
            $contracts = $this->contractRepository->findBy(['companySiret' => $siret]);

            $this->logger->debug('CompanyController::show - Retrieved company data', [
                'siret' => $siret,
                'mentors_count' => count($mentors),
                'contracts_count' => count($contracts),
            ]);

            if (empty($mentors)) {
                $this->logger->warning('CompanyController::show - Company not found', [
                    'siret' => $siret,
                ]);
                throw $this->createNotFoundException('Entreprise non trouvée');
            }

            // Get company info from first mentor
            $company = $mentors[0];

            // Get company address from contracts if available
            $companyAddress = null;
            if (!empty($contracts)) {
                $companyAddress = $contracts[0]->getCompanyAddress();
                $this->logger->debug('CompanyController::show - Company address found', [
                    'siret' => $siret,
                    'address_set' => !empty($companyAddress),
                ]);
            }

            // Calculate company metrics
            $metrics = $this->calculateCompanyMetrics($siret);

            $this->logger->info('CompanyController::show - Successfully retrieved company details', [
                'siret' => $siret,
                'company_name' => $company->getCompanyName(),
                'metrics_keys' => array_keys($metrics),
                'has_address' => !empty($companyAddress),
            ]);

            return $this->render('admin/alternance/company/show.html.twig', [
                'company' => $company,
                'companyAddress' => $companyAddress,
                'mentors' => $mentors,
                'contracts' => $contracts,
                'metrics' => $metrics,
            ]);
        } catch (Exception $e) {
            $this->logger->error('CompanyController::show - Error occurred while showing company details', [
                'siret' => $siret,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            if ($e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
                throw $e;
            }

            $this->addFlash('error', 'Une erreur est survenue lors du chargement des détails de l\'entreprise.');

            return $this->redirectToRoute('admin_alternance_company_index');
        }
    }

    #[Route('/{siret}/mentors', name: 'admin_alternance_company_mentors', methods: ['GET'])]
    public function mentors(string $siret): Response
    {
        $this->logger->info('CompanyController::mentors - Starting company mentors view', [
            'siret' => $siret,
            'user_email' => $this->getUser()?->getUserIdentifier(),
        ]);

        try {
            $mentors = $this->mentorRepository->findBy(['companySiret' => $siret]);

            $this->logger->debug('CompanyController::mentors - Retrieved mentors data', [
                'siret' => $siret,
                'mentors_count' => count($mentors),
            ]);

            if (empty($mentors)) {
                $this->logger->warning('CompanyController::mentors - Company not found', [
                    'siret' => $siret,
                ]);
                throw $this->createNotFoundException('Entreprise non trouvée');
            }

            $company = $mentors[0];

            // Get company address from contracts if available
            $contracts = $this->contractRepository->findBy(['companySiret' => $siret]);
            $companyAddress = null;
            if (!empty($contracts)) {
                $companyAddress = $contracts[0]->getCompanyAddress();
                $this->logger->debug('CompanyController::mentors - Company address found', [
                    'siret' => $siret,
                    'address_set' => !empty($companyAddress),
                ]);
            }

            $this->logger->info('CompanyController::mentors - Successfully retrieved company mentors', [
                'siret' => $siret,
                'company_name' => $company->getCompanyName(),
                'mentors_count' => count($mentors),
                'has_address' => !empty($companyAddress),
            ]);

            return $this->render('admin/alternance/company/mentors.html.twig', [
                'company' => $company,
                'companyAddress' => $companyAddress,
                'mentors' => $mentors,
            ]);
        } catch (Exception $e) {
            $this->logger->error('CompanyController::mentors - Error occurred while showing company mentors', [
                'siret' => $siret,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            if ($e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
                throw $e;
            }

            $this->addFlash('error', 'Une erreur est survenue lors du chargement des mentors de l\'entreprise.');

            return $this->redirectToRoute('admin_alternance_company_index');
        }
    }

    #[Route('/{siret}/contracts', name: 'admin_alternance_company_contracts', methods: ['GET'])]
    public function contracts(string $siret, Request $request): Response
    {
        $this->logger->info('CompanyController::contracts - Starting company contracts view', [
            'siret' => $siret,
            'user_email' => $this->getUser()?->getUserIdentifier(),
            'request_params' => $request->query->all(),
        ]);

        try {
            $page = $request->query->getInt('page', 1);
            $status = $request->query->get('status', '');
            $perPage = 10;

            $this->logger->debug('CompanyController::contracts - Processing request parameters', [
                'siret' => $siret,
                'page' => $page,
                'status_filter' => $status,
                'per_page' => $perPage,
            ]);

            // Get company name from first mentor
            $mentor = $this->mentorRepository->findOneBy(['companySiret' => $siret]);
            if (!$mentor) {
                $this->logger->warning('CompanyController::contracts - Company not found', [
                    'siret' => $siret,
                ]);
                throw $this->createNotFoundException('Entreprise non trouvée');
            }

            // Get contracts with filters
            $filters = ['companySiret' => $siret];
            if ($status) {
                $filters['status'] = $status;
            }

            $this->logger->debug('CompanyController::contracts - Applying filters', [
                'siret' => $siret,
                'filters' => $filters,
            ]);

            $contracts = $this->contractRepository->findPaginatedContracts($filters, $page, $perPage);
            $totalContracts = $this->contractRepository->countFilteredContracts($filters);
            $totalPages = ceil($totalContracts / $perPage);

            $this->logger->debug('CompanyController::contracts - Retrieved contracts data', [
                'siret' => $siret,
                'contracts_count' => count($contracts),
                'total_contracts' => $totalContracts,
                'total_pages' => $totalPages,
            ]);

            // Get company address from contracts if available
            $companyAddress = null;
            if (!empty($contracts)) {
                $companyAddress = $contracts[0]->getCompanyAddress();
                $this->logger->debug('CompanyController::contracts - Company address found', [
                    'siret' => $siret,
                    'address_set' => !empty($companyAddress),
                ]);
            }

            $this->logger->info('CompanyController::contracts - Successfully retrieved company contracts', [
                'siret' => $siret,
                'company_name' => $mentor->getCompanyName(),
                'contracts_on_page' => count($contracts),
                'total_contracts' => $totalContracts,
                'status_filter' => $status,
            ]);

            return $this->render('admin/alternance/company/contracts.html.twig', [
                'company' => $mentor,
                'companyAddress' => $companyAddress,
                'contracts' => $contracts,
                'current_page' => $page,
                'total_pages' => $totalPages,
                'status_filter' => $status,
            ]);
        } catch (Exception $e) {
            $this->logger->error('CompanyController::contracts - Error occurred while showing company contracts', [
                'siret' => $siret,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_params' => $request->query->all(),
            ]);

            if ($e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
                throw $e;
            }

            $this->addFlash('error', 'Une erreur est survenue lors du chargement des contrats de l\'entreprise.');

            return $this->redirectToRoute('admin_alternance_company_index');
        }
    }

    #[Route('/{siret}/statistics', name: 'admin_alternance_company_statistics', methods: ['GET'])]
    public function statistics(string $siret): Response
    {
        $this->logger->info('CompanyController::statistics - Starting company statistics view', [
            'siret' => $siret,
            'user_email' => $this->getUser()?->getUserIdentifier(),
        ]);

        try {
            $mentors = $this->mentorRepository->findBy(['companySiret' => $siret]);

            $this->logger->debug('CompanyController::statistics - Retrieved mentors data', [
                'siret' => $siret,
                'mentors_count' => count($mentors),
            ]);

            if (empty($mentors)) {
                $this->logger->warning('CompanyController::statistics - Company not found', [
                    'siret' => $siret,
                ]);
                throw $this->createNotFoundException('Entreprise non trouvée');
            }

            $company = $mentors[0];
            $detailedMetrics = $this->getDetailedCompanyMetrics($siret);

            $this->logger->debug('CompanyController::statistics - Generated detailed metrics', [
                'siret' => $siret,
                'metrics_keys' => array_keys($detailedMetrics),
                'contracts_by_status_count' => count($detailedMetrics['contracts_by_status'] ?? []),
                'contracts_by_type_count' => count($detailedMetrics['contracts_by_type'] ?? []),
            ]);

            // Get company address from contracts if available
            $contracts = $this->contractRepository->findBy(['companySiret' => $siret]);
            $companyAddress = null;
            if (!empty($contracts)) {
                $companyAddress = $contracts[0]->getCompanyAddress();
                $this->logger->debug('CompanyController::statistics - Company address found', [
                    'siret' => $siret,
                    'address_set' => !empty($companyAddress),
                ]);
            }

            $this->logger->info('CompanyController::statistics - Successfully generated company statistics', [
                'siret' => $siret,
                'company_name' => $company->getCompanyName(),
                'has_address' => !empty($companyAddress),
                'metrics_generated' => !empty($detailedMetrics),
            ]);

            return $this->render('admin/alternance/company/statistics.html.twig', [
                'company' => $company,
                'companyAddress' => $companyAddress,
                'metrics' => $detailedMetrics,
            ]);
        } catch (Exception $e) {
            $this->logger->error('CompanyController::statistics - Error occurred while generating company statistics', [
                'siret' => $siret,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            if ($e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
                throw $e;
            }

            $this->addFlash('error', 'Une erreur est survenue lors du calcul des statistiques de l\'entreprise.');

            return $this->redirectToRoute('admin_alternance_company_index');
        }
    }

    #[Route('/export', name: 'admin_alternance_company_export', methods: ['GET'])]
    public function export(Request $request): Response
    {
        $this->logger->info('CompanyController::export - Starting company export', [
            'user_email' => $this->getUser()?->getUserIdentifier(),
            'request_params' => $request->query->all(),
        ]);

        try {
            $format = $request->query->get('format', 'csv');
            $search = $request->query->get('search', '');

            $this->logger->debug('CompanyController::export - Processing export parameters', [
                'format' => $format,
                'search' => $search,
            ]);

            if (!in_array($format, ['csv', 'xlsx'], true)) {
                $this->logger->warning('CompanyController::export - Invalid export format', [
                    'format' => $format,
                    'allowed_formats' => ['csv', 'xlsx'],
                ]);
                throw new InvalidArgumentException("Format d'export non supporté: {$format}");
            }

            $companies = $this->getCompaniesData($search);
            
            $this->logger->debug('CompanyController::export - Retrieved companies for export', [
                'companies_count' => count($companies),
                'search_term' => $search,
                'format' => $format,
            ]);

            $data = $this->exportCompanies($companies, $format);

            $this->logger->info('CompanyController::export - Successfully generated export data', [
                'format' => $format,
                'companies_exported' => count($companies),
                'data_size' => strlen($data),
                'search_term' => $search,
            ]);

            $response = new Response($data);
            $response->headers->set('Content-Type', $format === 'csv' ? 'text/csv' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $response->headers->set('Content-Disposition', 'attachment; filename="entreprises_export.' . $format . '"');

            return $response;
        } catch (Exception $e) {
            $this->logger->error('CompanyController::export - Error occurred during export', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_params' => $request->query->all(),
            ]);

            $this->addFlash('error', 'Erreur lors de l\'export : ' . $e->getMessage());

            return $this->redirectToRoute('admin_alternance_company_index');
        }
    }

    private function getCompaniesData(string $search = '', ?int $page = null, ?int $perPage = null): array
    {
        $this->logger->debug('CompanyController::getCompaniesData - Starting data retrieval', [
            'search' => $search,
            'page' => $page,
            'per_page' => $perPage,
        ]);

        try {
            $qb = $this->entityManager->createQueryBuilder()
                ->select('m.companyName, m.companySiret, 
                         COUNT(DISTINCT m.id) as mentorCount,
                         COUNT(DISTINCT ac.id) as contractCount,
                         ac.companyAddress')
                ->from(Mentor::class, 'm')
                ->leftJoin(AlternanceContract::class, 'ac', 'WITH', 'ac.companySiret = m.companySiret')
                ->groupBy('m.companyName, m.companySiret, ac.companyAddress')
            ;

            if ($search) {
                $qb->where('m.companyName LIKE :search OR m.companySiret LIKE :search')
                    ->setParameter('search', '%' . $search . '%')
                ;
                $this->logger->debug('CompanyController::getCompaniesData - Applied search filter', [
                    'search' => $search,
                ]);
            }

            $qb->orderBy('m.companyName', 'ASC');

            if ($page && $perPage) {
                $offset = ($page - 1) * $perPage;
                $qb->setFirstResult($offset)->setMaxResults($perPage);
                $this->logger->debug('CompanyController::getCompaniesData - Applied pagination', [
                    'page' => $page,
                    'per_page' => $perPage,
                    'offset' => $offset,
                ]);
            }

            $result = $qb->getQuery()->getResult();

            $this->logger->debug('CompanyController::getCompaniesData - Successfully retrieved companies data', [
                'result_count' => count($result),
                'search' => $search,
                'page' => $page,
            ]);

            return $result;
        } catch (Exception $e) {
            $this->logger->error('CompanyController::getCompaniesData - Error retrieving companies data', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'search' => $search,
                'page' => $page,
                'per_page' => $perPage,
                'trace' => $e->getTraceAsString(),
            ]);

            return [];
        }
    }

    private function countCompanies(string $search = ''): int
    {
        $this->logger->debug('CompanyController::countCompanies - Starting count', [
            'search' => $search,
        ]);

        try {
            $qb = $this->entityManager->createQueryBuilder()
                ->select('COUNT(DISTINCT m.companySiret)')
                ->from(Mentor::class, 'm')
            ;

            if ($search) {
                $qb->where('m.companyName LIKE :search OR m.companySiret LIKE :search')
                    ->setParameter('search', '%' . $search . '%')
                ;
                $this->logger->debug('CompanyController::countCompanies - Applied search filter', [
                    'search' => $search,
                ]);
            }

            $count = $qb->getQuery()->getSingleScalarResult();

            $this->logger->debug('CompanyController::countCompanies - Successfully counted companies', [
                'count' => $count,
                'search' => $search,
            ]);

            return $count;
        } catch (Exception $e) {
            $this->logger->error('CompanyController::countCompanies - Error counting companies', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'search' => $search,
                'trace' => $e->getTraceAsString(),
            ]);

            return 0;
        }
    }

    private function getCompanyStatistics(): array
    {
        $this->logger->debug('CompanyController::getCompanyStatistics - Starting statistics calculation');

        try {
            $totalCompanies = $this->countCompanies();
            $totalMentors = $this->mentorRepository->count([]);
            $totalContracts = $this->contractRepository->count([]);
            $activeContracts = $this->contractRepository->countByStatus('active');

            $this->logger->debug('CompanyController::getCompanyStatistics - Retrieved basic statistics', [
                'total_companies' => $totalCompanies,
                'total_mentors' => $totalMentors,
                'total_contracts' => $totalContracts,
                'active_contracts' => $activeContracts,
            ]);

            // Get companies with most mentors
            $topCompaniesByMentors = $this->entityManager->createQueryBuilder()
                ->select('m.companyName, COUNT(m.id) as mentorCount')
                ->from(Mentor::class, 'm')
                ->groupBy('m.companyName')
                ->orderBy('mentorCount', 'DESC')
                ->setMaxResults(5)
                ->getQuery()
                ->getResult()
            ;

            // Get companies with most contracts
            $topCompaniesByContracts = $this->entityManager->createQueryBuilder()
                ->select('ac.companyName, COUNT(ac.id) as contractCount')
                ->from(AlternanceContract::class, 'ac')
                ->groupBy('ac.companyName')
                ->orderBy('contractCount', 'DESC')
                ->setMaxResults(5)
                ->getQuery()
                ->getResult()
            ;

            $this->logger->debug('CompanyController::getCompanyStatistics - Retrieved top companies', [
                'top_mentors_count' => count($topCompaniesByMentors),
                'top_contracts_count' => count($topCompaniesByContracts),
            ]);

            $statistics = [
                'total_companies' => $totalCompanies,
                'total_mentors' => $totalMentors,
                'total_contracts' => $totalContracts,
                'active_contracts' => $activeContracts,
                'average_mentors_per_company' => $totalCompanies > 0 ? round($totalMentors / $totalCompanies, 1) : 0,
                'average_contracts_per_company' => $totalCompanies > 0 ? round($totalContracts / $totalCompanies, 1) : 0,
                'top_companies_by_mentors' => $topCompaniesByMentors,
                'top_companies_by_contracts' => $topCompaniesByContracts,
            ];

            $this->logger->info('CompanyController::getCompanyStatistics - Successfully calculated statistics', [
                'statistics_keys' => array_keys($statistics),
                'total_companies' => $totalCompanies,
                'total_mentors' => $totalMentors,
            ]);

            return $statistics;
        } catch (Exception $e) {
            $this->logger->error('CompanyController::getCompanyStatistics - Error calculating statistics', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'total_companies' => 0,
                'total_mentors' => 0,
                'total_contracts' => 0,
                'active_contracts' => 0,
                'average_mentors_per_company' => 0,
                'average_contracts_per_company' => 0,
                'top_companies_by_mentors' => [],
                'top_companies_by_contracts' => [],
            ];
        }
    }

    private function calculateCompanyMetrics(string $siret): array
    {
        $this->logger->debug('CompanyController::calculateCompanyMetrics - Starting metrics calculation', [
            'siret' => $siret,
        ]);

        try {
            $mentors = $this->mentorRepository->findBy(['companySiret' => $siret]);
            $contracts = $this->contractRepository->findBy(['companySiret' => $siret]);

            $this->logger->debug('CompanyController::calculateCompanyMetrics - Retrieved data', [
                'siret' => $siret,
                'mentors_count' => count($mentors),
                'contracts_count' => count($contracts),
            ]);

            $activeContracts = array_filter($contracts, static fn ($c) => $c->getStatus() === 'active');
            $completedContracts = array_filter($contracts, static fn ($c) => $c->getStatus() === 'completed');
            $activeMentors = array_filter($mentors, static fn ($m) => $m->isActive());

            $metrics = [
                'total_mentors' => count($mentors),
                'active_mentors' => count($activeMentors),
                'total_contracts' => count($contracts),
                'active_contracts' => count($activeContracts),
                'completed_contracts' => count($completedContracts),
                'success_rate' => count($contracts) > 0 ? round((count($completedContracts) / count($contracts)) * 100, 1) : 0,
                'average_contract_duration' => $this->calculateAverageContractDuration($contracts),
                'newest_mentor' => $this->getNewestMentor($mentors),
                'oldest_contract' => $this->getOldestContract($contracts),
            ];

            $this->logger->info('CompanyController::calculateCompanyMetrics - Successfully calculated metrics', [
                'siret' => $siret,
                'metrics_keys' => array_keys($metrics),
                'success_rate' => $metrics['success_rate'],
                'total_mentors' => $metrics['total_mentors'],
                'total_contracts' => $metrics['total_contracts'],
            ]);

            return $metrics;
        } catch (Exception $e) {
            $this->logger->error('CompanyController::calculateCompanyMetrics - Error calculating metrics', [
                'siret' => $siret,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'total_mentors' => 0,
                'active_mentors' => 0,
                'total_contracts' => 0,
                'active_contracts' => 0,
                'completed_contracts' => 0,
                'success_rate' => 0,
                'average_contract_duration' => 0,
                'newest_mentor' => null,
                'oldest_contract' => null,
            ];
        }
    }

    private function getDetailedCompanyMetrics(string $siret): array
    {
        $this->logger->debug('CompanyController::getDetailedCompanyMetrics - Starting detailed metrics calculation', [
            'siret' => $siret,
        ]);

        try {
            $basicMetrics = $this->calculateCompanyMetrics($siret);

            // Add more detailed metrics
            $contracts = $this->contractRepository->findBy(['companySiret' => $siret]);

            $contractsByStatus = [];
            $contractsByType = [];
            $monthlyTrends = [];

            foreach ($contracts as $contract) {
                // Group by status
                $status = $contract->getStatus();
                $contractsByStatus[$status] = ($contractsByStatus[$status] ?? 0) + 1;

                // Group by type
                $type = $contract->getContractType();
                $contractsByType[$type] = ($contractsByType[$type] ?? 0) + 1;

                // Monthly trends (last 12 months)
                $month = $contract->getCreatedAt()->format('Y-m');
                $monthlyTrends[$month] = ($monthlyTrends[$month] ?? 0) + 1;
            }

            $detailedMetrics = array_merge($basicMetrics, [
                'contracts_by_status' => $contractsByStatus,
                'contracts_by_type' => $contractsByType,
                'monthly_trends' => $monthlyTrends,
                'mentor_expertise_distribution' => $this->getMentorExpertiseDistribution($siret),
            ]);

            $this->logger->info('CompanyController::getDetailedCompanyMetrics - Successfully calculated detailed metrics', [
                'siret' => $siret,
                'contracts_by_status_count' => count($contractsByStatus),
                'contracts_by_type_count' => count($contractsByType),
                'monthly_trends_count' => count($monthlyTrends),
                'expertise_domains_count' => count($detailedMetrics['mentor_expertise_distribution']),
            ]);

            return $detailedMetrics;
        } catch (Exception $e) {
            $this->logger->error('CompanyController::getDetailedCompanyMetrics - Error calculating detailed metrics', [
                'siret' => $siret,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'total_mentors' => 0,
                'active_mentors' => 0,
                'total_contracts' => 0,
                'active_contracts' => 0,
                'completed_contracts' => 0,
                'success_rate' => 0,
                'average_contract_duration' => 0,
                'newest_mentor' => null,
                'oldest_contract' => null,
                'contracts_by_status' => [],
                'contracts_by_type' => [],
                'monthly_trends' => [],
                'mentor_expertise_distribution' => [],
            ];
        }
    }

    private function getMentorExpertiseDistribution(string $siret): array
    {
        $this->logger->debug('CompanyController::getMentorExpertiseDistribution - Starting expertise distribution calculation', [
            'siret' => $siret,
        ]);

        try {
            $mentors = $this->mentorRepository->findBy(['companySiret' => $siret]);
            $distribution = [];

            foreach ($mentors as $mentor) {
                foreach ($mentor->getExpertiseDomains() as $domain) {
                    $distribution[$domain] = ($distribution[$domain] ?? 0) + 1;
                }
            }

            $this->logger->debug('CompanyController::getMentorExpertiseDistribution - Successfully calculated distribution', [
                'siret' => $siret,
                'mentors_count' => count($mentors),
                'domains_count' => count($distribution),
                'domains' => array_keys($distribution),
            ]);

            return $distribution;
        } catch (Exception $e) {
            $this->logger->error('CompanyController::getMentorExpertiseDistribution - Error calculating expertise distribution', [
                'siret' => $siret,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [];
        }
    }

    private function calculateAverageContractDuration(array $contracts): float
    {
        $this->logger->debug('CompanyController::calculateAverageContractDuration - Starting calculation', [
            'contracts_count' => count($contracts),
        ]);

        try {
            if (empty($contracts)) {
                $this->logger->debug('CompanyController::calculateAverageContractDuration - No contracts provided');
                return 0;
            }

            $totalDuration = 0;
            $count = 0;

            foreach ($contracts as $contract) {
                if ($contract->getDuration()) {
                    $totalDuration += $contract->getDuration();
                    $count++;
                }
            }

            $average = $count > 0 ? round($totalDuration / $count, 1) : 0;

            $this->logger->debug('CompanyController::calculateAverageContractDuration - Successfully calculated average', [
                'total_contracts' => count($contracts),
                'contracts_with_duration' => $count,
                'total_duration' => $totalDuration,
                'average_duration' => $average,
            ]);

            return $average;
        } catch (Exception $e) {
            $this->logger->error('CompanyController::calculateAverageContractDuration - Error calculating average duration', [
                'contracts_count' => count($contracts),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 0;
        }
    }

    private function getNewestMentor(array $mentors)
    {
        $this->logger->debug('CompanyController::getNewestMentor - Finding newest mentor', [
            'mentors_count' => count($mentors),
        ]);

        try {
            if (empty($mentors)) {
                $this->logger->debug('CompanyController::getNewestMentor - No mentors provided');
                return null;
            }

            usort($mentors, static fn ($a, $b) => $b->getCreatedAt() <=> $a->getCreatedAt());

            $newest = $mentors[0];

            $this->logger->debug('CompanyController::getNewestMentor - Successfully found newest mentor', [
                'mentor_id' => $newest->getId(),
                'created_at' => $newest->getCreatedAt()->format('Y-m-d H:i:s'),
                'total_mentors' => count($mentors),
            ]);

            return $newest;
        } catch (Exception $e) {
            $this->logger->error('CompanyController::getNewestMentor - Error finding newest mentor', [
                'mentors_count' => count($mentors),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    private function getOldestContract(array $contracts)
    {
        $this->logger->debug('CompanyController::getOldestContract - Finding oldest contract', [
            'contracts_count' => count($contracts),
        ]);

        try {
            if (empty($contracts)) {
                $this->logger->debug('CompanyController::getOldestContract - No contracts provided');
                return null;
            }

            usort($contracts, static fn ($a, $b) => $a->getCreatedAt() <=> $b->getCreatedAt());

            $oldest = $contracts[0];

            $this->logger->debug('CompanyController::getOldestContract - Successfully found oldest contract', [
                'contract_id' => $oldest->getId(),
                'created_at' => $oldest->getCreatedAt()->format('Y-m-d H:i:s'),
                'total_contracts' => count($contracts),
            ]);

            return $oldest;
        } catch (Exception $e) {
            $this->logger->error('CompanyController::getOldestContract - Error finding oldest contract', [
                'contracts_count' => count($contracts),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    private function exportCompanies(array $companies, string $format): string
    {
        $this->logger->debug('CompanyController::exportCompanies - Starting export', [
            'companies_count' => count($companies),
            'format' => $format,
        ]);

        try {
            if ($format === 'csv') {
                $output = fopen('php://temp', 'r+');

                // Headers
                fputcsv($output, [
                    'Nom de l\'entreprise',
                    'SIRET',
                    'Adresse',
                    'Nombre de mentors',
                    'Nombre de contrats',
                ]);

                // Data
                foreach ($companies as $company) {
                    fputcsv($output, [
                        $company['companyName'],
                        $company['companySiret'],
                        $company['companyAddress'] ?? '',
                        $company['mentorCount'],
                        $company['contractCount'],
                    ]);
                }

                rewind($output);
                $content = stream_get_contents($output);
                fclose($output);

                $this->logger->info('CompanyController::exportCompanies - Successfully exported to CSV', [
                    'companies_count' => count($companies),
                    'content_size' => strlen($content),
                ]);

                return $content;
            }

            $this->logger->error('CompanyController::exportCompanies - Unsupported format', [
                'format' => $format,
                'supported_formats' => ['csv'],
            ]);

            throw new InvalidArgumentException("Format d'export non supporté: {$format}");
        } catch (Exception $e) {
            $this->logger->error('CompanyController::exportCompanies - Error during export', [
                'companies_count' => count($companies),
                'format' => $format,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
