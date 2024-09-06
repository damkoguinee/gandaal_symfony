<?php

namespace App\Controller\Gandaal\Administration\Scolarite;

use App\Entity\Tuteur;
use App\Form\TuteurType;
use App\Repository\TuteurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/gandaal/administration/scolarite/tuteur')]
class TuteurController extends AbstractController
{
    #[Route('/', name: 'app_gandaal_administration_scolarite_tuteur_index', methods: ['GET'])]
    public function index(TuteurRepository $tuteurRepository): Response
    {
        return $this->render('gandaal/administration/scolarite/tuteur/index.html.twig', [
            'tuteurs' => $tuteurRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_gandaal_administration_scolarite_tuteur_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $tuteur = new Tuteur();
        $form = $this->createForm(TuteurType::class, $tuteur);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($tuteur);
            $entityManager->flush();

            return $this->redirectToRoute('app_gandaal_administration_scolarite_tuteur_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/administration/scolarite/tuteur/new.html.twig', [
            'tuteur' => $tuteur,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_gandaal_administration_scolarite_tuteur_show', methods: ['GET'])]
    public function show(Tuteur $tuteur): Response
    {
        return $this->render('gandaal/administration/scolarite/tuteur/show.html.twig', [
            'tuteur' => $tuteur,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_gandaal_administration_scolarite_tuteur_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Tuteur $tuteur, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(TuteurType::class, $tuteur);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_gandaal_administration_scolarite_tuteur_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/administration/scolarite/tuteur/edit.html.twig', [
            'tuteur' => $tuteur,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_gandaal_administration_scolarite_tuteur_delete', methods: ['POST'])]
    public function delete(Request $request, Tuteur $tuteur, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$tuteur->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($tuteur);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_gandaal_administration_scolarite_tuteur_index', [], Response::HTTP_SEE_OTHER);
    }
}
