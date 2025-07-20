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
use Symfony\Component\Console\Helper\ProgressBar;

#[AsCommand(
    name: 'app:duration:sync',
    description: 'Synchronize duration calculations across all entities'
)]
class DurationSyncCommand extends Command
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
            ->addArgument('entity-type', InputArgument::OPTIONAL, 'Entity type to sync (formation, module, chapter, course, all)', 'all')
            ->addOption('entity-id', null, InputOption::VALUE_OPTIONAL, 'Specific entity ID to sync')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be updated without making changes')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Force update even if calculated duration matches stored duration')
            ->addOption('clear-cache', null, InputOption::VALUE_NONE, 'Clear duration caches before syncing')
            ->addOption('batch-size', null, InputOption::VALUE_OPTIONAL, 'Number of entities to process in each batch', 50)
            ->setHelp('This command synchronizes duration calculations across the entity hierarchy.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $entityType = $input->getArgument('entity-type');
        $entityId = $input->getOption('entity-id');
        $dryRun = $input->getOption('dry-run');
        $force = $input->getOption('force');
        $clearCache = $input->getOption('clear-cache');
        $batchSize = (int) $input->getOption('batch-size');
        
        if ($clearCache) {
            $io->info('Clearing duration caches...');
            $this->durationService->clearDurationCaches();
        }
        
        if ($dryRun) {
            $io->warning('DRY RUN MODE - No changes will be made');
        }
        
        $io->title('Duration Synchronization');
        
        try {
            match ($entityType) {
                'formation' => $this->syncFormations($io, $entityId, $dryRun, $force, $batchSize),
                'module' => $this->syncModules($io, $entityId, $dryRun, $force, $batchSize),
                'chapter' => $this->syncChapters($io, $entityId, $dryRun, $force, $batchSize),
                'course' => $this->syncCourses($io, $entityId, $dryRun, $force, $batchSize),
                'all' => $this->syncAll($io, $dryRun, $force, $batchSize),
                default => throw new \InvalidArgumentException('Invalid entity type. Use: formation, module, chapter, course, or all')
            };
            
            $io->success('Duration synchronization completed successfully!');
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $io->error('Duration synchronization failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function syncAll(SymfonyStyle $io, bool $dryRun, bool $force, int $batchSize): void
    {
        $io->section('Syncing all entities (bottom-up approach)');
        
        // Sync in dependency order: Course → Chapter → Module → Formation
        $this->syncCourses($io, null, $dryRun, $force, $batchSize);
        $this->syncChapters($io, null, $dryRun, $force, $batchSize);
        $this->syncModules($io, null, $dryRun, $force, $batchSize);
        $this->syncFormations($io, null, $dryRun, $force, $batchSize);
    }

    private function syncCourses(SymfonyStyle $io, ?string $entityId, bool $dryRun, bool $force, int $batchSize): void
    {
        $io->section('Syncing Course durations');
        
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('c')
            ->from(Course::class, 'c')
            ->where('c.isActive = :active')
            ->setParameter('active', true);
        
        if ($entityId) {
            $queryBuilder->andWhere('c.id = :id')->setParameter('id', $entityId);
        }
        
        $courses = $queryBuilder->getQuery()->getResult();
        $totalCourses = count($courses);
        
        if ($totalCourses === 0) {
            $io->info('No courses found to sync');
            return;
        }
        
        $progressBar = new ProgressBar($output = $io, $totalCourses);
        $progressBar->start();
        
        $updatedCount = 0;
        $errorCount = 0;
        
        foreach (array_chunk($courses, $batchSize) as $batch) {
            $this->entityManager->beginTransaction();
            
            try {
                foreach ($batch as $course) {
                    $stats = $this->durationService->getDurationStatistics($course);
                    
                    if ($force || $stats['needs_update']) {
                        if (!$dryRun) {
                            $this->durationService->updateEntityDuration($course);
                        }
                        $updatedCount++;
                        
                        $io->writeln(sprintf(
                            '<info>Course "%s" - Stored: %d min, Calculated: %d min, Difference: %d min</info>',
                            $course->getTitle(),
                            $stats['stored_duration'],
                            $stats['calculated_duration'],
                            $stats['difference']
                        ));
                    }
                    
                    $progressBar->advance();
                }
                
                if (!$dryRun) {
                    $this->entityManager->flush();
                    $this->entityManager->commit();
                }
                
            } catch (\Exception $e) {
                $this->entityManager->rollback();
                $errorCount++;
                $io->error('Error processing course batch: ' . $e->getMessage());
            }
        }
        
        $progressBar->finish();
        $io->newLine();
        $io->info(sprintf('Courses processed: %d, Updated: %d, Errors: %d', $totalCourses, $updatedCount, $errorCount));
    }

    private function syncChapters(SymfonyStyle $io, ?string $entityId, bool $dryRun, bool $force, int $batchSize): void
    {
        $io->section('Syncing Chapter durations');
        
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('c')
            ->from(Chapter::class, 'c')
            ->where('c.isActive = :active')
            ->setParameter('active', true);
        
        if ($entityId) {
            $queryBuilder->andWhere('c.id = :id')->setParameter('id', $entityId);
        }
        
        $chapters = $queryBuilder->getQuery()->getResult();
        $totalChapters = count($chapters);
        
        if ($totalChapters === 0) {
            $io->info('No chapters found to sync');
            return;
        }
        
        $progressBar = new ProgressBar($output = $io, $totalChapters);
        $progressBar->start();
        
        $updatedCount = 0;
        $errorCount = 0;
        
        foreach (array_chunk($chapters, $batchSize) as $batch) {
            $this->entityManager->beginTransaction();
            
            try {
                foreach ($batch as $chapter) {
                    $stats = $this->durationService->getDurationStatistics($chapter);
                    
                    if ($force || $stats['needs_update']) {
                        if (!$dryRun) {
                            $this->durationService->updateEntityDuration($chapter);
                        }
                        $updatedCount++;
                        
                        $io->writeln(sprintf(
                            '<info>Chapter "%s" - Stored: %d min, Calculated: %d min, Difference: %d min</info>',
                            $chapter->getTitle(),
                            $stats['stored_duration'],
                            $stats['calculated_duration'],
                            $stats['difference']
                        ));
                    }
                    
                    $progressBar->advance();
                }
                
                if (!$dryRun) {
                    $this->entityManager->flush();
                    $this->entityManager->commit();
                }
                
            } catch (\Exception $e) {
                $this->entityManager->rollback();
                $errorCount++;
                $io->error('Error processing chapter batch: ' . $e->getMessage());
            }
        }
        
        $progressBar->finish();
        $io->newLine();
        $io->info(sprintf('Chapters processed: %d, Updated: %d, Errors: %d', $totalChapters, $updatedCount, $errorCount));
    }

    private function syncModules(SymfonyStyle $io, ?string $entityId, bool $dryRun, bool $force, int $batchSize): void
    {
        $io->section('Syncing Module durations');
        
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('m')
            ->from(Module::class, 'm')
            ->where('m.isActive = :active')
            ->setParameter('active', true);
        
        if ($entityId) {
            $queryBuilder->andWhere('m.id = :id')->setParameter('id', $entityId);
        }
        
        $modules = $queryBuilder->getQuery()->getResult();
        $totalModules = count($modules);
        
        if ($totalModules === 0) {
            $io->info('No modules found to sync');
            return;
        }
        
        $progressBar = new ProgressBar($output = $io, $totalModules);
        $progressBar->start();
        
        $updatedCount = 0;
        $errorCount = 0;
        
        foreach (array_chunk($modules, $batchSize) as $batch) {
            $this->entityManager->beginTransaction();
            
            try {
                foreach ($batch as $module) {
                    $stats = $this->durationService->getDurationStatistics($module);
                    
                    if ($force || $stats['needs_update']) {
                        if (!$dryRun) {
                            $this->durationService->updateEntityDuration($module);
                        }
                        $updatedCount++;
                        
                        $io->writeln(sprintf(
                            '<info>Module "%s" - Stored: %d hours, Calculated: %d hours, Difference: %d hours</info>',
                            $module->getTitle(),
                            $stats['stored_duration'],
                            $stats['calculated_duration'],
                            $stats['difference']
                        ));
                    }
                    
                    $progressBar->advance();
                }
                
                if (!$dryRun) {
                    $this->entityManager->flush();
                    $this->entityManager->commit();
                }
                
            } catch (\Exception $e) {
                $this->entityManager->rollback();
                $errorCount++;
                $io->error('Error processing module batch: ' . $e->getMessage());
            }
        }
        
        $progressBar->finish();
        $io->newLine();
        $io->info(sprintf('Modules processed: %d, Updated: %d, Errors: %d', $totalModules, $updatedCount, $errorCount));
    }

    private function syncFormations(SymfonyStyle $io, ?string $entityId, bool $dryRun, bool $force, int $batchSize): void
    {
        $io->section('Syncing Formation durations');
        
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('f')
            ->from(Formation::class, 'f')
            ->where('f.isActive = :active')
            ->setParameter('active', true);
        
        if ($entityId) {
            $queryBuilder->andWhere('f.id = :id')->setParameter('id', $entityId);
        }
        
        $formations = $queryBuilder->getQuery()->getResult();
        $totalFormations = count($formations);
        
        if ($totalFormations === 0) {
            $io->info('No formations found to sync');
            return;
        }
        
        $progressBar = new ProgressBar($output = $io, $totalFormations);
        $progressBar->start();
        
        $updatedCount = 0;
        $errorCount = 0;
        
        foreach (array_chunk($formations, $batchSize) as $batch) {
            $this->entityManager->beginTransaction();
            
            try {
                foreach ($batch as $formation) {
                    $stats = $this->durationService->getDurationStatistics($formation);
                    
                    if ($force || $stats['needs_update']) {
                        if (!$dryRun) {
                            $this->durationService->updateEntityDuration($formation);
                        }
                        $updatedCount++;
                        
                        $io->writeln(sprintf(
                            '<info>Formation "%s" - Stored: %d hours, Calculated: %d hours, Difference: %d hours</info>',
                            $formation->getTitle(),
                            $stats['stored_duration'],
                            $stats['calculated_duration'],
                            $stats['difference']
                        ));
                    }
                    
                    $progressBar->advance();
                }
                
                if (!$dryRun) {
                    $this->entityManager->flush();
                    $this->entityManager->commit();
                }
                
            } catch (\Exception $e) {
                $this->entityManager->rollback();
                $errorCount++;
                $io->error('Error processing formation batch: ' . $e->getMessage());
            }
        }
        
        $progressBar->finish();
        $io->newLine();
        $io->info(sprintf('Formations processed: %d, Updated: %d, Errors: %d', $totalFormations, $updatedCount, $errorCount));
    }
}
