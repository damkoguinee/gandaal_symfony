<?php

namespace App\Controller\Gandaal\Administration\Scolarite;

use App\Entity\LienFamilial;
use App\Form\LienFamilialType;
use App\Repository\LienFamilialRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/gandaal/administration/scolarite/lien/familial')]
class LienFamilialController extends AbstractController
{
    #[Route('/', name: 'app_gandaal_administration_scolarite_lien_familial_index', methods: ['GET'])]
    public function index(LienFamilialRepository $lienFamilialRepository): Response
    {
        return $this->render('gandaal/administration/scolarite/lien_familial/index.html.twig', [
            'lien_familials' => $lienFamilialRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_gandaal_administration_scolarite_lien_familial_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $lienFamilial = new LienFamilial();
        $form = $this->createForm(LienFamilialType::class, $lienFamilial);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($lienFamilial);
            $entityManager->flush();

            return $this->redirectToRoute('app_gandaal_administration_scolarite_lien_familial_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/administration/scolarite/lien_familial/new.html.twig', [
            'lien_familial' => $lienFamilial,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_gandaal_administration_scolarite_lien_familial_show', methods: ['GET'])]
    public function show(LienFamilial $lienFamilial): Response
    {
        return $this->render('gandaal/administration/scolarite/lien_familial/show.html.twig', [
            'lien_familial' => $lienFamilial,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_gandaal_administration_scolarite_lien_familial_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, LienFamilial $lienFamilial, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(LienFamilialType::class, $lienFamilial);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_gandaal_administration_scolarite_lien_familial_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/administration/scolarite/lien_familial/edit.html.twig', [
            'lien_familial' => $lienFamilial,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_gandaal_administration_scolarite_lien_familial_delete', methods: ['POST'])]
    public function delete(Request $request, LienFamilial $lienFamilial, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$lienFamilial->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($lienFamilial);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_gandaal_administration_scolarite_lien_familial_index', [], Response::HTTP_SEE_OTHER);
    }
}
