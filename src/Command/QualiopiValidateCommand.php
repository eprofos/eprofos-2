<?php

namespace App\Command;

use App\Entity\Training\Formation;
use App\Repository\Training\FormationRepository;
use App\Service\QualiopiValidationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:qualiopi:validate',
    description: 'Validate Qualiopi compliance for formations',
)]
class QualiopiValidateCommand extends Command
{
    public function __construct(
        private FormationRepository $formationRepository,
        private QualiopiValidationService $qualiopiValidationService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Validation de la conformité Qualiopi 2.5');
        
        $formations = $this->formationRepository->findAll();
        
        if (empty($formations)) {
            $io->warning('Aucune formation trouvée dans la base de données.');
            return Command::SUCCESS;
        }
        
        $io->text(sprintf('Analyse de %d formation(s)...', count($formations)));
        
        $compliantCount = 0;
        $totalCount = count($formations);
        
        foreach ($formations as $formation) {
            $report = $this->qualiopiValidationService->generateQualiopiReport($formation);
            
            $io->section(sprintf('Formation: %s', $formation->getTitle()));
            
            if ($report['critere_2_5']['compliant']) {
                $io->success('✅ Conforme au critère Qualiopi 2.5');
                $compliantCount++;
            } else {
                $io->error('❌ Non conforme au critère Qualiopi 2.5');
                $io->listing($report['critere_2_5']['errors']);
            }
            
            $io->text(sprintf(
                'Score objectifs: %d/100 | Conformité générale: %.1f%%',
                $report['critere_2_5']['score'],
                $report['overall_compliance']
            ));
            
            // Show structured objectives if present
            if (!empty($report['critere_2_5']['operational_objectives'])) {
                $io->text('Objectifs opérationnels:');
                $io->listing($report['critere_2_5']['operational_objectives']);
            }
            
            if (!empty($report['critere_2_5']['evaluable_objectives'])) {
                $io->text('Objectifs évaluables:');
                $io->listing($report['critere_2_5']['evaluable_objectives']);
            }
            
            $io->newLine();
        }
        
        $io->title('Résumé');
        $io->text(sprintf(
            'Formations conformes: %d/%d (%.1f%%)',
            $compliantCount,
            $totalCount,
            ($compliantCount / $totalCount) * 100
        ));
        
        if ($compliantCount === $totalCount) {
            $io->success('🎉 Toutes les formations sont conformes au critère Qualiopi 2.5 !');
        } else {
            $io->warning(sprintf(
                '%d formation(s) nécessitent des améliorations pour être conformes.',
                $totalCount - $compliantCount
            ));
        }
        
        return Command::SUCCESS;
    }
}
