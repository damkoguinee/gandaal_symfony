<?php

namespace App\Controller\Gandaal\Administration\Comptabilite\Admin;

use App\Entity\Etablissement;
use App\Entity\Formation;
use App\Entity\FraisScolarite;
use App\Form\FraisScolariteType;
use App\Repository\CursusRepository;
use App\Repository\FormationRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\NiveauClasseRepository;
use App\Repository\FraisScolariteRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

#[Route('/gandaal/administration/comptabilite/admin/frais/scolarite')]
class FraisScolariteController extends AbstractController
{
    #[Route('/{etablissement}', name: 'app_gandaal_administration_comptabilite_admin_frais_scolarite_index', methods: ['GET'])]
    public function index(FraisScolariteRepository $fraisScolariteRep, CursusRepository $cursusRep,  SessionInterface $session, FormationRepository $formationRep, Etablissement $etablissement): Response
    {
        $frais_scolarites = $fraisScolariteRep->fraisScolariteGroupeParFormation($etablissement, $session->get('promo'));

        $fraisParFormation = [];

        foreach ($frais_scolarites as $frais) {
            $formationNom = $frais->getFormation()->getNom();

            if (!isset($fraisParFormation[$formationNom])) {
                $fraisParFormation[$formationNom] = [];
            }

            $fraisParFormation[$formationNom][] = $frais;
        }

        return $this->render('gandaal/administration/comptabilite/admin/frais_scolarite/index.html.twig', [
            'fraisParFormation' => $fraisParFormation,
            'etablissement' => $etablissement,
        ]);
    }

    #[Route('/new/{etablissement}', name: 'app_gandaal_administration_comptabilite_admin_frais_scolarite_new', methods: ['GET', 'POST'])]
    public function new(Request $request, CursusRepository $cursusRep,  FormationRepository $formationRep, EntityManagerInterface $entityManager, SessionInterface $session, Etablissement $etablissement): Response
    {
        $fraisScolarite = new FraisScolarite();
        $cursus = $cursusRep->findBy(['etablissement' => $etablissement]);
        
        $formations = $formationRep->findBy(['cursus' => $cursus]);
        $form = $this->createForm(FraisScolariteType::class, $fraisScolarite, ['etablissement' => $etablissement, 'promo' => $session->get('promo')]);
        $form->handleRequest($request);        
        $promo = $session->get('promo');
        if ($form->isSubmitted() && $form->isValid()) {
           $formations = $request->get('formations');
           foreach ($formations as $value) {
                $formation = $formationRep->find($value);
                $fraisScolarite = new FraisScolarite();
                $fraisScolarite->setFormation($formation)
                            ->setPromo($promo)
                            ->setTranche($form->getViewData()->getTranche())
                            ->setMontant($form->getViewData()->getMontant())
                            ->setEtablissement($etablissement)
                            ->setDateLimite($form->getViewData()->getDateLimite());
                $entityManager->persist($fraisScolarite);
           }
            $entityManager->flush();

            $this->addFlash("success", "Frais ajouté avec succès :)");

            return $this->redirectToRoute('app_gandaal_administration_comptabilite_admin_frais_scolarite_index', ['etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/administration/comptabilite/admin/frais_scolarite/new.html.twig', [
            'frais_scolarite' => $fraisScolarite,
            'form' => $form,
            'formations' => $formations,
            'etablissement' => $etablissement,
        ]);
    }

    #[Route('/show/{id}/{etablissement}', name: 'app_gandaal_administration_comptabilite_admin_frais_scolarite_show', methods: ['GET'])]
    public function show(FraisScolarite $fraisScolarite, Etablissement $etablissement): Response
    {
        return $this->render('gandaal/administration/comptabilite/admin/frais_scolarite/show.html.twig', [
            'frais_scolarite' => $fraisScolarite,
            'etablissement' => $etablissement,
        ]);
    }

    #[Route('/edit/{id}/{etablissement}', name: 'app_gandaal_administration_comptabilite_admin_frais_scolarite_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, FraisScolarite $fraisScolarite, CursusRepository $cursusRep,  FormationRepository $formationRep, SessionInterface $session, EntityManagerInterface $entityManager, Etablissement $etablissement): Response
    {
        $cursus = $cursusRep->findBy(['etablissement' => $etablissement]);
        
        $formations = $formationRep->findBy(['cursus' => $cursus]);
        $form = $this->createForm(FraisScolariteType::class, $fraisScolarite, ['etablissement' => $etablissement, 'promo' => $session->get('promo')]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_gandaal_administration_comptabilite_admin_frais_scolarite_index', ['etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
        }

        $cursus = $cursusRep->findBy(['etablissement' => $etablissement]);
        
        $formations = $formationRep->findBy(['cursus' => $cursus]);

        return $this->render('gandaal/administration/comptabilite/admin/frais_scolarite/edit.html.twig', [
            'frais_scolarite' => $fraisScolarite,
            'form' => $form,
            'etablissement' => $etablissement,
            'formations' => $formations,
        ]);
    }

    #[Route('/delete/{id}/{etablissement}', name: 'app_gandaal_administration_comptabilite_admin_frais_scolarite_delete', methods: ['POST'])]
    public function delete(Request $request, Etablissement $etablissement, FraisScolarite $fraisScolarite, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$fraisScolarite->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($fraisScolarite);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_gandaal_administration_comptabilite_admin_frais_scolarite_index', ['etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
    }
}
