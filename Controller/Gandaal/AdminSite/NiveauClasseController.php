<?php

namespace App\Controller\Gandaal\AdminSite;

use App\Entity\Etablissement;
use App\Entity\NiveauClasse;
use App\Form\NiveauClasseType;
use App\Repository\CursusRepository;
use App\Repository\NiveauClasseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/gandaal/admin/site/niveau/classe')]
class NiveauClasseController extends AbstractController
{
    #[Route('/{etablissement}', name: 'app_gandaal_admin_site_niveau_classe_index', methods: ['GET'])]
    public function index(Etablissement $etablissement, NiveauClasseRepository $niveauClasseRepository, CursusRepository $cursusRep): Response
    {
        $cursus = $cursusRep->findBy(['etablissement' => $etablissement]);
        $niveaux = $niveauClasseRepository->findBy(['cursus' => $cursus]);
        return $this->render('gandaal/admin_site/niveau_classe/index.html.twig', [
            'niveau_classes' => $niveaux,
            'etablissement' => $etablissement,
        ]);
    }

    #[Route('/new/{etablissement}', name: 'app_gandaal_admin_site_niveau_classe_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, Etablissement $etablissement): Response
    {
        $niveauClasse = new NiveauClasse();
        $form = $this->createForm(NiveauClasseType::class, $niveauClasse, ['etablissement' => $etablissement]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($niveauClasse);
            $entityManager->flush();
            $this->addFlash("success", "Niveau enregistré avec succès :)");

            return $this->redirectToRoute('app_gandaal_admin_site_niveau_classe_index', ['etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/admin_site/niveau_classe/new.html.twig', [
            'niveau_classe' => $niveauClasse,
            'form' => $form,
            'etablissement' => $etablissement,
        ]);
    }

    #[Route('/show/{id}/{etablissement}', name: 'app_gandaal_admin_site_niveau_classe_show', methods: ['GET'])]
    public function show(NiveauClasse $niveauClasse, Etablissement $etablissement): Response
    {
        return $this->render('gandaal/admin_site/niveau_classe/show.html.twig', [
            'niveau_classe' => $niveauClasse,
            'etablissement' => $etablissement,
        ]);
    }

    #[Route('/edit/{id}/{etablissement}', name: 'app_gandaal_admin_site_niveau_classe_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, NiveauClasse $niveauClasse, EntityManagerInterface $entityManager, Etablissement $etablissement): Response
    {
        $form = $this->createForm(NiveauClasseType::class, $niveauClasse, ['etablissement' => $etablissement]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash("success", "Niveau modifié avec succès :)");
            return $this->redirectToRoute('app_gandaal_admin_site_niveau_classe_index', ['etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/admin_site/niveau_classe/edit.html.twig', [
            'niveau_classe' => $niveauClasse,
            'form' => $form,
            'etablissement' => $etablissement,
        ]);
    }

    #[Route('/delete/{id}/{etablissement}', name: 'app_gandaal_admin_site_niveau_classe_delete', methods: ['POST'])]
    public function delete(Request $request, NiveauClasse $niveauClasse, EntityManagerInterface $entityManager, Etablissement $etablissement): Response
    {
        if ($this->isCsrfTokenValid('delete'.$niveauClasse->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($niveauClasse);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_gandaal_admin_site_niveau_classe_index', ['etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
    }
}
