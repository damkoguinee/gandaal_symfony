<?php

namespace App\Controller\Gandaal\Administration\Scolarite;

use App\Entity\Filiation;
use App\Form\FiliationType;
use App\Repository\FiliationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/gandaal/administration/scolarite/filiation')]
class FiliationController extends AbstractController
{
    #[Route('/', name: 'app_gandaal_administration_scolarite_filiation_index', methods: ['GET'])]
    public function index(FiliationRepository $filiationRepository): Response
    {
        return $this->render('gandaal/administration/scolarite/filiation/index.html.twig', [
            'filiations' => $filiationRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_gandaal_administration_scolarite_filiation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $filiation = new Filiation();
        $form = $this->createForm(FiliationType::class, $filiation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($filiation);
            $entityManager->flush();

            return $this->redirectToRoute('app_gandaal_administration_scolarite_filiation_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/administration/scolarite/filiation/new.html.twig', [
            'filiation' => $filiation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_gandaal_administration_scolarite_filiation_show', methods: ['GET'])]
    public function show(Filiation $filiation): Response
    {
        return $this->render('gandaal/administration/scolarite/filiation/show.html.twig', [
            'filiation' => $filiation,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_gandaal_administration_scolarite_filiation_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Filiation $filiation, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(FiliationType::class, $filiation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_gandaal_administration_scolarite_filiation_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/administration/scolarite/filiation/edit.html.twig', [
            'filiation' => $filiation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_gandaal_administration_scolarite_filiation_delete', methods: ['POST'])]
    public function delete(Request $request, Filiation $filiation, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$filiation->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($filiation);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_gandaal_administration_scolarite_filiation_index', [], Response::HTTP_SEE_OTHER);
    }
}
