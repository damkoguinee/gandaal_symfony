<?php

namespace App\Controller\Gandaal\Administration\Comptabilite\Admin;

use App\Entity\Etablissement;
use App\Entity\TarifActiviteScolaire;
use App\Form\TarifActiviteScolaireType;
use App\Repository\ConfigActiviteScolaireRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\TarifActiviteScolaireRepository;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/gandaal/administration/comptabilite/admin')]
class TarifActiviteScolaireController extends AbstractController
{
    #[Route('/{etablissement}', name: 'app_gandaal_administration_comptabilite_admin_activite_tarif_index', methods: ['GET'])]
    public function index(Etablissement $etablissement, ConfigActiviteScolaireRepository $activiteRep, SessionInterface $session, TarifActiviteScolaireRepository $tarifActiviteScolaireRep): Response
    {
        $tarifs = $tarifActiviteScolaireRep->tarifActiviteParEtablissementParPromo($etablissement, $session->get('promo'));

        $activites = $activiteRep->findBy(['etablissement' => $etablissement], ['nom' => 'ASC']);

        return $this->render('gandaal/administration/comptabilite/admin/activite/index.html.twig', [
            'tarifs' => $tarifs,
            'etablissement' => $etablissement,
            'activites' => $activites,
        ]);
    }

    #[Route('/new/tarif/{etablissement}', name: 'app_gandaal_administration_comptabilite_admin_activite_tarif_new', methods: ['GET', 'POST'])]
    public function newTarif(Request $request, TarifActiviteScolaireRepository $tarifActiviteScolaireRep, SessionInterface $session, Etablissement $etablissement, EntityManagerInterface $entityManager): Response
    {
        $tarifActivite = new TarifActiviteScolaire();
        $form = $this->createForm(TarifActiviteScolaireType::class, $tarifActivite, ['etablissement' => $etablissement]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $verif = $tarifActiviteScolaireRep->findOneBy(['activite' => $tarifActivite->getActivite(), 'promo' => $session->get('promo')]);
            if ($verif) {
                $this->addFlash('warning', 'Cette activité existe déjà');
            }else{
                $montant = floatval(preg_replace('/[^0-9,.]/', '', $form->get('montant')->getData()));
                $tarifActivite->setPromo($session->get('promo'))
                        ->setMontant($montant);
                $entityManager->persist($tarifActivite);
                $entityManager->flush();
                // $referer = $request->get('referer');
                // return $this->redirect($referer);
                $this->addFlash('success', 'Activité ajoutée avec succès :)');
                return $this->redirectToRoute('app_gandaal_administration_comptabilite_admin_activite_tarif_index', ['etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render('gandaal/administration/comptabilite/admin/activite/new.html.twig', [
            'tarif_activite' => $tarifActivite,
            'form' => $form,
            'etablissement' => $etablissement,
            'referer' => $request->headers->get('referer')
        ]);
    }

    

    #[Route('/show/{etablissement}/{id}', name: 'app_gandaal_administration_comptabilite_admin_activite_tarif_show', methods: ['GET'])]
    public function show(TarifActiviteScolaire $tarifActivite, Etablissement $etablissement): Response
    {
        return $this->render('gandaal/administration/comptabilite/admin/activite/show.html.twig', [
            'tarif' => $tarifActivite,
            'etablissement' => $etablissement,
        ]);
    }

    #[Route('/{etablissement}/{id}/edit', name: 'app_gandaal_administration_comptabilite_admin_activite_tarif_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Etablissement $etablissement, TarifActiviteScolaire $tarifActivite, TarifActiviteScolaireRepository $tarifActiviteScolaireRep, SessionInterface $session, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(TarifActiviteScolaireType::class, $tarifActivite, ['etablissement' => $etablissement]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($tarifActivite);
            $entityManager->flush();
            $this->addFlash('success', 'Activité modifiée avec succès :)');
            return $this->redirectToRoute('app_gandaal_administration_comptabilite_admin_activite_tarif_index', ['etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
            
        }

        return $this->render('gandaal/administration/comptabilite/admin/activite/edit.html.twig', [
            'tarif' => $tarifActivite,
            'form' => $form,
            'etablissement' => $etablissement,
        ]);
    }

    #[Route('/{etablissement}/{id}', name: 'app_gandaal_administration_comptabilite_admin_activite_tarif_delete', methods: ['POST'])]
    public function delete(Request $request, Etablissement $etablissement, TarifActiviteScolaire $tarifActivite, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$tarifActivite->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($tarifActivite);
            $entityManager->flush();
        }

        $this->addFlash('success', 'Activité supprimée avec succès :)');
        return $this->redirectToRoute('app_gandaal_administration_comptabilite_admin_activite_tarif_index', ['etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
    }
}
