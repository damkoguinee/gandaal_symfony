<?php

namespace App\Controller\Gandaal\Administration\Pedagogie;

use App\Entity\Matiere;
use App\Form\MatiereType;
use App\Entity\Etablissement;
use App\Repository\CategorieMatiereRepository;
use App\Repository\CursusRepository;
use App\Repository\MatiereRepository;
use App\Repository\FormationRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\NiveauClasseRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/gandaal/administration/pedagogie/admin/matiere')]
class MatiereController extends AbstractController
{
    #[Route('/{etablissement}', name: 'app_gandaal_administration_pedagogie_admin_matiere_index', methods: ['GET'])]
    public function index(MatiereRepository $matiereRep, CategorieMatiereRepository $categorieMatRep, Request $request, CursusRepository $cursusRep,  FormationRepository $formationRep, Etablissement $etablissement): Response
    {
        $search = $request->get("formation") ? $formationRep->find($request->get("formation")) : null;
        $cursus = $cursusRep->findBy(['etablissement' => $etablissement]);
        
        $formations = $request->get("formation") ? $formationRep->findBy(['id' => $request->get("formation")]) : $formationRep->findBy(['cursus' => $cursus]);
        // dd($formations);
        
        $matieres = $matiereRep->listeMatiereParFormations($formations);
        

        // Grouper les paiements par mode de paiement
        $matieresParFormation = [];
        foreach ($matieres as $matiere) {
            $formation = $matiere->getFormation()->getNom();
            if (!isset($matieresParFormation[$formation])) {
                $matieresParFormation[$formation] = [];
            }
            $matieresParFormation[$formation][] = $matiere;
        }
        return $this->render('gandaal/administration/pedagogie/admin/matiere/index.html.twig', [
            'matieresParFormation' => $matieresParFormation,
            'formations' => $formationRep->findAll(),
            'search' => $search,
            'etablissement' => $etablissement
        ]);
    }

    #[Route('/new/{etablissement}', name: 'app_gandaal_administration_pedagogie_admin_matiere_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, CursusRepository $cursusRep,  FormationRepository $formationRep, Etablissement $etablissement): Response
    {
        $matiere = new Matiere();
        $cursus = $cursusRep->findBy(['etablissement' => $etablissement]);
        
        $formations = $formationRep->findBy(['cursus' => $cursus]);
        $form = $this->createForm(MatiereType::class, $matiere, ['formations' => $formations]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($matiere);
            $entityManager->flush();

            return $this->redirectToRoute('app_gandaal_administration_pedagogie_admin_matiere_index', ['etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/administration/pedagogie/admin/matiere/new.html.twig', [
            'matiere' => $matiere,
            'form' => $form,
            'etablissement' => $etablissement
        ]);
    }

    #[Route('/show/{id}/{etablissement}', name: 'app_gandaal_administration_pedagogie_admin_matiere_show', methods: ['GET'])]
    public function show(Matiere $matiere, Etablissement $etablissement): Response
    {
        return $this->render('gandaal/administration/pedagogie/admin/matiere/show.html.twig', [
            'matiere' => $matiere,
            'etablissement' => $etablissement
        ]);
    }

    #[Route('/edit/{id}/{etablissement}', name: 'app_gandaal_administration_pedagogie_admin_matiere_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Matiere $matiere, CursusRepository $cursusRep,  FormationRepository $formationRep, EntityManagerInterface $entityManager, Etablissement $etablissement): Response
    {
        $cursus = $cursusRep->findBy(['etablissement' => $etablissement]);
        
        $formations = $formationRep->findBy(['cursus' => $cursus]);
        $form = $this->createForm(MatiereType::class, $matiere, ['formations' => $formations]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_gandaal_administration_pedagogie_admin_matiere_index', ['etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/administration/pedagogie/admin/matiere/edit.html.twig', [
            'matiere' => $matiere,
            'form' => $form,
            'etablissement' => $etablissement
        ]);
    }

    #[Route('/delete/{id}', name: 'app_gandaal_administration_pedagogie_admin_matiere_delete', methods: ['POST'])]
    public function delete(Request $request, Matiere $matiere, EntityManagerInterface $entityManager, Etablissement $etablissement): Response
    {
        if ($this->isCsrfTokenValid('delete'.$matiere->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($matiere);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_gandaal_administration_pedagogie_admin_matiere_index', ['etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
    }
}
