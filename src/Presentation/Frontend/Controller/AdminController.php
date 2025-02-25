<?php

declare(strict_types=1);

namespace App\Presentation\Frontend\Controller;

use App\Application\Story\DoctorRegisterStory;
use App\Domain\Dto\UserCreateRequestDto;
use App\Infrastructure\Entity\Doctor;
use App\Infrastructure\Entity\HospitalService;
use App\Infrastructure\Entity\HospitalSettings;
use App\Infrastructure\Entity\MedicalSpecialty;
use App\Infrastructure\Entity\User;
use App\Infrastructure\Repository\DoctorRepository;
use App\Infrastructure\Repository\HospitalServiceRepository;
use App\Infrastructure\Repository\HospitalSettingsRepository;
use App\Infrastructure\Repository\MedicalSpecialtyRepository;
use App\Presentation\Frontend\Form\AdminDoctorFormType;
use App\Presentation\Frontend\Form\AdminServiceFormType;
use App\Presentation\Frontend\Form\AdminSpecialtyFormType;
use App\Presentation\Frontend\Form\HospitalSettingsFormType;
use App\Presentation\Frontend\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class AdminController extends AbstractController
{
    #[Route('/admin/doctors', name: 'admin_doctors', methods: ['GET', 'POST'])]
    public function doctorList(
        Request $request,
        DoctorRepository $doctorRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em
    ): Response {
        $form = $this->createForm(AdminDoctorFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var Doctor $doctor */
            $doctor = $form->getData();

            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            $hashedPassword = $passwordHasher->hashPassword(
                $doctor,
                $plainPassword
            );

            $doctor->setPassword($hashedPassword);
            $doctor->setRoles([Doctor::BASE_ROLE]);

            $em->persist($doctor);
            $em->flush();

            $this->addFlash('success', 'Doctorul a fost creat cu succes.');
            return $this->redirectToRoute('admin_doctors');
        }

        return $this->render('pages/admin/doctors/index.html.twig', [
            'doctors'    => $doctorRepository->findAll(),
            'doctorForm' => $form->createView(),
        ]);
    }

    #[Route('/admin/doctors/{id}/edit', name: 'admin_edit_doctor', methods: ['GET', 'POST'])]
    public function editDoctor(
        Request $request,
        DoctorRepository $doctorRepository,
        UserPasswordHasherInterface $passwordHasher,
        int $id,
        EntityManagerInterface $em
    ): Response {
        $doctor = $doctorRepository->find($id);
        if (!$doctor) {
            throw $this->createNotFoundException('Doctorul nu a fost găsit.');
        }

        $form = $this->createForm(AdminDoctorFormType::class, $doctor);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $hashedPassword = $passwordHasher->hashPassword(
                    $doctor,
                    $plainPassword
                );
                $doctor->setPassword($hashedPassword);
            }

            $em->flush();
            $this->addFlash('success', 'Doctorul a fost actualizat cu succes.');
            return $this->redirectToRoute('admin_doctors');
        }

        return $this->render('pages/admin/doctors/edit.html.twig', [
            'doctorForm' => $form->createView(),
            'doctor'     => $doctor,
        ]);
    }

    #[Route('/admin/services', name: 'admin_services', methods: ['GET', 'POST'])]
    public function serviceList(
        Request $request,
        HospitalServiceRepository $hospitalServiceRepository,
        EntityManagerInterface $em
    ): Response {
        $form = $this->createForm(AdminServiceFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var HospitalService $service */
            $service = $form->getData();
            $em->persist($service);
            $em->flush();
            $this->addFlash('success', 'Serviciul a fost creat cu succes.');
            return $this->redirectToRoute('admin_services');
        }

        return $this->render('pages/admin/services/index.html.twig', [
            'services'    => $hospitalServiceRepository->findAll(),
            'serviceForm' => $form->createView(),
        ]);
    }

    #[Route('/admin/services/{id}/edit', name: 'admin_edit_service', methods: ['GET', 'POST'])]
    public function editService(
        Request $request,
        HospitalServiceRepository $hospitalServiceRepository,
        int $id,
        EntityManagerInterface $em
    ): Response {
        $service = $hospitalServiceRepository->find($id);
        if (!$service) {
            throw $this->createNotFoundException('Serviciul nu a fost găsit.');
        }
        $form = $this->createForm(AdminServiceFormType::class, $service);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Serviciul a fost actualizat cu succes.');
            return $this->redirectToRoute('admin_services');
        }

        return $this->render('pages/admin/services/edit.html.twig', [
            'serviceForm' => $form->createView(),
            'service'     => $service,
        ]);
    }

    #[Route('/admin/specialties', name: 'admin_specialties', methods: ['GET', 'POST'])]
    public function adminSpecialties(
        Request $request,
        MedicalSpecialtyRepository $medicalSpecialtyRepository,
        EntityManagerInterface $em
    ): Response {
        $form = $this->createForm(AdminSpecialtyFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var MedicalSpecialty $service */
            $specialty = $form->getData();
            $em->persist($specialty);
            $em->flush();
            $this->addFlash('success', 'Specialitatea a fost creat cu succes.');
            return $this->redirectToRoute('admin_specialties');
        }

        return $this->render('pages/admin/specialties/index.html.twig', [
            'specialties'    => $medicalSpecialtyRepository->findAll(),
            'specialtyForm' => $form->createView(),
        ]);
    }

    #[Route('/admin/specialties/{id}/edit', name: 'admin_edit_specialty', methods: ['GET', 'POST'])]
    public function editSpecialty(
        Request $request,
        MedicalSpecialtyRepository $medicalSpecialtyRepository,
        int $id,
        EntityManagerInterface $em
    ): Response {
        $specialty = $medicalSpecialtyRepository->find($id);
        if (!$specialty) {
            throw $this->createNotFoundException('Specialitatea nu a fost găsită.');
        }
        $form = $this->createForm(AdminSpecialtyFormType::class, $specialty);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Specialitatea a fost actualizat cu succes.');
            return $this->redirectToRoute('admin_specialties');
        }

        return $this->render('pages/admin/specialties/edit.html.twig', [
            'specialtyForm' => $form->createView(),
            'specialty'     => $specialty,
        ]);
    }

    #[Route('/admin/settings', name: 'admin_settings')]
    public function settings(
        Request $request,
        EntityManagerInterface $em,
        HospitalSettingsRepository $settingsRepo
    ): Response {
        $settingsEntity = $settingsRepo->findOneBy([]);
        if (!$settingsEntity) {
            $settingsEntity = new HospitalSettings();
            $em->persist($settingsEntity);
            $em->flush();
        }

        $form = $this->createForm(HospitalSettingsFormType::class, $settingsEntity);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Setările au fost actualizate cu succes.');
            return $this->redirectToRoute('admin_settings');
        }

        return $this->render('pages/admin/settings.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
