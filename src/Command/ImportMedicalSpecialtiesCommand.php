<?php

namespace App\Command;

use App\Infrastructure\Entity\MedicalSpecialty;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'command:import-medical-specialties',
    description: 'Imports a predefined list of medical specialties into the database.'
)]
class ImportMedicalSpecialtiesCommand extends Command
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $specialties = [
            'ACUPUNCTURA',
            'ALERGOLOG.,IMUNOLOG.',
            'ANATOMIE PATOLOGICA',
            'ANESTEZIE',
            'BOLI INFECTIOASE',
            'CARDIO PEDIATR',
            'CARDIOLOGIE',
            'CARDIOLOGIE INTERV',
            'CHIRURGIE CARDIACA',
            'CHIRURGIE DENTO-ALV.',
            'CHIRURGIE FACIALA',
            'CHIRURGIE GENERALA',
            'CHIRURGIE ORALA',
            'CHIRURGIE ORTOPEDIE',
            'CHIRURGIE PEDIATRICA',
            'CHIRURGIE PLASTICA',
            'CHIRURGIE TORACICA',
            'CHIRURGIE VASCULARA',
            'CT',
            'DENTIST',
            'DERMATOVENEROLOGIE',
            'DIABET ZAHARAT',
            'ECOGRAFIE',
            'ENDOCRINOLOGIE',
            'ENDODONTIE',
            'ENDOSCOPIE',
            'EPIDEMIOLOGIE',
            'EXPERTIZA MEDICALA',
            'EXPLORARI FUNCTIONAL',
            'FARMACIE CLINICA',
            'FARMACIE GENERALA',
            'FARMACOLOG. CLINICA',
            'FITOTERAPIE',
            'GASTRO PEDIATRICA',
            'GASTROENTEROLOGIE',
            'GENETICA MEDICALA',
            'GERIATRIE SI GERONT.',
            'GRUPA CHIRURGICALA',
            'GRUPA MEDICALA',
            'HEMATOLOGIE',
            'HOMEOPATIE',
            'IGIENA',
            'IMAGISTICA SAN',
            'INDUSTRIE FARMACEUT.',
            'LABORATOR FARMACEUT.',
            'MEDICINA DE FAMILIE',
            'MEDICINA DENTARA',
            'MEDICINA GENERALA',
            'MEDICINA INTERNA',
            'MEDICINA LABORATOR',
            'MEDICINA LEGALA',
            'MEDICINA MUNCII',
            'MEDICINA NUCLEARA',
            'MEDICINA SCOLARA',
            'MEDICINA SPORTIVA',
            'MEDICINA URGENTA',
            'MICROBIOLOGIE',
            'NA',
            'NEFRO PEDIATRICA',
            'NEFROLOGIE',
            'NEONATOLOGIE',
            'NEUROCHIRURGIE',
            'NEUROLOGIE',
            'NEUROLOGIE PEDIATR.',
            'OBSTETRICA-GINECOL.',
            'OFTALMOLOGIE',
            'ONCO HEMAT PEDI',
            'ONCOLOGIE MEDICALA',
            'ORTODONTIE',
            'ORTOPEDIE',
            'ORTOPEDIE PEDIATRICA',
            'ORTOPEDIE STOMA',
            'OTORINOLARINGOLOGIE',
            'PAL',
            'PARODONTOLOGIE',
            'PEDIATRIE',
            'PLANNING',
            'PNEUMO PEDIATRICA',
            'PNEUMOLOGIE',
            'PROTETICA DENTARA',
            'PSIHIATRIE',
            'PSIHIATRIE PEDIATR.',
            'PSIHOLOG',
            'PSIHOPEDAGOGIE SPECI',
            'RADIOIMG-DENT-MAX-FC',
            'RADIOLOGIE',
            'RADIOTERAPIE',
            'REABILITARE MEDICALA',
            'REUMATOLOGIE',
            'RMN',
            'SANATATE PUBLICA',
            'SPECIALITATI CLINICE',
            'SPECIALITATI FARM.',
            'SPECIALITATI PARACL.',
            'UROLOGIE'
        ];

        $counter = 2;
        foreach ($specialties as $specialtyName) {
            if (strlen($specialtyName) > 255) {
                $specialtyName = "Nume prestabilit";
            }

            $medicalSpecialty = new MedicalSpecialty();
            $medicalSpecialty->setName($specialtyName);
            $code = sprintf('%03d', $counter);
            $medicalSpecialty->setCode($code);
            $counter++;

            $this->entityManager->persist($medicalSpecialty);
        }

        $this->entityManager->flush();
        $output->writeln("<info>Medical Specialties imported successfully!</info>");

        return Command::SUCCESS;
    }
}
