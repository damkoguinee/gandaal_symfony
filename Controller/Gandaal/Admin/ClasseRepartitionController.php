<?php

namespace App\Controller\Gandaal\Admin;

use App\Entity\ClasseRepartition;
use App\Form\ClasseRepartitionType;
use App\Repository\ClasseRepartitionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/gandaal/admin/classe/repartition')]
class ClasseRepartitionController extends AbstractController
{
    #[Route('/', name: 'app_gandaal_admin_classe_repartition_index', methods: ['GET'])]
    public function index(ClasseRepartitionRepository $classeRepartitionRepository): Response
    {
        return $this->render('gandaal/admin/classe_repartition/index.html.twig', [
            'classe_repartitions' => $classeRepartitionRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_gandaal_admin_classe_repartition_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $classeRepartition = new ClasseRepartition();
        $form = $this->createForm(ClasseRepartitionType::class, $classeRepartition, [
            'year_choices' => $this->getYearChoices()
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($classeRepartition);
            $entityManager->flush();

            return $this->redirectToRoute('app_gandaal_admin_classe_repartition_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/admin/classe_repartition/new.html.twig', [
            'classe_repartition' => $classeRepartition,
            'form' => $form,
        ]);
    }

    private function getYearChoices(): array
    {
        $currentYear = (int) date('Y');
        $startYear = 2023;
        $years = [];

        for ($year = $startYear; $year <= $currentYear + 1; $year++) {
            $years["$year - " . ($year + 1)] = $year+1;
        }

        return $years;
    }

    #[Route('/{id}', name: 'app_gandaal_admin_classe_repartition_show', methods: ['GET'])]
    public function show(ClasseRepartition $classeRepartition): Response
    {
        return $this->render('gandaal/admin/classe_repartition/show.html.twig', [
            'classe_repartition' => $classeRepartition,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_gandaal_admin_classe_repartition_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, ClasseRepartition $classeRepartition, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ClasseRepartitionType::class, $classeRepartition, [
            'year_choices' => $this->getYearChoices()
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_gandaal_admin_classe_repartition_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/admin/classe_repartition/edit.html.twig', [
            'classe_repartition' => $classeRepartition,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_gandaal_admin_classe_repartition_delete', methods: ['POST'])]
    public function delete(Request $request, ClasseRepartition $classeRepartition, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$classeRepartition->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($classeRepartition);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_gandaal_admin_classe_repartition_index', [], Response::HTTP_SEE_OTHER);
    }
}
