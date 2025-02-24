<?php

namespace App\Command;

use App\Infrastructure\Entity\Doctor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'command:import-doctors-from-csv',
    description: 'Imports doctors from a CSV file. Removes "Dr." prefix from names.'
)]
class ImportDoctorsCommand extends Command
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager, string $projectDir)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->projectDir = $projectDir;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $csvFile = $this->projectDir . '/exports/doctors.csv';

        if (!file_exists($csvFile) || !is_readable($csvFile)) {
            $output->writeln("<error>CSV file not found or not readable: $csvFile</error>");
            return Command::FAILURE;
        }

        if (($handle = fopen($csvFile, 'r')) === false) {
            $output->writeln("<error>Unable to open CSV file: $csvFile</error>");
            return Command::FAILURE;
        }

        $header = fgetcsv($handle, 1000, ",");
        if ($header === false) {
            $output->writeln("<error>CSV file is empty.</error>");
            fclose($handle);
            return Command::FAILURE;
        }

        $counter = 1;

        while (($data = fgetcsv($handle, 1000, ",")) !== false) {
            if (count($data) !== count($header)) {
                $output->writeln("<error>Row with mismatched column count, skipping row.</error>");
                continue;
            }
            $row = array_combine($header, $data);
            $firstName = $row['first_name'];
            $lastName = $row['last_name'];

            $email = $row['email'] ?? sprintf('doctor%03d@example.com', $counter);
            $existingDoctor = $this->entityManager->getRepository(Doctor::class)->findOneBy(['email' => $email]);
            if ($existingDoctor) {
                $output->writeln("<comment>Skipping duplicate email: $email</comment>");
                continue;
            }
            $cnp = $row['cnp'] ?? str_pad((string)$counter, 13, '0', STR_PAD_LEFT);
            $phone = $row['phone'] ?? sprintf('070000%04d', $counter);

            $password = password_hash('defaultpassword', PASSWORD_BCRYPT);

            $doctor = new Doctor();
            $doctor->setEmail($email);
            $doctor->setFirstName($firstName);
            $doctor->setLastName($lastName);
            $doctor->setCnp($cnp);
            $doctor->setPhone($phone);
            $doctor->setPhoto("");
            $doctor->setRoles(['ROLE_DOCTOR']);
            $doctor->setPassword($password);

            $this->entityManager->persist($doctor);
            $counter++;
        }

        fclose($handle);
        $this->entityManager->flush();
        $output->writeln("<info>Doctors imported successfully!</info>");

        return Command::SUCCESS;
    }
}
