<?php

declare(strict_types=1);

namespace App\Service\Alternance;

use App\Entity\Alternance\AlternanceContract;
use App\Repository\Alternance\AlternanceContractRepository;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Service for managing alternance contracts.
 *
 * Provides business logic for CRUD operations, validation,
 * and workflow management for alternance contracts.
 */
class AlternanceContractService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AlternanceContractRepository $contractRepository,
        private ValidatorInterface $validator,
        private LoggerInterface $logger,
    ) {}

    /**
     * Create a new alternance contract.
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function createContract(array $data): AlternanceContract
    {
        $this->logger->info('Starting creation of new alternance contract', [
            'data_keys' => array_keys($data),
            'student_id' => $data['student']?->getId() ?? null,
            'session_id' => $data['session']?->getId() ?? null,
            'company_name' => $data['companyName'] ?? null,
            'contract_type' => $data['contractType'] ?? null,
        ]);

        try {
            $contract = new AlternanceContract();

            $this->logger->debug('Populating new contract with provided data', [
                'contract_temp_id' => spl_object_hash($contract),
            ]);

            $this->populateContract($contract, $data);

            $this->logger->debug('Validating new contract', [
                'contract_temp_id' => spl_object_hash($contract),
                'contract_type' => $contract->getContractType(),
                'company_siret' => $contract->getCompanySiret(),
            ]);

            $errors = $this->validator->validate($contract);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }

                $this->logger->error('Contract validation failed during creation', [
                    'contract_temp_id' => spl_object_hash($contract),
                    'validation_errors' => $errorMessages,
                    'errors_count' => count($errors),
                ]);

                throw new InvalidArgumentException('Validation failed: ' . implode(', ', $errorMessages));
            }

            $this->logger->debug('Persisting new contract to database', [
                'contract_temp_id' => spl_object_hash($contract),
            ]);

            $this->entityManager->persist($contract);
            $this->entityManager->flush();

            $this->logger->info('Alternance contract created successfully', [
                'contract_id' => $contract->getId(),
                'contract_type' => $contract->getContractType(),
                'student_id' => $contract->getStudent()?->getId(),
                'session_id' => $contract->getSession()?->getId(),
                'company_name' => $contract->getCompanyName(),
                'status' => $contract->getStatus(),
            ]);

            return $contract;
        } catch (InvalidArgumentException $e) {
            // Re-throw validation exceptions as-is
            throw $e;
        } catch (Exception $e) {
            $this->logger->critical('Unexpected error during contract creation', [
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'data_keys' => array_keys($data),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new RuntimeException(
                sprintf('Erreur inattendue lors de la création du contrat: %s', $e->getMessage()),
                0,
                $e,
            );
        }
    }

    /**
     * Update an existing alternance contract.
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function updateContract(AlternanceContract $contract, array $data): AlternanceContract
    {
        $this->logger->info('Starting update of alternance contract', [
            'contract_id' => $contract->getId(),
            'current_status' => $contract->getStatus(),
            'data_keys' => array_keys($data),
            'company_name' => $contract->getCompanyName(),
        ]);

        try {
            // Store original values for logging
            $originalData = [
                'status' => $contract->getStatus(),
                'contract_type' => $contract->getContractType(),
                'company_name' => $contract->getCompanyName(),
                'total_weekly_hours' => null,
            ];

            try {
                $originalData['total_weekly_hours'] = $contract->getTotalWeeklyHours();
            } catch (Exception $e) {
                $this->logger->debug('Could not calculate original weekly hours', [
                    'contract_id' => $contract->getId(),
                    'error' => $e->getMessage(),
                ]);
            }

            $this->logger->debug('Populating contract with updated data', [
                'contract_id' => $contract->getId(),
                'original_data' => $originalData,
            ]);

            $this->populateContract($contract, $data);

            $this->logger->debug('Validating updated contract', [
                'contract_id' => $contract->getId(),
                'contract_type' => $contract->getContractType(),
                'status' => $contract->getStatus(),
            ]);

            $errors = $this->validator->validate($contract);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }

                $this->logger->error('Contract validation failed during update', [
                    'contract_id' => $contract->getId(),
                    'validation_errors' => $errorMessages,
                    'errors_count' => count($errors),
                    'original_data' => $originalData,
                ]);

                throw new InvalidArgumentException('Validation failed: ' . implode(', ', $errorMessages));
            }

            $this->logger->debug('Persisting contract updates to database', [
                'contract_id' => $contract->getId(),
            ]);

            $this->entityManager->flush();

            // Log the changes
            $updatedData = [
                'status' => $contract->getStatus(),
                'contract_type' => $contract->getContractType(),
                'company_name' => $contract->getCompanyName(),
                'total_weekly_hours' => null,
            ];

            try {
                $updatedData['total_weekly_hours'] = $contract->getTotalWeeklyHours();
            } catch (Exception $e) {
                $this->logger->debug('Could not calculate updated weekly hours', [
                    'contract_id' => $contract->getId(),
                    'error' => $e->getMessage(),
                ]);
            }

            $this->logger->info('Alternance contract updated successfully', [
                'contract_id' => $contract->getId(),
                'original_data' => $originalData,
                'updated_data' => $updatedData,
                'changed_fields' => array_keys($data),
            ]);

            return $contract;
        } catch (InvalidArgumentException $e) {
            // Re-throw validation exceptions as-is
            throw $e;
        } catch (Exception $e) {
            $this->logger->critical('Unexpected error during contract update', [
                'contract_id' => $contract->getId(),
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'data_keys' => array_keys($data),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new RuntimeException(
                sprintf(
                    'Erreur inattendue lors de la mise à jour du contrat %s: %s',
                    $contract->getId(),
                    $e->getMessage(),
                ),
                0,
                $e,
            );
        }
    }

    /**
     * Delete an alternance contract.
     */
    public function deleteContract(AlternanceContract $contract): void
    {
        $this->entityManager->remove($contract);
        $this->entityManager->flush();
    }

    /**
     * Validate a contract.
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function validateContract(AlternanceContract $contract): AlternanceContract
    {
        $this->logger->info('Starting contract validation process', [
            'contract_id' => $contract->getId(),
            'current_status' => $contract->getStatus(),
            'contract_type' => $contract->getContractType(),
            'company_name' => $contract->getCompanyName(),
        ]);

        try {
            if ($contract->getStatus() !== 'pending_validation') {
                $error = 'Contract must be in pending_validation status to be validated.';
                $this->logger->warning('Contract validation failed: Invalid status', [
                    'contract_id' => $contract->getId(),
                    'current_status' => $contract->getStatus(),
                    'required_status' => 'pending_validation',
                    'error' => $error,
                ]);

                throw new InvalidArgumentException($error);
            }

            $this->logger->debug('Updating contract status to validated', [
                'contract_id' => $contract->getId(),
                'previous_status' => $contract->getStatus(),
            ]);

            $contract->setStatus('validated');
            $contract->setValidatedAt(new DateTimeImmutable());

            $this->logger->debug('Persisting contract validation to database', [
                'contract_id' => $contract->getId(),
                'validated_at' => $contract->getValidatedAt()?->format('Y-m-d H:i:s'),
            ]);

            $this->entityManager->flush();

            $this->logger->info('Contract validated successfully', [
                'contract_id' => $contract->getId(),
                'new_status' => $contract->getStatus(),
                'validated_at' => $contract->getValidatedAt()?->format('Y-m-d H:i:s'),
            ]);

            return $contract;
        } catch (InvalidArgumentException $e) {
            // Re-throw business logic exceptions as-is
            throw $e;
        } catch (Exception $e) {
            $this->logger->critical('Unexpected error during contract validation', [
                'contract_id' => $contract->getId(),
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new RuntimeException(
                sprintf(
                    'Erreur inattendue lors de la validation du contrat %s: %s',
                    $contract->getId(),
                    $e->getMessage(),
                ),
                0,
                $e,
            );
        }
    }

    /**
     * Start a contract.
     *
     * @throws InvalidArgumentException
     */
    public function startContract(AlternanceContract $contract): AlternanceContract
    {
        if ($contract->getStatus() !== 'validated') {
            throw new InvalidArgumentException('Contract must be validated before starting.');
        }

        $contract->setStatus('active');
        $contract->setStartedAt(new DateTimeImmutable());

        $this->entityManager->flush();

        return $contract;
    }

    /**
     * Complete a contract.
     *
     * @throws InvalidArgumentException
     */
    public function completeContract(AlternanceContract $contract): AlternanceContract
    {
        if (!in_array($contract->getStatus(), ['active', 'suspended'], true)) {
            throw new InvalidArgumentException('Contract must be active or suspended to be completed.');
        }

        $contract->setStatus('completed');
        $contract->setCompletedAt(new DateTimeImmutable());

        $this->entityManager->flush();

        return $contract;
    }

    /**
     * Suspend a contract.
     *
     * @throws InvalidArgumentException
     */
    public function suspendContract(AlternanceContract $contract, string $reason = ''): AlternanceContract
    {
        if ($contract->getStatus() !== 'active') {
            throw new InvalidArgumentException('Only active contracts can be suspended.');
        }

        $contract->setStatus('suspended');

        if ($reason) {
            $additionalData = $contract->getAdditionalData() ?? [];
            $additionalData['suspension_reason'] = $reason;
            $additionalData['suspended_at'] = (new DateTimeImmutable())->format('Y-m-d H:i:s');
            $contract->setAdditionalData($additionalData);
        }

        $this->entityManager->flush();

        return $contract;
    }

    /**
     * Resume a suspended contract.
     *
     * @throws InvalidArgumentException
     */
    public function resumeContract(AlternanceContract $contract): AlternanceContract
    {
        if ($contract->getStatus() !== 'suspended') {
            throw new InvalidArgumentException('Only suspended contracts can be resumed.');
        }

        $contract->setStatus('active');

        $additionalData = $contract->getAdditionalData() ?? [];
        $additionalData['resumed_at'] = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $contract->setAdditionalData($additionalData);

        $this->entityManager->flush();

        return $contract;
    }

    /**
     * Terminate a contract.
     *
     * @throws InvalidArgumentException
     */
    public function terminateContract(AlternanceContract $contract, string $reason = ''): AlternanceContract
    {
        if (!in_array($contract->getStatus(), ['active', 'suspended'], true)) {
            throw new InvalidArgumentException('Only active or suspended contracts can be terminated.');
        }

        $contract->setStatus('terminated');

        $additionalData = $contract->getAdditionalData() ?? [];
        $additionalData['termination_reason'] = $reason;
        $additionalData['terminated_at'] = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $contract->setAdditionalData($additionalData);

        $this->entityManager->flush();

        return $contract;
    }

    /**
     * Get contracts by status.
     *
     * @return AlternanceContract[]
     */
    public function getContractsByStatus(string $status, ?int $limit = null): array
    {
        return $this->contractRepository->findByStatus($status, $limit);
    }

    /**
     * Get active contracts.
     *
     * @return AlternanceContract[]
     */
    public function getActiveContracts(?int $limit = null): array
    {
        return $this->contractRepository->findActiveContracts($limit);
    }

    /**
     * Get contracts ending soon.
     *
     * @return AlternanceContract[]
     */
    public function getContractsEndingSoon(int $days = 30): array
    {
        return $this->contractRepository->findEndingSoon($days);
    }

    /**
     * Get contract statistics.
     *
     * @throws RuntimeException
     */
    public function getContractStatistics(): array
    {
        $this->logger->info('Generating contract statistics');

        try {
            $this->logger->debug('Fetching status statistics from repository');
            $statusStats = $this->contractRepository->getStatusStatistics();

            $this->logger->debug('Fetching contract type statistics from repository');
            $typeStats = $this->contractRepository->getContractTypeStatistics();

            $this->logger->debug('Fetching monthly creation statistics from repository');
            $monthlyStats = $this->contractRepository->getMonthlyCreationStatistics();

            $statistics = [
                'by_status' => $statusStats,
                'by_contract_type' => $typeStats,
                'monthly_creation' => $monthlyStats,
            ];

            $this->logger->info('Contract statistics generated successfully', [
                'status_entries' => count($statusStats),
                'type_entries' => count($typeStats),
                'monthly_entries' => count($monthlyStats),
                'statistics_summary' => [
                    'total_statuses' => array_sum(array_column($statusStats, 'count')),
                    'total_by_types' => array_sum(array_column($typeStats, 'count')),
                ],
            ]);

            return $statistics;
        } catch (Exception $e) {
            $this->logger->error('Error generating contract statistics', [
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new RuntimeException(
                sprintf('Erreur lors de la génération des statistiques de contrats: %s', $e->getMessage()),
                0,
                $e,
            );
        }
    }

    /**
     * Search contracts with filters.
     *
     * @return AlternanceContract[]
     */
    public function searchContracts(array $filters): array
    {
        return $this->contractRepository->searchWithFilters($filters);
    }

    /**
     * Check if contract dates are valid.
     */
    public function validateContractDates(AlternanceContract $contract): bool
    {
        if (!$contract->getStartDate() || !$contract->getEndDate()) {
            return false;
        }

        // Contract must last at least 4 weeks
        $minDuration = 4 * 7; // 4 weeks in days
        $actualDuration = $contract->getDurationInDays();

        if ($actualDuration < $minDuration) {
            return false;
        }

        // Start date should be in the future (for new contracts)
        if ($contract->getId() === null && $contract->getStartDate() <= new DateTime()) {
            return false;
        }

        return true;
    }

    /**
     * Validate total weekly hours.
     */
    public function validateWeeklyHours(AlternanceContract $contract): bool
    {
        $totalHours = $contract->getTotalWeeklyHours();

        // Total hours should not exceed 35 hours per week for alternance
        return $totalHours <= 35 && $totalHours >= 20; // Minimum 20 hours per week
    }

    /**
     * Populate contract from data array.
     */
    private function populateContract(AlternanceContract $contract, array $data): void
    {
        if (isset($data['student'])) {
            $contract->setStudent($data['student']);
        }

        if (isset($data['session'])) {
            $contract->setSession($data['session']);
        }

        if (isset($data['companyName'])) {
            $contract->setCompanyName($data['companyName']);
        }

        if (isset($data['companyAddress'])) {
            $contract->setCompanyAddress($data['companyAddress']);
        }

        if (isset($data['companySiret'])) {
            $contract->setCompanySiret($data['companySiret']);
        }

        if (isset($data['mentor'])) {
            $contract->setMentor($data['mentor']);
        }

        if (isset($data['pedagogicalSupervisor'])) {
            $contract->setPedagogicalSupervisor($data['pedagogicalSupervisor']);
        }

        if (isset($data['contractType'])) {
            $contract->setContractType($data['contractType']);
        }

        if (isset($data['startDate'])) {
            $contract->setStartDate($data['startDate']);
        }

        if (isset($data['endDate'])) {
            $contract->setEndDate($data['endDate']);
        }

        if (isset($data['jobTitle'])) {
            $contract->setJobTitle($data['jobTitle']);
        }

        if (isset($data['jobDescription'])) {
            $contract->setJobDescription($data['jobDescription']);
        }

        if (isset($data['learningObjectives'])) {
            $contract->setLearningObjectives($data['learningObjectives']);
        }

        if (isset($data['companyObjectives'])) {
            $contract->setCompanyObjectives($data['companyObjectives']);
        }

        if (isset($data['weeklyCenterHours'])) {
            $contract->setWeeklyCenterHours($data['weeklyCenterHours']);
        }

        if (isset($data['weeklyCompanyHours'])) {
            $contract->setWeeklyCompanyHours($data['weeklyCompanyHours']);
        }

        if (isset($data['remuneration'])) {
            $contract->setRemuneration($data['remuneration']);
        }

        if (isset($data['status'])) {
            $contract->setStatus($data['status']);
        }

        if (isset($data['notes'])) {
            $contract->setNotes($data['notes']);
        }

        if (isset($data['additionalData'])) {
            $contract->setAdditionalData($data['additionalData']);
        }
    }
}
