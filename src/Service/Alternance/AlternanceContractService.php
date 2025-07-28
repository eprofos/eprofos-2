<?php

declare(strict_types=1);

namespace App\Service\Alternance;

use App\Entity\Alternance\AlternanceContract;
use App\Repository\Alternance\AlternanceContractRepository;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
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
    ) {}

    /**
     * Create a new alternance contract.
     *
     * @throws InvalidArgumentException
     */
    public function createContract(array $data): AlternanceContract
    {
        $contract = new AlternanceContract();
        $this->populateContract($contract, $data);

        $errors = $this->validator->validate($contract);
        if (count($errors) > 0) {
            throw new InvalidArgumentException('Validation failed: ' . (string) $errors);
        }

        $this->entityManager->persist($contract);
        $this->entityManager->flush();

        return $contract;
    }

    /**
     * Update an existing alternance contract.
     *
     * @throws InvalidArgumentException
     */
    public function updateContract(AlternanceContract $contract, array $data): AlternanceContract
    {
        $this->populateContract($contract, $data);

        $errors = $this->validator->validate($contract);
        if (count($errors) > 0) {
            throw new InvalidArgumentException('Validation failed: ' . (string) $errors);
        }

        $this->entityManager->flush();

        return $contract;
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
     */
    public function validateContract(AlternanceContract $contract): AlternanceContract
    {
        if ($contract->getStatus() !== 'pending_validation') {
            throw new InvalidArgumentException('Contract must be in pending_validation status to be validated.');
        }

        $contract->setStatus('validated');
        $contract->setValidatedAt(new DateTimeImmutable());

        $this->entityManager->flush();

        return $contract;
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
     */
    public function getContractStatistics(): array
    {
        return [
            'by_status' => $this->contractRepository->getStatusStatistics(),
            'by_contract_type' => $this->contractRepository->getContractTypeStatistics(),
            'monthly_creation' => $this->contractRepository->getMonthlyCreationStatistics(),
        ];
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
