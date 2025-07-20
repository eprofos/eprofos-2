<?php

namespace App\Command;

use App\Entity\Training\Formation;
use App\Entity\Training\Module;
use App\Entity\Training\Chapter;
use App\Entity\Training\Course;
use App\Service\DurationCalculationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:duration:analyze',
    description: 'Analyze duration statistics and inconsistencies'
)]
class DurationAnalyzeCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DurationCalculationService $durationService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('entity-type', InputArgument::OPTIONAL, 'Entity type to analyze (formation, module, chapter, course, all)', 'all')
            ->addOption('inconsistencies-only', null, InputOption::VALUE_NONE, 'Show only entities with duration inconsistencies')
            ->addOption('threshold', null, InputOption::VALUE_OPTIONAL, 'Minimum difference threshold for inconsistencies (minutes for courses/chapters, hours for modules/formations)', 5)
            ->addOption('output-format', null, InputOption::VALUE_OPTIONAL, 'Output format (table, json, csv)', 'table')
            ->setHelp('This command analyzes duration statistics and reports inconsistencies between stored and calculated durations.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $entityType = $input->getArgument('entity-type');
        $inconsistenciesOnly = $input->getOption('inconsistencies-only');
        $threshold = (int) $input->getOption('threshold');
        $outputFormat = $input->getOption('output-format');
        
        $io->title('Duration Analysis Report');
        
        try {
            $results = match ($entityType) {
                'formation' => $this->analyzeFormations($threshold),
                'module' => $this->analyzeModules($threshold),
                'chapter' => $this->analyzeChapters($threshold),
                'course' => $this->analyzeCourses($threshold),
                'all' => $this->analyzeAll($threshold),
                default => throw new \InvalidArgumentException('Invalid entity type. Use: formation, module, chapter, course, or all')
            };
            
            // Filter for inconsistencies only if requested
            if ($inconsistenciesOnly) {
                $results = array_filter($results, fn($result) => $result['has_inconsistency']);
            }
            
            // Output results
            match ($outputFormat) {
                'table' => $this->outputTable($io, $results),
                'json' => $this->outputJson($output, $results),
                'csv' => $this->outputCsv($output, $results),
                default => throw new \InvalidArgumentException('Invalid output format. Use: table, json, or csv')
            };
            
            // Summary
            $totalEntities = count($results);
            $inconsistencies = count(array_filter($results, fn($result) => $result['has_inconsistency']));
            
            $io->info(sprintf('Analyzed %d entities, found %d inconsistencies', $totalEntities, $inconsistencies));
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $io->error('Duration analysis failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function analyzeAll(int $threshold): array
    {
        $results = [];
        
        $results = array_merge($results, $this->analyzeCourses($threshold));
        $results = array_merge($results, $this->analyzeChapters($threshold));
        $results = array_merge($results, $this->analyzeModules($threshold));
        $results = array_merge($results, $this->analyzeFormations($threshold));
        
        return $results;
    }

    private function analyzeCourses(int $threshold): array
    {
        $courses = $this->entityManager->getRepository(Course::class)->findBy(['isActive' => true]);
        $results = [];
        
        foreach ($courses as $course) {
            $stats = $this->durationService->getDurationStatistics($course);
            $difference = abs($stats['difference'] ?? 0);
            
            $results[] = [
                'entity_type' => 'Course',
                'entity_id' => $course->getId(),
                'entity_title' => $course->getTitle(),
                'stored_duration' => $stats['stored_duration'],
                'calculated_duration' => $stats['calculated_duration'],
                'difference' => $stats['difference'] ?? 0,
                'unit' => $stats['unit'],
                'has_inconsistency' => $difference >= $threshold,
                'exercise_count' => $stats['exercise_count'] ?? 0,
                'qcm_count' => $stats['qcm_count'] ?? 0,
                'parent_entity' => $course->getChapter() ? $course->getChapter()->getTitle() : 'N/A'
            ];
        }
        
        return $results;
    }

    private function analyzeChapters(int $threshold): array
    {
        $chapters = $this->entityManager->getRepository(Chapter::class)->findBy(['isActive' => true]);
        $results = [];
        
        foreach ($chapters as $chapter) {
            $stats = $this->durationService->getDurationStatistics($chapter);
            $difference = abs($stats['difference'] ?? 0);
            
            $results[] = [
                'entity_type' => 'Chapter',
                'entity_id' => $chapter->getId(),
                'entity_title' => $chapter->getTitle(),
                'stored_duration' => $stats['stored_duration'],
                'calculated_duration' => $stats['calculated_duration'],
                'difference' => $stats['difference'] ?? 0,
                'unit' => $stats['unit'],
                'has_inconsistency' => $difference >= $threshold,
                'course_count' => $stats['course_count'] ?? 0,
                'qcm_count' => null,
                'parent_entity' => $chapter->getModule() ? $chapter->getModule()->getTitle() : 'N/A'
            ];
        }
        
        return $results;
    }

    private function analyzeModules(int $threshold): array
    {
        $modules = $this->entityManager->getRepository(Module::class)->findBy(['isActive' => true]);
        $results = [];
        
        foreach ($modules as $module) {
            $stats = $this->durationService->getDurationStatistics($module);
            $difference = abs($stats['difference'] ?? 0);
            
            $results[] = [
                'entity_type' => 'Module',
                'entity_id' => $module->getId(),
                'entity_title' => $module->getTitle(),
                'stored_duration' => $stats['stored_duration'],
                'calculated_duration' => $stats['calculated_duration'],
                'difference' => $stats['difference'] ?? 0,
                'unit' => $stats['unit'],
                'has_inconsistency' => $difference >= $threshold,
                'exercise_count' => null,
                'qcm_count' => null,
                'parent_entity' => $module->getFormation() ? $module->getFormation()->getTitle() : 'N/A'
            ];
        }
        
        return $results;
    }

    private function analyzeFormations(int $threshold): array
    {
        $formations = $this->entityManager->getRepository(Formation::class)->findBy(['isActive' => true]);
        $results = [];
        
        foreach ($formations as $formation) {
            $stats = $this->durationService->getDurationStatistics($formation);
            $difference = abs($stats['difference'] ?? 0);
            
            $results[] = [
                'entity_type' => 'Formation',
                'entity_id' => $formation->getId(),
                'entity_title' => $formation->getTitle(),
                'stored_duration' => $stats['stored_duration'],
                'calculated_duration' => $stats['calculated_duration'],
                'difference' => $stats['difference'] ?? 0,
                'unit' => $stats['unit'],
                'has_inconsistency' => $difference >= $threshold,
                'exercise_count' => null,
                'qcm_count' => null,
                'parent_entity' => $formation->getCategory() ? $formation->getCategory()->getName() : 'N/A'
            ];
        }
        
        return $results;
    }

    private function outputTable(SymfonyStyle $io, array $results): void
    {
        if (empty($results)) {
            $io->info('No results to display');
            return;
        }
        
        $headers = [
            'Type',
            'ID',
            'Title',
            'Stored',
            'Calculated',
            'Difference',
            'Unit',
            'Inconsistent',
            'Parent'
        ];
        
        $rows = [];
        foreach ($results as $result) {
            $rows[] = [
                $result['entity_type'],
                $result['entity_id'],
                substr($result['entity_title'], 0, 30) . (strlen($result['entity_title']) > 30 ? '...' : ''),
                $result['stored_duration'],
                $result['calculated_duration'],
                $result['difference'],
                $result['unit'],
                $result['has_inconsistency'] ? '✗' : '✓',
                substr($result['parent_entity'], 0, 20) . (strlen($result['parent_entity']) > 20 ? '...' : '')
            ];
        }
        
        $io->table($headers, $rows);
    }

    private function outputJson(OutputInterface $output, array $results): void
    {
        $output->writeln(json_encode($results, JSON_PRETTY_PRINT));
    }

    private function outputCsv(OutputInterface $output, array $results): void
    {
        if (empty($results)) {
            return;
        }
        
        // Headers
        $headers = array_keys($results[0]);
        $output->writeln(implode(',', $headers));
        
        // Data rows
        foreach ($results as $result) {
            $row = array_map(function($value) {
                // Escape commas and quotes in CSV
                if (is_string($value) && (strpos($value, ',') !== false || strpos($value, '"') !== false)) {
                    return '"' . str_replace('"', '""', $value) . '"';
                }
                return $value;
            }, $result);
            
            $output->writeln(implode(',', $row));
        }
    }
}
