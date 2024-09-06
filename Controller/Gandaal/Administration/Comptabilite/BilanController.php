<?php

namespace App\Controller\Gandaal\Administration\Comptabilite;

use App\Entity\Etablissement;
use App\Repository\ConfigCaisseRepository;
use App\Repository\ConfigDeviseRepository;
use App\Repository\ConfigModePaiementRepository;
use App\Repository\UserRepository;
use App\Repository\MouvementCaisseRepository;
use App\Repository\PaiementEleveRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/gandaal/administration/comptabilite/bilan')]
class BilanController extends AbstractController
{
    #[Route('/{etablissement}', name: 'app_gandaal_administration_comptabilite_bilan')]
    public function index(Etablissement $etablissement, Request $request, SessionInterface $session, MouvementCaisseRepository $mouvementRep, ConfigDeviseRepository $deviseRep, ConfigCaisseRepository $caisseRep, PaiementEleveRepository $paiementRep, ConfigModePaiementRepository $modePaieRep): Response
    {
        $firstOp = $mouvementRep->findOneBy(['promo' => $session->get('promo')], ['dateOperation' => 'ASC']);
        $date1 = $request->get("date1") ? $request->get("date1") : ($firstOp ? $firstOp->getDateOperation()->format('Y-m-d') : $request->get("date1"));
        $date2 = $request->get("date2") ? $request->get("date2") : date("Y-m-d");

        $search_devise = $request->get("search_devise") ? $deviseRep->find($request->get("search_devise")) : $deviseRep->find(1);
        $search_caisse = $request->get("search_caisse") ? $caisseRep->find($request->get("search_caisse")) : $caisseRep->findOneBy([]);

        $caisses = $caisseRep->findBy(['etablissement' => $etablissement]);
        $devises = $deviseRep->findAll();
        $modesPaiement = $modePaieRep->findAll();

        $solde_caisses = $mouvementRep->soldeCaisseParPeriodeParLieu($date1, $date2, $session->get('promo'), $etablissement, $devises, $caisses);
        $caisses_lieu = [];
        foreach ($solde_caisses as $solde) {
            foreach ($caisses as $caisse) {
                if ($solde['id_caisse'] == $caisse->getId()) {
                    $caisses_lieu[$caisse->getNom()][] = $solde;
                } 
            }
        }

        $solde_caisses_devises = $mouvementRep->soldeCaisseParDeviseParLieu($date1, $date2, $session->get('promo'), $etablissement, $devises);

        if ($request->get("search_caisse")) {
            $solde_types = $mouvementRep->soldeCaisseParPeriodeParTypeParLieuParDeviseParCaisse($date1, $date2, $session->get('promo'), $etablissement, $search_devise, $search_caisse);
        } else {
            $solde_types = $mouvementRep->soldeCaisseParPeriodeParTypeParLieuParDevise($date1, $date2, $session->get('promo'), $etablissement, $search_devise);
        }

        // Organiser les données par mode de paiement
        $solde_types_par_mode = [];
        foreach ($solde_types as $solde_type) {
            $modePaie = $solde_type['mouvement']->getModePaie()->getNom();
            $typeMouvement = $solde_type['mouvement']->getTypeMouvement();

            if (!isset($solde_types_par_mode[$typeMouvement])) {
                $solde_types_par_mode[$typeMouvement] = [];
            }

            if (!isset($solde_types_par_mode[$typeMouvement][$modePaie])) {
                $solde_types_par_mode[$typeMouvement][$modePaie] = [
                    'mouvement' => $solde_type['mouvement'],
                    'solde' => 0,
                    'nbre' => 0
                ];
            }

            $solde_types_par_mode[$typeMouvement][$modePaie]['solde'] += $solde_type['solde'];
            $solde_types_par_mode[$typeMouvement][$modePaie]['nbre'] += $solde_type['nbre'];
        }

        // Calculer les totaux pour chaque type de mouvement
        $totals = [];
        foreach ($solde_types_par_mode as $typeMouvement => $modes) {
            $totals[$typeMouvement] = [
                'nbre' => array_sum(array_column($modes, 'nbre')),
                'solde' => array_sum(array_column($modes, 'solde'))
            ];
        }

        // // Trier les types de mouvement par solde (positifs en haut, négatifs en bas)
        // foreach ($solde_types_par_mode as $typeMouvement => &$modes) {
        //     usort($modes, function($a, $b) {
        //         return $b['solde'] <=> $a['solde'];
        //     });
        // }

        // dd($solde_types_par_mode);

        return $this->render('gandaal/administration/comptabilite/bilan/index.html.twig', [
            'solde_caisses' => $caisses_lieu,
            'solde_caisses_devises' => $solde_caisses_devises,
            'solde_types' => $solde_types_par_mode,
            'totals' => $totals,
            'etablissement' => $etablissement,
            'liste_caisse' => $caisses,
            'search' => "",
            'devises' => $devises,
            'modesPaiement' => $modesPaiement,
            'date1' => $date1,
            'date2' => $date2,
            'search_devise' => $search_devise,
            'search_caisse' => $search_caisse,
            'promo' => $session->get('promo'),
        ]);
    }


    #[Route('/mouvement/caisse/{etablissement}', name: 'app_gandaal_administration_comptabilite_bilan_mouvement_caisse', methods: ['GET'])]
    public function mouvementCaisse(MouvementCaisseRepository $mouvementCaisseRep, ConfigDeviseRepository $deviseRep, Request $request, ConfigCaisseRepository $caisseRep, SessionInterface $session, Etablissement $etablissement): Response
    {
        if ($request->get("search_devise")){
            $search_devise = $deviseRep->find($request->get("search_devise"));
        }else{
            $search_devise = $deviseRep->find(1);
        }

        if ($request->get("search_caisse")){
            $search_caisse = $caisseRep->find($request->get("search_caisse"));
        }else{
            $search_caisse = $caisseRep->findOneBy([]);
        }

        if ($request->get("date1")){
            $date1 = $request->get("date1");
            $date2 = $request->get("date2");

        }else{
            $date1 = date("Y-01-01");
            $date2 = date("Y-m-d");
        }

        $pageEncours = $request->get('pageEncours', 1);
        
        $operations = $mouvementCaisseRep->listeOperationcaisseParEtablissementParCaisseParDeviseParPeriode($etablissement, $search_caisse, $search_devise, $session->get('promo'), $date1, $date2, $pageEncours, 50);

        $solde_generale = $mouvementCaisseRep->findSoldeCaisseByPromo($search_caisse , $search_devise, $session->get('promo'));
        $solde_selection = $mouvementCaisseRep->soldeCaisseParDeviseParPeriode($search_caisse, $search_devise,  $session->get('promo'), $date1, $date2);

        return $this->render('gandaal/administration/comptabilite/bilan/mouvement_caisse.html.twig', [
            'etablissement' => $etablissement,
            'liste_caisse' => $caisseRep->findBy(['etablissement' => $etablissement]),
            'date1' => $date1,
            'date2' => $date2,
            'search_devise' => $search_devise,
            'search_caisse' => $search_caisse,
            'devises' => $deviseRep->findAll(),
            'operations' => $operations,
            'solde_general' => $solde_generale,
            'solde_selection' => $solde_selection
        ]);
    }


}
