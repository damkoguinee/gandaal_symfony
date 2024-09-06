<?php

namespace App\Controller\Gandaal\AdminSite;

use App\Entity\Salaire;
use App\Form\SalaireType;
use App\Repository\SalaireRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/gandaal/admin/site/salaire')]
class SalaireController extends AbstractController
{
    #[Route('/', name: 'app_gandaal_admin_site_salaire_index', methods: ['GET'])]
    public function index(SalaireRepository $salaireRepository): Response
    {
        return $this->render('gandaal/admin_site/salaire/index.html.twig', [
            'salaires' => $salaireRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_gandaal_admin_site_salaire_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $salaire = new Salaire();
        $form = $this->createForm(SalaireType::class, $salaire);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($salaire);
            $entityManager->flush();

            return $this->redirectToRoute('app_gandaal_admin_site_salaire_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/admin_site/salaire/new.html.twig', [
            'salaire' => $salaire,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_gandaal_admin_site_salaire_show', methods: ['GET'])]
    public function show(Salaire $salaire): Response
    {
        return $this->render('gandaal/admin_site/salaire/show.html.twig', [
            'salaire' => $salaire,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_gandaal_admin_site_salaire_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Salaire $salaire, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(SalaireType::class, $salaire);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_gandaal_admin_site_salaire_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/admin_site/salaire/edit.html.twig', [
            'salaire' => $salaire,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_gandaal_admin_site_salaire_delete', methods: ['POST'])]
    public function delete(Request $request, Salaire $salaire, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$salaire->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($salaire);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_gandaal_admin_site_salaire_index', [], Response::HTTP_SEE_OTHER);
    }
}
