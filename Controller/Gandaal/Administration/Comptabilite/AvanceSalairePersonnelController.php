<?php

namespace App\Controller\Gandaal\Administration\Comptabilite;

use App\Entity\Etablissement;
use App\Repository\UserRepository;
use App\Entity\HistoriqueSuppression;
use App\Entity\AvanceSalairePersonnel;
use App\Form\AvanceSalairePersonnelType;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\ConfigCaisseRepository;
use App\Repository\ConfigDeviseRepository;
use App\Repository\PersonnelActifRepository;
use App\Repository\MouvementCaisseRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\ConfigModePaiementRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Repository\ConfigCompteOperationRepository;
use App\Repository\AvanceSalairePersonnelRepository;
use App\Repository\MouvementCollaborateurRepository;
use App\Repository\ConfigCategorieOperationRepository;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/gandaal/administration/comptabilite/avance/personnel')]
class AvanceSalairePersonnelController extends AbstractController
{
    #[Route('/accueil/{etablissement}', name: 'app_gandaal_administration_comptabilite_avance_personnel_index', methods: ['GET'])]
    public function index(AvanceSalairePersonnelRepository $avanceRep, SessionInterface $session, PersonnelActifRepository $personnelActifRep, Request $request, etablissement $etablissement): Response
    {
        if ($request->get("id_user_search")){
            $search = $request->get("id_user_search");
        }else{
            $search = "";
        }

        // if ($request->get("activite")){
        //     $activite = $activiteRep->find($request->get("activite"));
        // }else{
        //     $activite = "";
        // }

        $firstOp = $avanceRep->findOneBy(['promo' => $session->get('promo')], ['dateOperation' => 'ASC']);
        $date1 = $request->get("date1") ? $request->get("date1") : ($firstOp ? $firstOp->getDateOperation()->format('Y-m-d') : $request->get("date1"));
        $date2 = $request->get("date2") ? $request->get("date2") : date("Y-m-d");  

        // if ($request->isXmlHttpRequest()) {
        //     $search = $request->query->get('search');
        //     $eleves = $eleveRep->rechercheEleveParEtablissement($search, $etablissement);    
        //     $response = [];
        //     foreach ($eleves as $eleve) {
        //         $response[] = [
        //             'nom' => ucwords($eleve->getPrenom())." ".strtoupper($eleve->getNom()),
        //             'id' => $eleve->getId()
        //         ]; 
        //     }
        //     return new JsonResponse($response);
        // }

        $pageEncours = $request->get('pageEncours', 1);
        // if ($request->get("id_user_search")){
        //     $paiements_activite = $avanceRep->findPaiementActiviteByEtablissementBySearchPaginated($etablissement, $search, $session->get('promo'), $date1, $date2, $pageEncours, 25);
        // }elseif ($request->get("activite")){
        //     $paiements_activite = $avanceRep->findPaiementActiviteByEtablissementByActivitePaginated($etablissement, $activite, $session->get('promo'), $date1, $date2, $pageEncours, 25);
        // }else{
        // }

        $avances = $avanceRep->listeDesAvancesParEtablissementPaginated($etablissement, $session->get('promo'), $date1, $date2, $pageEncours, 50);

        return $this->render('gandaal/administration/comptabilite/avance_personnel/index.html.twig', [
            'avances' => $avances,
            'search' => $search,            
            'etablissement' => $etablissement,
            'date1' => $date1,
            'date2' => $date2,
        ]);
    }

    #[Route('/new/{etablissement}', name: 'app_gandaal_administration_comptabilite_avance_personnel_new', methods: ['GET', 'POST'])]
    public function new(Request $request, Etablissement $etablissement, AvanceSalairePersonnelRepository $avanceSalairePersonnelRep, SessionInterface $session, PersonnelActifRepository $personnelActifRep,  UserRepository $userRep, ConfigCaisseRepository $caisseRep, ConfigModePaiementRepository $modePaieRep, ConfigDeviseRepository $deviseRep, ConfigCompteOperationRepository $compteOpRep, ConfigCategorieOperationRepository $categorieOpRep, MouvementCaisseRepository $mouvementRep, EntityManagerInterface $entityManager): Response
    {
        if ($request->get("id_user_search")){
            $search = $userRep->find($request->get("id_user_search"));  
        }else{
            $search = "";
        }
        if ($request->isXmlHttpRequest()) {
            $search = $request->query->get('search');
            $personnels = $personnelActifRep->rechercheUserParEtablissement($search, $etablissement, $session->get('promo'));    
            $response = [];
            foreach ($personnels as $personnel) {
                $response[] = [
                    'nom' => ucwords($personnel->getPersonnel()->getPrenom())." ".strtoupper($personnel->getPersonnel()->getNom()),
                    'id' => $personnel->getPersonnel()->getId()
                ]; 
            }
            return new JsonResponse($response);
        }

        $personnel = $personnelActifRep->findOneBy(['personnel' => $request->get("id_user_search")]);  
        if ($request->get('avance_personnel')) {
            $caisse = $caisseRep->find($request->get('caisse'));
            $devise = $deviseRep->find($request->get('devise'));
            $modePaie = $modePaieRep->find($request->get('modePaie'));
            $montantSaisie = floatval(preg_replace('/[^0-9,.]/', '', $request->get('montant')));
            $datePaiement = new \DateTime($request->get('datePaiement'));
            $banquePaie = $request->get('banquePaie');
            $numeroPaie = $request->get('numeroPaie');
            $taux = $request->get('taux');

            $currentYear = (new \DateTime())->format('ymd');
            $maxPaie = $avanceSalairePersonnelRep->findMaxId($etablissement, $session->get('promo'));
            $formattedMaxPaie = sprintf('%04d', $maxPaie + 1); 
            $generatedReference = $currentYear . $formattedMaxPaie;
            $reference = 'ava'.$generatedReference;

            $periodes = $request->get('periodes');

           
            $solde_caisse = $mouvementRep->findSoldeCaisse($caisse, $devise);
            if ($solde_caisse >= $montantSaisie) {
                foreach ($periodes as $item) { 
                    $periode = date("Y") . '-' . $item  . '-01';

                    $avance = new AvanceSalairePersonnel();
                    $avance->setPeriode($periode)
                        ->setPersonnelActif($personnel)
                        ->setCaisse($caisse)
                        ->setCategorieOperation($categorieOpRep->find(1))
                        ->setCompteOperation($compteOpRep->find(6))
                        ->setDevise($devise)
                        ->setModePaie($modePaie)
                        ->setTaux($taux)
                        ->setMontant(-$montantSaisie)
                        ->setNumeroPaie($numeroPaie)
                        ->setBanquePaie($banquePaie)
                        ->setTypeMouvement('avance')
                        ->setEtatOperation('clos')
                        ->setDateOperation($datePaiement ? $datePaiement : new \DateTime("now"))
                        ->setDateSaisie(new \DateTime("now"))
                        ->setSaisiePar($this->getUser())
                        ->setEtablissement($etablissement)
                        ->setPromo($session->get('promo'))
                        ->setReference($reference);
                    $entityManager->persist($avance);                    
                }
            }else{
                $this->addFlash("warning", "Le montant disponible en caisse est insuffisant");
                // Récupérer l'URL de la page précédente
                $referer = $request->headers->get('referer');
                return $this->redirect($referer);
            }
            $entityManager->flush();
            $this->addFlash("success", "Avance enregistrée avec succés :) ");
            $referer = $request->headers->get('referer');
            return $this->redirect($referer);

        }

        $caisses = $caisseRep->findBy(['etablissement' => $etablissement]);
        $devises = $deviseRep->findAll();
        $modePaies = $modePaieRep->findAll();

        $avances = $avanceSalairePersonnelRep->findBy(['personnelActif' => $personnel, 'etablissement' => $etablissement, 'promo' => $session->get('promo')]);

        // Regrouper les avances par période
        $groupedByPeriode = [];

        foreach ($avances as $avance) {
            $periode = $avance->getPeriode()->format('Y-m-d'); // Utiliser le getter approprié pour récupérer la période

            if (!isset($groupedByPeriode[$periode])) {
                $groupedByPeriode[$periode] = [];
            }

            $groupedByPeriode[$periode][] = $avance;
        }

        return $this->render('gandaal/administration/comptabilite/avance_personnel/new.html.twig', [
            'personnelActif' => $personnel,
            'etablissement' => $etablissement,
            'search' => $search,
            'dernier_promo' => $session->get('promo'),
            'avances' => $groupedByPeriode,
            'caisses' => $caisses,
            'devises' => $devises,
            'modePaies' => $modePaies,
            // 'periode_select' => $periode_select,
        ]);
    }

    #[Route('/show/{id}/{etablissement}', name: 'app_gandaal_administration_comptabilite_avance_personnel_show', methods: ['GET'])]
    public function show(AvanceSalairePersonnel $paiement, Etablissement $etablissement, AvanceSalairePersonnelRepository $avanceRep): Response
    {
        $paiement_lies = $avanceRep->avanceLies($paiement->getReference(), $paiement);
        return $this->render('gandaal/administration/comptabilite/avance_personnel/show.html.twig', [
            'avance_personnel' => $paiement,
            'etablissement' => $etablissement,
            'paiement_lies' => $paiement_lies,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_gandaal_administration_comptabilite_avance_personnel_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, AvanceSalairePersonnel $avanceSalairePersonnel, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(AvanceSalairePersonnelType::class, $avanceSalairePersonnel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_gandaal_administration_comptabilite_avance_personnel_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/administration/comptabilite/avance_personnel/edit.html.twig', [
            'avance_personnel' => $avanceSalairePersonnel,
            'form' => $form,
        ]);
    }

    #[Route('/confirm/delete/{id}/{etablissement}', name: 'app_gandaal_administration_comptabilite_avance_personnel_confirm_delete', methods: ['GET', 'POST'])]
    public function confirmDelete(AvanceSalairePersonnel $avance, Request $request, Etablissement $etablissement): Response
    {
        $param = $request->request->get('param'); // Récupération du paramètre

        if ($param === 'simple') {
            // Code spécifique pour le paramètre "simple"
            $route_suppression = $this->generateUrl('app_gandaal_administration_comptabilite_avance_personnel_delete', [
                'id' => $avance->getId(),
                'etablissement' => $etablissement->getId()
            ]);
        }elseif ($param === 'general') {
            // Code pour d'autres valeurs de paramètre ou défaut
            $route_suppression = $this->generateUrl('app_gandaal_administration_comptabilite_avance_personnel_delete_liaison', [
                'id' => $avance->getId(),
                'etablissement' => $etablissement->getId()
            ]);
        }

        return $this->render('gandaal/administration/comptabilite/avance_personnel/confirm_delete.html.twig', [
            'paiement' => $avance,
            'etablissement' => $etablissement,
            'route_suppression' => $route_suppression,
            'param' => $param,
        ]);
    }

    #[Route('/delete/{id}/{etablissement}', name: 'app_gandaal_administration_comptabilite_avance_personnel_delete', methods: ['POST'])]
    public function delete(Request $request, SessionInterface $session, AvanceSalairePersonnel $avance, Etablissement $etablissement, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$avance->getId(), $request->getPayload()->getString('_token'))) {
            $deleteReason = $request->request->get('delete_reason');
            $information = 'ref '.$avance->getReference().' '.number_format($avance->getMontant(),0,',',' ');
            $historique = new HistoriqueSuppression();
            $historique->setType('paiement avance') // ou un type plus spécifique
                ->setMotif($deleteReason)
                ->setOrigine('comptabilite')
                ->setDateOperation(new \DateTime())
                ->setInformation($information)
                ->setSaisiePar($this->getUser())
                ->setPromo($session->get('promo'))
                ->setUser($avance->getPersonnelActif()->getPersonnel());
            $entityManager->persist($historique);
            $entityManager->remove($avance);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_gandaal_administration_comptabilite_avance_personnel_new', ['etablissement' => $etablissement->getId(), 'id_user_search' => $avance->getPersonnelActif()->getPersonnel()->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/delete/liaison/{id}/{etablissement}', name: 'app_gandaal_administration_comptabilite_avance_personnel_delete_liaison', methods: ['POST'])]
    public function deleteLiaison(Request $request, SessionInterface $session, AvanceSalairePersonnel $avance, AvanceSalairePersonnelRepository $AvanceSalairePersonnelRep, Etablissement $etablissement, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$avance->getId(), $request->getPayload()->getString('_token'))) {
            $reference = $avance->getReference();
            $paiements = $AvanceSalairePersonnelRep->findBy(['reference' => $reference]);
            $deleteReason = $request->request->get('delete_reason');

            foreach ($paiements as $paiement) {
                $information = 'ref '.$paiement->getReference().' montant'.number_format($paiement->getMontant(),0,',',' ');
                $historique = new HistoriqueSuppression();
                $historique->setType('paiement avance') // ou un type plus spécifique
                    ->setMotif($deleteReason)
                    ->setOrigine('comptabilite')
                    ->setDateOperation(new \DateTime())
                    ->setInformation($information)
                    ->setSaisiePar($this->getUser())
                    ->setPromo($session->get('promo'))
                    ->setUser($paiement->getPersonnelActif()->getPersonnel());
                $entityManager->persist($historique);
                $entityManager->remove($paiement);
                $entityManager->remove($paiement);
            }
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_gandaal_administration_comptabilite_avance_personnel_new', ['etablissement' => $etablissement->getId(), 'id_user_search' => $avance->getPersonnelActif()->getPersonnel()->getId()], Response::HTTP_SEE_OTHER);
    }
}
