<?php

namespace App\Controller\Gandaal\AdminSite;

use App\Entity\CategorieMatiere;
use App\Entity\Etablissement;
use App\Form\CategorieMatiereType;
use App\Repository\CategorieMatiereRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/gandaal/admin/site/categorie/matiere')]
class CategorieMatiereController extends AbstractController
{
    #[Route('/', name: 'app_gandaal_admin_site_categorie_matiere_index', methods: ['GET'])]
    public function index(CategorieMatiereRepository $categorieMatiereRepository): Response
    {
        return $this->render('gandaal/admin_site/categorie_matiere/index.html.twig', [
            'categorie_matieres' => $categorieMatiereRepository->findAll(),
        ]);
    }

    #[Route('/new/{etablissement}', name: 'app_gandaal_admin_site_categorie_matiere_new', methods: ['GET', 'POST'])]
    public function new(Request $request, Etablissement $etablissement, EntityManagerInterface $entityManager): Response
    {
        $categorieMatiere = new CategorieMatiere();
        $form = $this->createForm(CategorieMatiereType::class, $categorieMatiere);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($categorieMatiere);
            $entityManager->flush();

            return $this->redirectToRoute('app_gandaal_administration_pedagogie_admin_matiere_new', ['etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/admin_site/categorie_matiere/new.html.twig', [
            'categorie_matiere' => $categorieMatiere,
            'form' => $form,
            'etablissement' => $etablissement
        ]);
    }

    #[Route('/{id}', name: 'app_gandaal_admin_site_categorie_matiere_show', methods: ['GET'])]
    public function show(CategorieMatiere $categorieMatiere): Response
    {
        return $this->render('gandaal/admin_site/categorie_matiere/show.html.twig', [
            'categorie_matiere' => $categorieMatiere,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_gandaal_admin_site_categorie_matiere_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, CategorieMatiere $categorieMatiere, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(CategorieMatiereType::class, $categorieMatiere);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_gandaal_admin_site_categorie_matiere_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/admin_site/categorie_matiere/edit.html.twig', [
            'categorie_matiere' => $categorieMatiere,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_gandaal_admin_site_categorie_matiere_delete', methods: ['POST'])]
    public function delete(Request $request, CategorieMatiere $categorieMatiere, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$categorieMatiere->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($categorieMatiere);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_gandaal_admin_site_categorie_matiere_index', [], Response::HTTP_SEE_OTHER);
    }
}
