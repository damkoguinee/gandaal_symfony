<?php

namespace App\Controller\Gandaal\AdminSite;

use App\Entity\Formation;
use App\Form\FormationType;
use App\Entity\Etablissement;
use App\Repository\CursusRepository;
use App\Repository\FormationRepository;
use App\Repository\NiveauClasseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/gandaal/admin/site/formation')]
class FormationController extends AbstractController
{
    #[Route('/{etablissement}', name: 'app_gandaal_admin_site_formation_index', methods: ['GET'])]
    public function index(FormationRepository $formationRepository, CursusRepository $cursusRep, Etablissement $etablissement): Response
    {
        $cursus = $cursusRep->findBy(['etablissement' => $etablissement]);
        $formations = $formationRepository->findBy(['cursus' => $cursus]);
        return $this->render('gandaal/admin_site/formation/index.html.twig', [
            'formations' => $formations,
            'etablissement' => $etablissement,
        ]);
    }

    #[Route('/new/{etablissement}', name: 'app_gandaal_admin_site_formation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, CursusRepository $cursusRep, EntityManagerInterface $entityManager, Etablissement $etablissement): Response
    {
        $cursus = $cursusRep->findBy(['etablissement' => $etablissement]);
        $formation = new Formation();
        $form = $this->createForm(FormationType::class, $formation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($formation);
            $entityManager->flush();

            return $this->redirectToRoute('app_gandaal_admin_site_formation_index', ['etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/admin_site/formation/new.html.twig', [
            'formation' => $formation,
            'form' => $form,
            'etablissement' => $etablissement,
        ]);
    }

    #[Route('/show/{id}{etablissement}', name: 'app_gandaal_admin_site_formation_show', methods: ['GET'])]
    public function show(Formation $formation, Etablissement $etablissement): Response
    {
        return $this->render('gandaal/admin_site/formation/show.html.twig', [
            'formation' => $formation,
            'etablissement' => $etablissement,
        ]);
    }

    #[Route('/edit/{id}/{etablissement}', name: 'app_gandaal_admin_site_formation_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Formation $formation, EntityManagerInterface $entityManager, Etablissement $etablissement): Response
    {
        $form = $this->createForm(FormationType::class, $formation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_gandaal_admin_site_formation_index', ['etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/admin_site/formation/edit.html.twig', [
            'formation' => $formation,
            'form' => $form,
            'etablissement' => $etablissement,
        ]);
    }

    #[Route('/delete/{id}', name: 'app_gandaal_admin_site_formation_delete', methods: ['POST'])]
    public function delete(Request $request, Formation $formation, EntityManagerInterface $entityManager, Etablissement $etablissement): Response
    {
        if ($this->isCsrfTokenValid('delete'.$formation->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($formation);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_gandaal_admin_site_formation_index', ['etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
    }
}
