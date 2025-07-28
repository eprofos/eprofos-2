<?php

namespace App\Controller\Admin\Alternance;

use App\Entity\Alternance\AlternanceContract;
use App\Entity\User\Mentor;
use App\Repository\User\MentorRepository;
use App\Repository\Alternance\AlternanceContractRepository;
use Doctrine\ORM\EntityManagerInterface;
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
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('', name: 'admin_alternance_company_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        $search = $request->query->get('search', '');
        $perPage = 20;

        // Get unique companies from mentors
        $companies = $this->getCompaniesData($search, $page, $perPage);
        $totalCompanies = $this->countCompanies($search);
        $totalPages = ceil($totalCompanies / $perPage);

        // Get company statistics
        $statistics = $this->getCompanyStatistics();

        return $this->render('admin/alternance/company/index.html.twig', [
            'companies' => $companies,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'search' => $search,
            'statistics' => $statistics,
        ]);
    }

    #[Route('/{siret}', name: 'admin_alternance_company_show', methods: ['GET'])]
    public function show(string $siret): Response
    {
        // Find company data by SIRET
        $mentors = $this->mentorRepository->findBy(['companySiret' => $siret]);
        $contracts = $this->contractRepository->findBy(['companySiret' => $siret]);
        
        if (empty($mentors)) {
            throw $this->createNotFoundException('Entreprise non trouvée');
        }

        // Get company info from first mentor
        $company = $mentors[0];
        
        // Get company address from contracts if available
        $companyAddress = null;
        if (!empty($contracts)) {
            $companyAddress = $contracts[0]->getCompanyAddress();
        }
        
        // Calculate company metrics
        $metrics = $this->calculateCompanyMetrics($siret);
        
        return $this->render('admin/alternance/company/show.html.twig', [
            'company' => $company,
            'companyAddress' => $companyAddress,
            'mentors' => $mentors,
            'contracts' => $contracts,
            'metrics' => $metrics,
        ]);
    }

    #[Route('/{siret}/mentors', name: 'admin_alternance_company_mentors', methods: ['GET'])]
    public function mentors(string $siret): Response
    {
        $mentors = $this->mentorRepository->findBy(['companySiret' => $siret]);
        
        if (empty($mentors)) {
            throw $this->createNotFoundException('Entreprise non trouvée');
        }

        $company = $mentors[0];
        
        // Get company address from contracts if available
        $contracts = $this->contractRepository->findBy(['companySiret' => $siret]);
        $companyAddress = null;
        if (!empty($contracts)) {
            $companyAddress = $contracts[0]->getCompanyAddress();
        }

        return $this->render('admin/alternance/company/mentors.html.twig', [
            'company' => $company,
            'companyAddress' => $companyAddress,
            'mentors' => $mentors,
        ]);
    }

    #[Route('/{siret}/contracts', name: 'admin_alternance_company_contracts', methods: ['GET'])]
    public function contracts(string $siret, Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        $status = $request->query->get('status', '');
        $perPage = 10;

        // Get company name from first mentor
        $mentor = $this->mentorRepository->findOneBy(['companySiret' => $siret]);
        if (!$mentor) {
            throw $this->createNotFoundException('Entreprise non trouvée');
        }

        // Get contracts with filters
        $filters = ['companySiret' => $siret];
        if ($status) {
            $filters['status'] = $status;
        }

        $contracts = $this->contractRepository->findPaginatedContracts($filters, $page, $perPage);
        $totalContracts = $this->contractRepository->countFilteredContracts($filters);
        $totalPages = ceil($totalContracts / $perPage);
        
        // Get company address from contracts if available
        $companyAddress = null;
        if (!empty($contracts)) {
            $companyAddress = $contracts[0]->getCompanyAddress();
        }

        return $this->render('admin/alternance/company/contracts.html.twig', [
            'company' => $mentor,
            'companyAddress' => $companyAddress,
            'contracts' => $contracts,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'status_filter' => $status,
        ]);
    }

    #[Route('/{siret}/statistics', name: 'admin_alternance_company_statistics', methods: ['GET'])]
    public function statistics(string $siret): Response
    {
        $mentors = $this->mentorRepository->findBy(['companySiret' => $siret]);
        
        if (empty($mentors)) {
            throw $this->createNotFoundException('Entreprise non trouvée');
        }

        $company = $mentors[0];
        $detailedMetrics = $this->getDetailedCompanyMetrics($siret);
        
        // Get company address from contracts if available
        $contracts = $this->contractRepository->findBy(['companySiret' => $siret]);
        $companyAddress = null;
        if (!empty($contracts)) {
            $companyAddress = $contracts[0]->getCompanyAddress();
        }

        return $this->render('admin/alternance/company/statistics.html.twig', [
            'company' => $company,
            'companyAddress' => $companyAddress,
            'metrics' => $detailedMetrics,
        ]);
    }

    #[Route('/export', name: 'admin_alternance_company_export', methods: ['GET'])]
    public function export(Request $request): Response
    {
        $format = $request->query->get('format', 'csv');
        $search = $request->query->get('search', '');

        try {
            $companies = $this->getCompaniesData($search);
            $data = $this->exportCompanies($companies, $format);

            $response = new Response($data);
            $response->headers->set('Content-Type', $format === 'csv' ? 'text/csv' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $response->headers->set('Content-Disposition', 'attachment; filename="entreprises_export.'.$format.'"');
            
            return $response;
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'export : ' . $e->getMessage());
            return $this->redirectToRoute('admin_alternance_company_index');
        }
    }

    private function getCompaniesData(string $search = '', ?int $page = null, ?int $perPage = null): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('m.companyName, m.companySiret, 
                     COUNT(DISTINCT m.id) as mentorCount,
                     COUNT(DISTINCT ac.id) as contractCount,
                     ac.companyAddress')
            ->from(Mentor::class, 'm')
            ->leftJoin(AlternanceContract::class, 'ac', 'WITH', 'ac.companySiret = m.companySiret')
            ->groupBy('m.companyName, m.companySiret, ac.companyAddress');

        if ($search) {
            $qb->where('m.companyName LIKE :search OR m.companySiret LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        $qb->orderBy('m.companyName', 'ASC');

        if ($page && $perPage) {
            $offset = ($page - 1) * $perPage;
            $qb->setFirstResult($offset)->setMaxResults($perPage);
        }

        return $qb->getQuery()->getResult();
    }

    private function countCompanies(string $search = ''): int
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('COUNT(DISTINCT m.companySiret)')
            ->from(Mentor::class, 'm');

        if ($search) {
            $qb->where('m.companyName LIKE :search OR m.companySiret LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        return $qb->getQuery()->getSingleScalarResult();
    }

    private function getCompanyStatistics(): array
    {
        $totalCompanies = $this->countCompanies();
        $totalMentors = $this->mentorRepository->count([]);
        $totalContracts = $this->contractRepository->count([]);
        $activeContracts = $this->contractRepository->countByStatus('active');

        // Get companies with most mentors
        $topCompaniesByMentors = $this->entityManager->createQueryBuilder()
            ->select('m.companyName, COUNT(m.id) as mentorCount')
            ->from(Mentor::class, 'm')
            ->groupBy('m.companyName')
            ->orderBy('mentorCount', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        // Get companies with most contracts
        $topCompaniesByContracts = $this->entityManager->createQueryBuilder()
            ->select('ac.companyName, COUNT(ac.id) as contractCount')
            ->from(AlternanceContract::class, 'ac')
            ->groupBy('ac.companyName')
            ->orderBy('contractCount', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        return [
            'total_companies' => $totalCompanies,
            'total_mentors' => $totalMentors,
            'total_contracts' => $totalContracts,
            'active_contracts' => $activeContracts,
            'average_mentors_per_company' => $totalCompanies > 0 ? round($totalMentors / $totalCompanies, 1) : 0,
            'average_contracts_per_company' => $totalCompanies > 0 ? round($totalContracts / $totalCompanies, 1) : 0,
            'top_companies_by_mentors' => $topCompaniesByMentors,
            'top_companies_by_contracts' => $topCompaniesByContracts,
        ];
    }

    private function calculateCompanyMetrics(string $siret): array
    {
        $mentors = $this->mentorRepository->findBy(['companySiret' => $siret]);
        $contracts = $this->contractRepository->findBy(['companySiret' => $siret]);

        $activeContracts = array_filter($contracts, fn($c) => $c->getStatus() === 'active');
        $completedContracts = array_filter($contracts, fn($c) => $c->getStatus() === 'completed');
        $activeMentors = array_filter($mentors, fn($m) => $m->isActive());

        return [
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
    }

    private function getDetailedCompanyMetrics(string $siret): array
    {
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

        return array_merge($basicMetrics, [
            'contracts_by_status' => $contractsByStatus,
            'contracts_by_type' => $contractsByType,
            'monthly_trends' => $monthlyTrends,
            'mentor_expertise_distribution' => $this->getMentorExpertiseDistribution($siret),
        ]);
    }

    private function getMentorExpertiseDistribution(string $siret): array
    {
        $mentors = $this->mentorRepository->findBy(['companySiret' => $siret]);
        $distribution = [];
        
        foreach ($mentors as $mentor) {
            foreach ($mentor->getExpertiseDomains() as $domain) {
                $distribution[$domain] = ($distribution[$domain] ?? 0) + 1;
            }
        }
        
        return $distribution;
    }

    private function calculateAverageContractDuration(array $contracts): float
    {
        if (empty($contracts)) {
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

        return $count > 0 ? round($totalDuration / $count, 1) : 0;
    }

    private function getNewestMentor(array $mentors)
    {
        if (empty($mentors)) {
            return null;
        }

        usort($mentors, fn($a, $b) => $b->getCreatedAt() <=> $a->getCreatedAt());
        return $mentors[0];
    }

    private function getOldestContract(array $contracts)
    {
        if (empty($contracts)) {
            return null;
        }

        usort($contracts, fn($a, $b) => $a->getCreatedAt() <=> $b->getCreatedAt());
        return $contracts[0];
    }

    private function exportCompanies(array $companies, string $format): string
    {
        if ($format === 'csv') {
            $output = fopen('php://temp', 'r+');
            
            // Headers
            fputcsv($output, [
                'Nom de l\'entreprise',
                'SIRET',
                'Adresse',
                'Nombre de mentors',
                'Nombre de contrats'
            ]);
            
            // Data
            foreach ($companies as $company) {
                fputcsv($output, [
                    $company['companyName'],
                    $company['companySiret'],
                    $company['companyAddress'] ?? '',
                    $company['mentorCount'],
                    $company['contractCount']
                ]);
            }
            
            rewind($output);
            $content = stream_get_contents($output);
            fclose($output);
            
            return $content;
        }

        throw new \InvalidArgumentException("Format d'export non supporté: {$format}");
    }
}
