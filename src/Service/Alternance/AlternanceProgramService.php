<?php

namespace App\Service\Alternance;

use App\Entity\Alternance\AlternanceProgram;
use App\Repository\AlternanceProgramRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Service for managing alternance programs
 * 
 * Provides business logic for CRUD operations, validation,
 * and program management for alternance pedagogical programs.
 */
class AlternanceProgramService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AlternanceProgramRepository $programRepository,
        private ValidatorInterface $validator
    ) {
    }

    /**
     * Create a new alternance program
     *
     * @param array $data
     * @return AlternanceProgram
     * @throws \InvalidArgumentException
     */
    public function createProgram(array $data): AlternanceProgram
    {
        $program = new AlternanceProgram();
        $this->populateProgram($program, $data);

        $errors = $this->validator->validate($program);
        if (count($errors) > 0) {
            throw new \InvalidArgumentException('Validation failed: ' . (string) $errors);
        }

        // Validate duration consistency
        if (!$program->hasConsistentDurations()) {
            throw new \InvalidArgumentException('Center duration + company duration must equal total duration.');
        }

        $this->entityManager->persist($program);
        $this->entityManager->flush();

        return $program;
    }

    /**
     * Update an existing alternance program
     *
     * @param AlternanceProgram $program
     * @param array $data
     * @return AlternanceProgram
     * @throws \InvalidArgumentException
     */
    public function updateProgram(AlternanceProgram $program, array $data): AlternanceProgram
    {
        $this->populateProgram($program, $data);

        $errors = $this->validator->validate($program);
        if (count($errors) > 0) {
            throw new \InvalidArgumentException('Validation failed: ' . (string) $errors);
        }

        // Validate duration consistency
        if (!$program->hasConsistentDurations()) {
            throw new \InvalidArgumentException('Center duration + company duration must equal total duration.');
        }

        $this->entityManager->flush();

        return $program;
    }

    /**
     * Delete an alternance program
     *
     * @param AlternanceProgram $program
     * @return void
     */
    public function deleteProgram(AlternanceProgram $program): void
    {
        $this->entityManager->remove($program);
        $this->entityManager->flush();
    }

    /**
     * Get program by session
     *
     * @param int $sessionId
     * @return AlternanceProgram|null
     */
    public function getProgramBySession(int $sessionId): ?AlternanceProgram
    {
        return $this->programRepository->findBySession($sessionId);
    }

    /**
     * Search programs with filters
     *
     * @param array $filters
     * @return AlternanceProgram[]
     */
    public function searchPrograms(array $filters): array
    {
        return $this->programRepository->searchWithFilters($filters);
    }

    /**
     * Get program statistics
     *
     * @return array
     */
    public function getProgramStatistics(): array
    {
        return [
            'duration' => $this->programRepository->getDurationStatistics(),
            'rhythm' => $this->programRepository->getRhythmStatistics(),
            'center_company' => $this->programRepository->getCenterCompanyStatistics(),
            'monthly_creation' => $this->programRepository->getMonthlyCreationStatistics(),
        ];
    }

    /**
     * Add center module to program
     *
     * @param AlternanceProgram $program
     * @param array $moduleData
     * @return AlternanceProgram
     */
    public function addCenterModule(AlternanceProgram $program, array $moduleData): AlternanceProgram
    {
        $modules = $program->getCenterModules();
        $modules[] = $this->validateModuleData($moduleData);
        $program->setCenterModules($modules);

        $this->entityManager->flush();

        return $program;
    }

    /**
     * Add company module to program
     *
     * @param AlternanceProgram $program
     * @param array $moduleData
     * @return AlternanceProgram
     */
    public function addCompanyModule(AlternanceProgram $program, array $moduleData): AlternanceProgram
    {
        $modules = $program->getCompanyModules();
        $modules[] = $this->validateModuleData($moduleData);
        $program->setCompanyModules($modules);

        $this->entityManager->flush();

        return $program;
    }

    /**
     * Add coordination point to program
     *
     * @param AlternanceProgram $program
     * @param array $coordinationData
     * @return AlternanceProgram
     */
    public function addCoordinationPoint(AlternanceProgram $program, array $coordinationData): AlternanceProgram
    {
        $points = $program->getCoordinationPoints();
        $points[] = $this->validateCoordinationData($coordinationData);
        $program->setCoordinationPoints($points);

        $this->entityManager->flush();

        return $program;
    }

    /**
     * Add assessment period to program
     *
     * @param AlternanceProgram $program
     * @param array $assessmentData
     * @return AlternanceProgram
     */
    public function addAssessmentPeriod(AlternanceProgram $program, array $assessmentData): AlternanceProgram
    {
        $periods = $program->getAssessmentPeriods();
        $periods[] = $this->validateAssessmentData($assessmentData);
        $program->setAssessmentPeriods($periods);

        $this->entityManager->flush();

        return $program;
    }

    /**
     * Add learning progression step to program
     *
     * @param AlternanceProgram $program
     * @param array $progressionData
     * @return AlternanceProgram
     */
    public function addLearningProgressionStep(AlternanceProgram $program, array $progressionData): AlternanceProgram
    {
        $progression = $program->getLearningProgression();
        $progression[] = $this->validateProgressionData($progressionData);
        $program->setLearningProgression($progression);

        $this->entityManager->flush();

        return $program;
    }

    /**
     * Generate default program structure based on session
     *
     * @param object $session Session entity
     * @return array
     */
    public function generateDefaultProgramStructure($session): array
    {
        $formation = $session->getFormation();
        
        return [
            'title' => 'Programme d\'alternance - ' . $formation->getTitle(),
            'description' => 'Programme pédagogique d\'alternance pour la formation ' . $formation->getTitle(),
            'totalDuration' => max(52, intval($formation->getDurationHours() / 35)), // Minimum 52 weeks
            'centerDuration' => intval($formation->getDurationHours() / 35),
            'companyDuration' => max(52 - intval($formation->getDurationHours() / 35), 26),
            'rhythm' => '2-2', // 2 weeks center / 2 weeks company
            'centerModules' => $this->generateCenterModulesFromFormation($formation),
            'companyModules' => $this->generateDefaultCompanyModules(),
            'coordinationPoints' => $this->generateDefaultCoordinationPoints(),
            'assessmentPeriods' => $this->generateDefaultAssessmentPeriods(),
            'learningProgression' => $this->generateDefaultLearningProgression(),
        ];
    }

    /**
     * Populate program from data array
     *
     * @param AlternanceProgram $program
     * @param array $data
     * @return void
     */
    private function populateProgram(AlternanceProgram $program, array $data): void
    {
        if (isset($data['session'])) {
            $program->setSession($data['session']);
        }

        if (isset($data['title'])) {
            $program->setTitle($data['title']);
        }

        if (isset($data['description'])) {
            $program->setDescription($data['description']);
        }

        if (isset($data['totalDuration'])) {
            $program->setTotalDuration($data['totalDuration']);
        }

        if (isset($data['centerDuration'])) {
            $program->setCenterDuration($data['centerDuration']);
        }

        if (isset($data['companyDuration'])) {
            $program->setCompanyDuration($data['companyDuration']);
        }

        if (isset($data['centerModules'])) {
            $program->setCenterModules($data['centerModules']);
        }

        if (isset($data['companyModules'])) {
            $program->setCompanyModules($data['companyModules']);
        }

        if (isset($data['coordinationPoints'])) {
            $program->setCoordinationPoints($data['coordinationPoints']);
        }

        if (isset($data['assessmentPeriods'])) {
            $program->setAssessmentPeriods($data['assessmentPeriods']);
        }

        if (isset($data['rhythm'])) {
            $program->setRhythm($data['rhythm']);
        }

        if (isset($data['learningProgression'])) {
            $program->setLearningProgression($data['learningProgression']);
        }

        if (isset($data['notes'])) {
            $program->setNotes($data['notes']);
        }

        if (isset($data['additionalData'])) {
            $program->setAdditionalData($data['additionalData']);
        }
    }

    /**
     * Validate module data
     *
     * @param array $moduleData
     * @return array
     * @throws \InvalidArgumentException
     */
    private function validateModuleData(array $moduleData): array
    {
        $required = ['title', 'description', 'duration', 'objectives'];
        
        foreach ($required as $field) {
            if (!isset($moduleData[$field]) || empty($moduleData[$field])) {
                throw new \InvalidArgumentException("Module field '{$field}' is required and cannot be empty.");
            }
        }

        return [
            'title' => $moduleData['title'],
            'description' => $moduleData['description'],
            'duration' => $moduleData['duration'],
            'objectives' => $moduleData['objectives'],
            'methods' => $moduleData['methods'] ?? [],
            'resources' => $moduleData['resources'] ?? [],
            'assessment' => $moduleData['assessment'] ?? '',
        ];
    }

    /**
     * Validate coordination data
     *
     * @param array $coordinationData
     * @return array
     * @throws \InvalidArgumentException
     */
    private function validateCoordinationData(array $coordinationData): array
    {
        $required = ['summary', 'frequency', 'participants'];
        
        foreach ($required as $field) {
            if (!isset($coordinationData[$field]) || empty($coordinationData[$field])) {
                throw new \InvalidArgumentException("Coordination field '{$field}' is required and cannot be empty.");
            }
        }

        return [
            'summary' => $coordinationData['summary'],
            'frequency' => $coordinationData['frequency'],
            'participants' => $coordinationData['participants'],
            'objectives' => $coordinationData['objectives'] ?? [],
            'methods' => $coordinationData['methods'] ?? [],
        ];
    }

    /**
     * Validate assessment data
     *
     * @param array $assessmentData
     * @return array
     * @throws \InvalidArgumentException
     */
    private function validateAssessmentData(array $assessmentData): array
    {
        $required = ['name', 'type', 'timing', 'criteria'];
        
        foreach ($required as $field) {
            if (!isset($assessmentData[$field]) || empty($assessmentData[$field])) {
                throw new \InvalidArgumentException("Assessment field '{$field}' is required and cannot be empty.");
            }
        }

        return [
            'name' => $assessmentData['name'],
            'type' => $assessmentData['type'],
            'timing' => $assessmentData['timing'],
            'criteria' => $assessmentData['criteria'],
            'methods' => $assessmentData['methods'] ?? [],
            'tools' => $assessmentData['tools'] ?? [],
        ];
    }

    /**
     * Validate progression data
     *
     * @param array $progressionData
     * @return array
     * @throws \InvalidArgumentException
     */
    private function validateProgressionData(array $progressionData): array
    {
        $required = ['milestone', 'description', 'period', 'competencies'];
        
        foreach ($required as $field) {
            if (!isset($progressionData[$field]) || empty($progressionData[$field])) {
                throw new \InvalidArgumentException("Progression field '{$field}' is required and cannot be empty.");
            }
        }

        return [
            'milestone' => $progressionData['milestone'],
            'description' => $progressionData['description'],
            'period' => $progressionData['period'],
            'competencies' => $progressionData['competencies'],
            'evaluation' => $progressionData['evaluation'] ?? [],
        ];
    }

    /**
     * Generate center modules from formation
     *
     * @param object $formation
     * @return array
     */
    private function generateCenterModulesFromFormation($formation): array
    {
        $modules = [];
        
        // This would typically iterate through formation modules
        // For now, we'll create a basic structure
        $modules[] = [
            'title' => 'Modules théoriques',
            'description' => 'Apprentissage des concepts fondamentaux en centre de formation',
            'duration' => 4,
            'objectives' => ['Acquérir les bases théoriques', 'Développer les compétences techniques'],
            'methods' => ['Cours magistraux', 'Travaux pratiques', 'Études de cas'],
            'resources' => ['Supports de cours', 'Plateforme e-learning'],
            'assessment' => 'Évaluations continues et examens'
        ];

        return $modules;
    }

    /**
     * Generate default company modules
     *
     * @return array
     */
    private function generateDefaultCompanyModules(): array
    {
        return [
            [
                'title' => 'Intégration en entreprise',
                'description' => 'Découverte de l\'environnement professionnel et des processus métier',
                'duration' => 2,
                'objectives' => ['Comprendre l\'organisation', 'S\'intégrer dans l\'équipe'],
                'methods' => ['Observation', 'Accompagnement', 'Missions progressives'],
                'resources' => ['Livret d\'accueil', 'Référent entreprise'],
                'assessment' => 'Rapport d\'intégration'
            ],
            [
                'title' => 'Mise en pratique',
                'description' => 'Application des compétences acquises en centre de formation',
                'duration' => 8,
                'objectives' => ['Appliquer les connaissances', 'Développer l\'autonomie'],
                'methods' => ['Projets réels', 'Missions encadrées'],
                'resources' => ['Outils métier', 'Documentation technique'],
                'assessment' => 'Évaluation des compétences en situation'
            ]
        ];
    }

    /**
     * Generate default coordination points
     *
     * @return array
     */
    private function generateDefaultCoordinationPoints(): array
    {
        return [
            [
                'summary' => 'Réunion de suivi mensuelle',
                'frequency' => 'Mensuelle',
                'participants' => ['Alternant', 'Tuteur entreprise', 'Référent pédagogique'],
                'objectives' => ['Faire le point sur les apprentissages', 'Ajuster le parcours'],
                'methods' => ['Entretien tripartite', 'Bilan de compétences']
            ],
            [
                'summary' => 'Visite en entreprise',
                'frequency' => 'Trimestrielle',
                'participants' => ['Référent pédagogique', 'Tuteur entreprise'],
                'objectives' => ['Observer l\'alternant en situation', 'Coordination pédagogique'],
                'methods' => ['Observation directe', 'Échange avec le tuteur']
            ]
        ];
    }

    /**
     * Generate default assessment periods
     *
     * @return array
     */
    private function generateDefaultAssessmentPeriods(): array
    {
        return [
            [
                'name' => 'Évaluation de mi-parcours',
                'type' => 'Formative',
                'timing' => 'Milieu de formation',
                'criteria' => ['Progression des apprentissages', 'Intégration en entreprise'],
                'methods' => ['Entretien', 'Grille d\'évaluation'],
                'tools' => ['Livret de suivi', 'Portfolio de compétences']
            ],
            [
                'name' => 'Évaluation finale',
                'type' => 'Certificative',
                'timing' => 'Fin de formation',
                'criteria' => ['Maîtrise des compétences', 'Projet professionnel'],
                'methods' => ['Soutenance', 'Mise en situation'],
                'tools' => ['Rapport final', 'Présentation orale']
            ]
        ];
    }

    /**
     * Generate default learning progression
     *
     * @return array
     */
    private function generateDefaultLearningProgression(): array
    {
        return [
            [
                'milestone' => 'Découverte',
                'description' => 'Phase de découverte du métier et de l\'environnement professionnel',
                'period' => 'Premiers 3 mois',
                'competencies' => ['Connaissance de l\'entreprise', 'Bases du métier'],
                'evaluation' => ['Observation', 'Questionnaire de découverte']
            ],
            [
                'milestone' => 'Développement',
                'description' => 'Développement des compétences techniques et relationnelles',
                'period' => 'Mois 4 à 9',
                'competencies' => ['Compétences techniques', 'Autonomie', 'Communication'],
                'evaluation' => ['Projets encadrés', 'Évaluation continue']
            ],
            [
                'milestone' => 'Maîtrise',
                'description' => 'Maîtrise des compétences et préparation à l\'insertion professionnelle',
                'period' => 'Derniers 3 mois',
                'competencies' => ['Expertise métier', 'Management', 'Innovation'],
                'evaluation' => ['Projet final', 'Évaluation certificative']
            ]
        ];
    }
}
