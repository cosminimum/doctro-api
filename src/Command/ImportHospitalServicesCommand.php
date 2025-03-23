<?php
namespace App\Command;

use App\Infrastructure\Entity\HospitalService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'command:import-hospital-services',
    description: 'Import hospital services from a CSV file located in /exports'
)]
class ImportHospitalServicesCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private string $projectDir;

    public function __construct(EntityManagerInterface $entityManager, string $projectDir)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->projectDir = $projectDir;
    }

    protected function configure(): void
    {
        $this->setDescription('Import hospital services from a CSV file.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $csvFile = $this->projectDir . '/exports/services.csv';

        if (!file_exists($csvFile) || !is_readable($csvFile)) {
            $output->writeln("<error>CSV file not found or not readable: $csvFile</error>");
            return Command::FAILURE;
        }

        if (($handle = fopen($csvFile, 'r')) !== false) {
            $header = fgetcsv($handle, 1000, ",");
            if ($header === false) {
                $output->writeln("<error>CSV file appears to be empty.</error>");
                return Command::FAILURE;
            }

            while (($data = fgetcsv($handle, 1000, ",")) !== false) {
                if (count($data) !== count($header)) {
                    $output->writeln("<error>Row has mismatched columns, skipping row.</error>");
                    continue;
                }
                $row = array_combine($header, $data);

                if (!isset($row['name'])) {
                    $output->writeln("<error>Missing 'name' field in row, skipping row.</error>");
                    continue;
                }

                $serviceName = $row['name'];
                if (strlen($serviceName) > 255) {
                    $serviceName = "Nume prestabilit";
                }

                $hospitalService = new HospitalService();
                $hospitalService->setName($serviceName);
                $hospitalService->setDescription($row['description'] ?? '');
                $hospitalService->setCode($row['code'] ?? '');
                $hospitalService->setPrice($row['price'] ?? '');
                $hospitalService->setDuration('30');
                $hospitalService->setMode($row['mode'] ?? '');

                $hospitalService->setIsActive(true);

                $this->entityManager->persist($hospitalService);
            }

            fclose($handle);

            $this->entityManager->flush();
            $output->writeln("<info>Import completed successfully!</info>");
        } else {
            $output->writeln("<error>Unable to open the CSV file.</error>");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
