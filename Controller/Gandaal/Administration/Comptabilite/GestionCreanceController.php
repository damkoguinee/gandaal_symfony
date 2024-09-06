<?php

namespace App\Controller\Gandaal\Administration\Comptabilite;

use App\Entity\Etablissement;
use App\Repository\EleveRepository;
use App\Repository\InscriptionRepository;
use App\Repository\PaiementEleveRepository;
use App\Repository\FraisScolariteRepository;
use App\Repository\TranchePaiementRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\ClasseRepartitionRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/gandaal/administration/comptabilite/gestion/creance')]
class GestionCreanceController extends AbstractController
{
    #[Route('/{etablissement}', name: 'app_gandaal_administration_comptabilite_gestion_creance_index')]
    public function index(Etablissement $etablissement, PaiementEleveRepository $paiementRep, InscriptionRepository $inscriptionRep, TranchePaiementRepository $tranchePaieRep, FraisScolariteRepository $fraisScolRep, SessionInterface $session, ClasseRepartitionRepository $classeRep, EleveRepository $eleveRep, Request $request ): Response
    {
        
        $search = $request->get('search') ? $request->get('search') : Null;    
        $tranche = $request->get('tranche') ? $tranchePaieRep->find($request->get('tranche')) : Null;        
        $classe = $request->get('classe') ? $classeRep->find($request->get('classe')) : Null;
        $pageEncours = $request->get('pageEncours', 1);

        
        $donnees = [];
        if ($classe) {
            $inscriptions = $inscriptionRep->listeDesElevesInscritParPromoParEtablissementParClassePaginated($session->get('promo'), $etablissement, $classe, $search, $pageEncours, 20000);

        }else{

            $inscriptions = $inscriptionRep->listeDesElevesInscritParPromoParEtablissement($session->get('promo'), $etablissement, $search, $pageEncours, 20000);
        }
        foreach ($inscriptions['data'] as $inscription) {
            
            $cumulPaiements = $paiementRep->cumulPaiementEleveGroupeParType($inscription, $session->get('promo'));

            $remiseScolarite = $inscription->getRemiseScolarite() ? $inscription->getRemiseScolarite() : 0;
    
            if ($tranche) {
                $tranchePaie = $tranchePaieRep->find($tranche);
        
                $frais_tranche = $fraisScolRep->findBy(['formation' => $inscription->getClasse()->getFormation(), 'tranche' => $tranchePaie, 'promo' => $session->get('promo')]);
            }else{
                $frais_tranche = $fraisScolRep->findBy(['formation' => $inscription->getClasse()->getFormation(), 'promo' => $session->get('promo')]);
            }
    
            $reste_scolarite_tranche = $paiementRep->creances($inscription, $session->get('promo'), $frais_tranche, $remiseScolarite/100);

            $paiement = 0;
            foreach ($cumulPaiements as $cumul) {
                $paiement += $cumul['solde'];
            }

            
            if ($reste_scolarite_tranche) {
                $donnees [] = [
                    'inscription' => $inscription,
                    'restes' => $reste_scolarite_tranche,
                    'cumuls' => $cumulPaiements,
                    'paiement' => $paiement,
                ];
            }
            
            
        }    

        return $this->render('gandaal/administration/comptabilite/gestion_creance/index.html.twig', [
            'etablissement' => $etablissement,
            'donnees' => $donnees,
            'promo' => $session->get('promo'),
            'tranches' => $tranchePaieRep->findBy(['etablissement' => $etablissement, 'promo' => $session->get('promo')]),
            'classes' => $classeRep->listeDesClassesParEtablissementParPromo($etablissement, $session->get('promo')),
            'classe' => $classe,
            'tranche' => $tranche,
            'search' => $search,
        ]);
    }


    #[Route('/retard/scolarite/{etablissement}', name: 'app_gandaal_administration_comptabilite_gestion_creance_retard_scolarite')]
    public function retardScolarite(Etablissement $etablissement, PaiementEleveRepository $paiementRep, InscriptionRepository $inscriptionRep, TranchePaiementRepository $tranchePaieRep, FraisScolariteRepository $fraisScolRep, SessionInterface $session, ClasseRepartitionRepository $classeRep, EleveRepository $eleveRep, Request $request ): Response
    {
        
        $search = $request->get('search') ? $request->get('search') : Null;    
        $tranche = $request->get('tranche') ? $tranchePaieRep->find($request->get('tranche')) : Null;        
        $classe = $request->get('classe') ? $classeRep->find($request->get('classe')) : Null;
        $pageEncours = $request->get('pageEncours', 1);

        
        $donnees = [];
        if ($classe) {
            $inscriptions = $inscriptionRep->listeDesElevesInscritParPromoParEtablissementParClassePaginated($session->get('promo'), $etablissement, $classe, $search, $pageEncours, 20000);

        }else{

            $inscriptions = $inscriptionRep->listeDesElevesInscritParPromoParEtablissement($session->get('promo'), $etablissement, $search, $pageEncours, 20000);
        }
        foreach ($inscriptions['data'] as $inscription) {
            
            $cumulPaiements = $paiementRep->cumulPaiementEleveGroupeParType($inscription, $session->get('promo'));

            $remiseScolarite = $inscription->getRemiseScolarite() ? $inscription->getRemiseScolarite() : 0;
    
            if ($tranche) {
                $tranchePaie = $tranchePaieRep->find($tranche);
        
                $frais_tranche = $fraisScolRep->findBy(['formation' => $inscription->getClasse()->getFormation(), 'tranche' => $tranchePaie, 'promo' => $session->get('promo')]);
            }else{
                $frais_tranche = $fraisScolRep->findBy(['formation' => $inscription->getClasse()->getFormation(), 'promo' => $session->get('promo')]);
            }
    
            $reste_scolarite_tranche = $paiementRep->resteScolariteEleveParDatelimite($inscription, $session->get('promo'), $frais_tranche, $remiseScolarite/100);

            $paiement = 0;
            foreach ($cumulPaiements as $cumul) {
                $paiement += $cumul['solde'];
            }

            
            if ($reste_scolarite_tranche) {
                $donnees [] = [
                    'inscription' => $inscription,
                    'restes' => $reste_scolarite_tranche,
                    'cumuls' => $cumulPaiements,
                    'paiement' => $paiement,
                ];
            }
            
            
        }    

        return $this->render('gandaal/administration/comptabilite/gestion_creance/retard_scolarite.html.twig', [
            'etablissement' => $etablissement,
            'donnees' => $donnees,
            'promo' => $session->get('promo'),
            'tranches' => $tranchePaieRep->findBy(['etablissement' => $etablissement, 'promo' => $session->get('promo')]),
            'classes' => $classeRep->listeDesClassesParEtablissementParPromo($etablissement, $session->get('promo')),
            'classe' => $classe,
            'tranche' => $tranche,
            'search' => $search,
        ]);
    }
}
