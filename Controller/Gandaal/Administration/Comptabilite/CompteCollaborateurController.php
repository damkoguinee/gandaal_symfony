<?php

namespace App\Controller\Gandaal\Administration\Comptabilite;


use App\Entity\Etablissement;
use App\Repository\UserRepository;
use App\Repository\ClientRepository;
use App\Repository\ConfigDeviseRepository;
use App\Repository\RegionRepository;
use App\Repository\EntrepriseRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Repository\MouvementCollaborateurRepository;
use App\Repository\PersonnelRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/gandaal/administration/comptabilite/compte/collaborateur')]
class CompteCollaborateurController extends AbstractController
{
    #[Route('/{etablissement}', name: 'app_gandaal_administration_comptabilite_compte_collaborateur_index')]
    public function index(Etablissement $etablissement, Request $request, MouvementCollaborateurRepository $mouvementRep, ConfigDeviseRepository $deviseRep, UserRepository $userRep, EntrepriseRepository $entrepriseRep): Response
    {
        if ($request->get("date1")){
            $date1 = $request->get("date1");
            $date2 = $request->get("date2");

        }else{
            $date1 = date("Y-01-01");
            $date2 = date("Y-m-d");
        }
        $type1 = $request->get('type1') ? $request->get('type1') : 'personnel';
        $type2 = $request->get('type2') ? $request->get('type2') : 'personnel';

        if ($request->get("id_user_search")){
            $search = $request->get("id_user_search");            
        }else{
            $search = "";
        }
        if ($request->isXmlHttpRequest()) {
            $search = $request->query->get('search');
            $users = $userRep->rechercheUserType1Type2ParEtablissement($search, 'eleve', 'personnel', $etablissement);    
            $response = [];
            foreach ($users as $user) {
                $response[] = [
                    'nom' => ucwords($user->getPrenom())." ".strtoupper($user->getNom()),
                    'id' => $user->getId()
                ]; 
            }
            return new JsonResponse($response);
        }
        $devises = $deviseRep->findAll();
        
        

        if ($request->get("id_user_search")) {
            $users = $userRep->findBy(['id' => $request->get("id_user_search")]);
        }else{
            $users = $userRep->findBy(['typeUser' => 'personnel', 'etablissement' => $etablissement]);   
        }
        
        $comptes = [];
        foreach ($users as $user) {
           $comptes[] = [
                'collaborateur' => $user,
                'soldes' => $mouvementRep->findSoldeCompteCollaborateur($user, $devises)
            ];
        }
        $solde_general_type = $mouvementRep->findSoldeGeneralByType($type1, $type2, $etablissement, $devises);
        // dd($solde_general_type);
        return $this->render('gandaal/administration/comptabilite/compte/compte_collaborateur/index.html.twig', [
            'etablissement' => $etablissement,
            'search' => $search,
            'comptes' => $comptes,
            'devises'   => $devises,
            'type1' => $type1,
            'type2' => $type2,
            'solde_general_type' => $solde_general_type,
            'date1' => $date1,
            'date2' => $date2,
            'search' => $search
        ]);
    }

    #[Route('/detail/{etablissement}', name: 'app_gandaal_administration_comptabilite_compte_collaborateur_detail')]
    public function detailCompte(Etablissement $etablissement, UserRepository $userRep, Request $request, MouvementCollaborateurRepository $mouvementRep, ConfigDeviseRepository $deviseRep, EntrepriseRepository $entrepriseRep): Response
    {
        

        if ($request->get("date1")){
            $date1 = $request->get("date1");
            $date2 = $request->get("date2");

        }else{
            $date1 = date("Y-01-01");
            $date2 = date("Y-m-d");
        }
        $user = $userRep->find($request->get('user'));
        $devise = $deviseRep->findOneBy(['nom' => $request->get('devise')]);
        $pageEncours = $request->get('pageEncours', 1);

        $mouvements = $mouvementRep->SoldeDetailByCollaborateurByDeviseByDate($user, $devise, $date1, $date2, $pageEncours, 2000);
        
        $solde_init = $mouvementRep->sumMontantBeforeStartDate($user, $devise, $date1);
        return $this->render('gandaal/administration/comptabilite/compte/compte_collaborateur/detail_compte.html.twig', [
            'etablissement' => $etablissement,
            'mouvements' =>$mouvements,
            'solde_init' => $solde_init,
            'user' => $user,
            'devise' => $devise,
            'date1' => $date1,
            'date2' => $date2,
        ]);
    }
}
