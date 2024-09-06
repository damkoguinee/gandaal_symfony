<?php

namespace App\Controller\Gandaal\Admin;

use App\Entity\NiveauClasse;
use App\Form\NiveauClasseType;
use App\Repository\NiveauClasseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/gandaal/admin/niveau/classe')]
class NiveauClasseController extends AbstractController
{
    #[Route('/', name: 'app_gandaal_admin_niveau_classe_index', methods: ['GET'])]
    public function index(NiveauClasseRepository $niveauClasseRepository): Response
    {
        return $this->render('gandaal/admin/niveau_classe/index.html.twig', [
            'niveau_classes' => $niveauClasseRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_gandaal_admin_niveau_classe_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $niveauClasse = new NiveauClasse();
        $form = $this->createForm(NiveauClasseType::class, $niveauClasse);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($niveauClasse);
            $entityManager->flush();
            $this->addFlash("success", "Niveau enregistré avec succès :)");

            return $this->redirectToRoute('app_gandaal_admin_niveau_classe_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/admin/niveau_classe/new.html.twig', [
            'niveau_classe' => $niveauClasse,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_gandaal_admin_niveau_classe_show', methods: ['GET'])]
    public function show(NiveauClasse $niveauClasse): Response
    {
        return $this->render('gandaal/admin/niveau_classe/show.html.twig', [
            'niveau_classe' => $niveauClasse,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_gandaal_admin_niveau_classe_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, NiveauClasse $niveauClasse, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(NiveauClasseType::class, $niveauClasse);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash("success", "Niveau modifié avec succès :)");
            return $this->redirectToRoute('app_gandaal_admin_niveau_classe_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/admin/niveau_classe/edit.html.twig', [
            'niveau_classe' => $niveauClasse,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_gandaal_admin_niveau_classe_delete', methods: ['POST'])]
    public function delete(Request $request, NiveauClasse $niveauClasse, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$niveauClasse->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($niveauClasse);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_gandaal_admin_niveau_classe_index', [], Response::HTTP_SEE_OTHER);
    }
}
