<?php

namespace App\Controller\Gandaal\Administration\Comptabilite;

use DateTime;
use ReflectionClass;
use App\Entity\Etablissement;
use App\Entity\PaiementEleve;
use App\Form\PaiementEleveType;
use App\Repository\UserRepository;
use App\Repository\EleveRepository;
use App\Entity\HistoriqueSuppression;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\InscriptionRepository;
use App\Repository\ConfigCaisseRepository;
use App\Repository\ConfigDeviseRepository;
use App\Repository\EtablissementRepository;
use App\Repository\PaiementEleveRepository;
use App\Repository\FraisScolariteRepository;
use App\Repository\MouvementCaisseRepository;
use App\Repository\TranchePaiementRepository;
use Symfony\Component\HttpFoundation\Request;
use App\Repository\FraisInscriptionRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\ConfigModePaiementRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Repository\ConfigCompteOperationRepository;
use App\Repository\ConfigCategorieOperationRepository;
use App\Repository\HistoriqueSuppressionRepository;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Session\Session;

#[Route('/gandaal/administration/comptabilite/paiement/eleve')]
class PaiementEleveController extends AbstractController
{
    #[Route('/accueil/{etablissement}', name: 'app_gandaal_administration_comptabilite_paiement_eleve_index', methods: ['GET'])]
    public function index(PaiementEleveRepository $paiementRep, SessionInterface $session, EleveRepository $eleveRep, Request $request, etablissement $etablissement): Response
    {
        if ($request->get("id_user_search")){
            $search = $request->get("id_user_search");
        }else{
            $search = "";
        }


        $firstOp = $paiementRep->findOneBy(['promo' => $session->get('promo')], ['dateOperation' => 'ASC']);
        $date1 = $request->get("date1") ? $request->get("date1") : ($firstOp ? $firstOp->getDateOperation()->format('Y-m-d') : $request->get("date1"));
        $date2 = $request->get("date2") ? $request->get("date2") : date("Y-m-d");  

        if ($request->isXmlHttpRequest()) {
            $search = $request->query->get('search');
            $eleves = $eleveRep->rechercheEleveParEtablissement($search, $etablissement);    
            $response = [];
            foreach ($eleves as $eleve) {
                $response[] = [
                    'nom' => ucwords($eleve->getPrenom())." ".strtoupper($eleve->getNom()),
                    'id' => $eleve->getId()
                ]; 
            }
            return new JsonResponse($response);
        }

        $pageEncours = $request->get('pageEncours', 1);
        if ($request->get("id_user_search")){
            $paiements = $paiementRep->findPaiementScolariteByEtablissementBySearchPaginated($etablissement, $search, $session->get('promo'), $date1, $date2, $pageEncours, 25);
        }else{
            $paiements = $paiementRep->findPaiementScolariteByEtablissementPaginated($etablissement, $session->get('promo'), $date1, $date2, $pageEncours, 25);
        }

        return $this->render('gandaal/administration/comptabilite/paiement_eleve/index.html.twig', [
            'paiements' => $paiements,
            'search' => $search,            
            'etablissement' => $etablissement,
            'date1' => $date1,
            'date2' => $date2,
        ]);
    }

    #[Route('/new/{etablissement}', name: 'app_gandaal_administration_comptabilite_paiement_eleve_new', methods: ['GET', 'POST'])]
    public function new(Request $request, Etablissement $etablissement, ConfigCompteOperationRepository $compteOpRep, ConfigCategorieOperationRepository $categorieOpRep, MouvementCaisseRepository $mouvementCaisseRep, InscriptionRepository $inscriptionRep, UserRepository $userRep, SessionInterface $session, FraisScolariteRepository $fraisScolRep, FraisInscriptionRepository $fraisInsRep, PaiementEleveRepository $paiementRep, TranchePaiementRepository $tranchePaieRep, EtablissementRepository $etablissementRep, ConfigCaisseRepository $caisseRep, ConfigModePaiementRepository $modePaieRep, ConfigDeviseRepository $deviseRep, EntityManagerInterface $entityManager): Response
    {
        $paiements = $paiementRep->findAll();

        if ($request->get("id_user_search")){
            $search = $userRep->find($request->get("id_user_search"));            
        }else{
            $search = "";
        }
        if ($request->isXmlHttpRequest()) {
            $search = $request->query->get('search');
            $inscriptions = $inscriptionRep->rechercheEleveParEtablissementParPromo($search, $session->get('promo'), $etablissement);    
            $response = [];
            foreach ($inscriptions as $inscription) {
                $response[] = [
                    'nom' => ucwords($inscription->getEleve()->getPrenom())." ".strtoupper($inscription->getEleve()->getNom()),
                    'id' => $inscription->getEleve()->getId()
                ]; 
            }
            return new JsonResponse($response);
        }

        $tranches = $tranchePaieRep->findBy(['etablissement' => $etablissement, 'promo' => $session->get('promo')]);

        $inscription = $inscriptionRep->findOneBy(['eleve' => $search, 'promo' => $session->get('promo')]);
        if ($inscription) {
            $remiseIns = $inscription->getRemiseInscription() ? $inscription->getRemiseInscription() : 0;
            $remiseScolarite = $inscription->getRemiseScolarite() ? $inscription->getRemiseScolarite() : 0;
            $classe = $inscription->getClasse();
    
            $frais = $fraisScolRep->findBy(['formation' => $classe->getFormation(), 'promo' => $session->get('promo')]);

            $cursus = $classe->getFormation()->getCursus();

            $fraisIns = $fraisInsRep->findOneBy(['cursus' => $cursus, 'description' => $inscription->getType(), 'promo' => $session->get('promo')]);

            $paiements = $paiementRep->findBy(['inscription' => $inscription, 'promo' => $session->get('promo')]);
            
            $totalScolarite = $fraisScolRep->montantTotalFraisScolariteParFormation($classe->getFormation(), $session->get('promo'));

            $totalScolarite = $totalScolarite * (1 - ($remiseScolarite / 100));
            $fraisInscription = $fraisIns->getMontant() * (1 - ($remiseIns / 100));
            
            $scolarite_annuel = $totalScolarite + $fraisInscription;

            $reste_scolarite = $paiementRep->resteScolariteEleve($inscription, $session->get('promo'), $frais, $remiseScolarite/100);

            $reste_inscription = $fraisInscription - $paiementRep->paiementInscription($inscription, $session->get('promo'));
            
        }else{
            $frais = [];
            $fraisIns = [];
            $paiements = [];
            $scolarite_annuel = 0;
            $reste_scolarite = [];
            $reste_inscription = 0;
        }
      

        $panier = $session->get('panier', []);
        

        $currentYear = (new \DateTime())->format('ymd');
        $maxPaie = $paiementRep->findCountId($etablissement, $session->get('promo'));
        $formattedMaxPaie = sprintf('%04d', $maxPaie + 1); 
        $generatedReference = $currentYear . $formattedMaxPaie;
        $reference = $generatedReference;
        
        if ($request->get('paiement_eleve')) {
            $type = $request->get('type');
            $caisse = $request->get('caisse');
            $devise = $request->get('devise');
            $modePaie = $request->get('modePaie');
            $montant = $request->get('montant');
            $datePaiement = $request->get('datePaie');
            $banquePaie = $request->get('banquePaie');
            $numeroPaie = $request->get('numeroPaie');
            $taux = $request->get('taux');
            $paiements = [];

            if ($type == 'inscription') {// gestion des paiements des frais inscroptions
                
                $reste_a_payer = $reste_inscription;
                $montant_paye = floatval(preg_replace('/[^0-9,.]/', '', $request->get('montant')));

                if ($montant_paye > $reste_a_payer) {
                    $this->addFlash("warning", "le montant payé est supérieur au montant restant :) ");
                    $referer = $request->headers->get('referer');
                    return $this->redirect($referer);
                }else{
                    // Vérifiez si l'élève existe déjà dans le panier
                    $eleveExisteDeja = false;
                    foreach ($panier as $paiement) {
                        if ($paiement['inscriptionEleve']->getEleve() === $search) {
                            // L'élève existe déjà, mettez à jour les informations de paiement
                            $paiement['caisse'] = $caisse;
                            $paiement['devise'] = $devise;
                            $paiement['modePaie'] = $modePaie;
                            $paiement['inscription'] = $montant_paye;
                            $paiement['fraisScol'] = 0;
                            $paiement['datePaiement'] = $datePaiement;
                            $eleveExisteDeja = true;
                            break;
                        }
                    }

                    // Si l'élève n'existe pas déjà dans le panier, ajoutez un nouveau paiement
                    if (!$eleveExisteDeja) {                    
                        $panier[] = [
                            'inscriptionEleve' => $inscription,
                            'caisse' => $caisse,
                            'devise' => $devise,
                            'modePaie' => $modePaie,
                            'inscription' => $montant_paye,
                            'fraisScol' => 0,
                            'remiseIns' => $remiseIns,
                            'remiseScolarite' => 0,
                            'datePaiement' => $datePaiement,
                            'banquePaie' => $banquePaie,
                            'numeroPaie' => $numeroPaie,
                            'taux' => $taux,
                        ];
                    }

                    // Mettre à jour la session avec le nouveau tableau de paiements
                    $session->set('panier', $panier);
                    // $session->remove('panier');
                    $panier = $session->get('panier');
                    $this->addFlash("success", "Paiment enregistré avec succés :) ");
                    $referer = $request->headers->get('referer');
                    return $this->redirect($referer);
                }

            } 

            if (intval($type) == $type) {// on gère les tranches individuellement
                // on recupere les paiements précedents et comparé le montant payé
                
                $tranchePaie = $tranchePaieRep->find($type);

                $frais_tranche = $fraisScolRep->findBy(['formation' => $classe->getFormation(), 'tranche' => $tranchePaie, 'promo' => $session->get('promo')]);

                $reste_scolarite_tranche = $paiementRep->resteScolariteEleve($inscription, $session->get('promo'), $frais_tranche, $remiseScolarite/100);

                $reste_a_payer = $reste_scolarite_tranche[$tranchePaie->getNom()];
                $montant_paye = floatval(preg_replace('/[^0-9,.]/', '', $request->get('montant')));

                if ($montant_paye > $reste_a_payer) {
                    $this->addFlash("warning", "le montant payé est supérieur au montant restant :) ");
                    $referer = $request->headers->get('referer');
                    return $this->redirect($referer);
                }else{
                    $frais_tranche_paye = $frais_tranche[0];
                    // on remplace le montant de la tranche par le montant payé
                    $frais_tranche_paye->setMontant($montant_paye);
                    // Vérifiez si l'élève existe déjà dans le panier
                    $eleveExisteDeja = false;
                    foreach ($panier as $paiement) {
                        if ($paiement['inscriptionEleve']->getEleve() === $search) {
                            // L'élève existe déjà, mettez à jour les informations de paiement
                            $paiement['caisse'] = $caisse;
                            $paiement['devise'] = $devise;
                            $paiement['modePaie'] = $modePaie;
                            $paiement['inscription'] = 0;
                            $paiement['fraisScol'] = $frais_tranche_paye;
                            $paiement['datePaiement'] = $datePaiement;
                            $eleveExisteDeja = true;
                            break;
                        }
                    }

                    // Si l'élève n'existe pas déjà dans le panier, ajoutez un nouveau paiement
                    if (!$eleveExisteDeja) {                    
                        $panier[] = [
                            'inscriptionEleve' => $inscription,
                            'caisse' => $caisse,
                            'devise' => $devise,
                            'modePaie' => $modePaie,
                            'inscription' => 0,
                            'fraisScol' => $frais_tranche_paye,
                            'remiseIns' => 0,
                            'remiseScolarite' => $remiseScolarite,
                            'datePaiement' => $datePaiement,
                            'banquePaie' => $banquePaie,
                            'numeroPaie' => $numeroPaie,
                            'taux' => $taux,
                        ];
                    }

                    // Mettre à jour la session avec le nouveau tableau de paiements
                    $session->set('panier', $panier);
                    // $session->remove('panier');
                    $panier = $session->get('panier');
                    $this->addFlash("success", "Paiment enregistré avec succés :) ");
                    $referer = $request->headers->get('referer');
                    return $this->redirect($referer);
                }

            } 


            if ($type == 'reste_scolarite_annuel') {
                foreach ($frais as $scol) {
                    // Vérifiez que l'objet FraisScolarite a une tranche définie dans $reste_scolarite
                    if (isset($reste_scolarite[$scol->getTranche()->getNom()])) {
                        // Remplacez le montant par le montant correspondant dans $reste_scolarite
                        $nouveauMontant = $reste_scolarite[$scol->getTranche()->getNom()];
                        $scol->setMontant($nouveauMontant);
                    }
                }
                // Vérifiez si l'élève existe déjà dans le panier
                $eleveExisteDeja = false;
                foreach ($panier as $paiement) {
                    if ($paiement['inscriptionEleve']->getEleve() === $search) {
                        // L'élève existe déjà, mettez à jour les informations de paiement
                        $paiement['caisse'] = $caisse;
                        $paiement['devise'] = $devise;
                        $paiement['modePaie'] = $modePaie;
                        $paiement['inscription'] = $reste_inscription;
                        $paiement['fraisScol'] = $frais;
                        $paiement['datePaiement'] = $datePaiement;
                        $eleveExisteDeja = true;
                        break;
                    }
                }

                // Si l'élève n'existe pas déjà dans le panier, ajoutez un nouveau paiement
                if (!$eleveExisteDeja) {                    
                    $panier[] = [
                        'inscriptionEleve' => $inscription,
                        'caisse' => $caisse,
                        'devise' => $devise,
                        'modePaie' => $modePaie,
                        'inscription' => $reste_inscription,
                        'fraisScol' => $frais,
                        'remiseIns' => $remiseIns,
                        'remiseScolarite' => $remiseScolarite,
                        'datePaiement' => $datePaiement,
                        'banquePaie' => $banquePaie,
                        'numeroPaie' => $numeroPaie,
                        'taux' => $taux,
                    ];
                }

                // Mettre à jour la session avec le nouveau tableau de paiements
                $session->set('panier', $panier);
                // $session->remove('panier');
                $panier = $session->get('panier');
                $this->addFlash("success", "Paiment enregistré avec succés :) ");
                $referer = $request->headers->get('referer');
                return $this->redirect($referer);


            }

            

            if ($type == 'general') {
                
                $fraisInscription = $fraisIns->getMontant()*(1 - ($remiseIns/100));
                // verif s'il n ya pas des paiements avant
                $verif_paie = $paiementRep->findOneBy(['inscription' => $inscription, 'promo' => $session->get('promo')]);
                
                if ($verif_paie) {
                    $this->addFlash("warning", "cet élève à déjà éffectué un paiement :) ");
                    $referer = $request->headers->get('referer');
                    return $this->redirect($referer);
                }
                // Vérifiez si l'élève existe déjà dans le panier
                $eleveExisteDeja = false;
                foreach ($panier as $paiement) {
                    if ($paiement['inscriptionEleve']->getEleve() === $search) {
                        // L'élève existe déjà, mettez à jour les informations de paiement
                        $paiement['caisse'] = $caisse;
                        $paiement['devise'] = $devise;
                        $paiement['modePaie'] = $modePaie;
                        $paiement['inscription'] = $fraisInscription;
                        $paiement['fraisScol'] = $frais;
                        $paiement['datePaiement'] = $datePaiement;
                        $eleveExisteDeja = true;
                        break;
                    }
                }

                // Si l'élève n'existe pas déjà dans le panier, ajoutez un nouveau paiement
                if (!$eleveExisteDeja) {                    
                    $panier[] = [
                        'inscriptionEleve' => $inscription,
                        'caisse' => $caisse,
                        'devise' => $devise,
                        'modePaie' => $modePaie,
                        'inscription' => $fraisInscription,
                        'fraisScol' => $frais,
                        'remiseIns' => $remiseIns,
                        'remiseScolarite' => $remiseScolarite,
                        'datePaiement' => $datePaiement,
                        'banquePaie' => $banquePaie,
                        'numeroPaie' => $numeroPaie,
                        'taux' => $taux,
                    ];
                }

                // Mettre à jour la session avec le nouveau tableau de paiements
                $session->set('panier', $panier);
                // $session->remove('panier');
                $panier = $session->get('panier');
                $this->addFlash("success", "Paiment enregistré avec succés :) ");
                $referer = $request->headers->get('referer');
                return $this->redirect($referer);


            }
        }

        $caisses = $caisseRep->findBy(['etablissement' => $etablissement]);
        $devises = $deviseRep->findAll();
        $modePaies = $modePaieRep->findAll();
        if ($request->get('finaliser')) {
            foreach ($panier as $item) {  
                $inscription_id = $item['inscriptionEleve']->getId();
                $inscription_eleve = $inscriptionRep->find($inscription_id);
                $caisse_id = $item['caisse'];
                $caisse = $caisseRep->find($caisse_id);   
                $devise_id = $item['devise'];
                $devise = $deviseRep->find($devise_id);
                $modePaie_id = $item['modePaie'];
                $modePaie = $modePaieRep->find($modePaie_id);

                $datePaiement = new \DateTime($item['datePaiement']);
        
                if ($item['inscription']) {
                    $paiement_eleve_ins = new PaiementEleve();
                    $paiement_eleve_ins->setDateSaisie(new \DateTime("now"))
                        ->setSaisiePar($this->getUser())
                        ->setEtablissement($etablissement)
                        ->setDateOperation($datePaiement ? $datePaiement : new \DateTime("now"))
                        ->setReference($reference)
                        ->setInscription($inscription_eleve)
                        ->setPromo($session->get('promo'))
                        ->setMontant($item['inscription'])
                        ->setTaux($item['taux'])
                        ->setCaisse($caisse)
                        ->setDevise($devise)
                        ->setModePaie($modePaie)
                        ->setBanquePaie($item['banquePaie'])
                        ->setNumeroPaie($item['numeroPaie'])
                        ->setTypePaie($inscription->getType())
                        ->setOrigine('inscription')
                        ->setCategorieOperation($categorieOpRep->find(1))
                        ->setCompteOperation($compteOpRep->find(6))
                        ->setTypeMouvement($inscription->getType())
                        ->setEtatOperation('clos');        
                    $entityManager->persist($paiement_eleve_ins);
                }
        
                if ($item['fraisScol']) {
                    if (is_array($item['fraisScol'])) {
                        foreach ($item['fraisScol'] as $frais) {
                            if ($item['remiseScolarite']) {
                                $frais_scolarite = $frais->getMontant();
                            } else {
                                $frais_scolarite = $frais->getMontant();
                            }
            
                            $paiement_eleve_scol = new PaiementEleve();
                            $paiement_eleve_scol->setDateSaisie(new \DateTime("now"))
                                ->setSaisiePar($this->getUser())
                                ->setDateOperation($datePaiement ? $datePaiement : new \DateTime("now"))
                                ->setEtablissement($etablissement)                            
                                ->setReference($reference)
                                ->setInscription($inscription_eleve)
                                ->setPromo($session->get('promo'))
                                ->setMontant($frais_scolarite)
                                ->setTaux($item['taux'])
                                ->setCaisse($caisse)
                                ->setDevise($devise)
                                ->setModePaie($modePaie)
                                ->setBanquePaie($item['banquePaie'])
                                ->setNumeroPaie($item['numeroPaie'])
                                ->setTypePaie($frais->getTranche()->getNom())
                                ->setOrigine('scolarite')
                                ->setCategorieOperation($categorieOpRep->find(1))
                                ->setCompteOperation($compteOpRep->find(6))
                                ->setTypeMouvement("scolarite")
                                ->setEtatOperation('clos');
            
                            $entityManager->persist($paiement_eleve_scol);
                        }
                    }else{
                        if ($item['remiseScolarite']) {
                            $frais_scolarite = $item['fraisScol']->getMontant();
                        } else {
                            $frais_scolarite = $item['fraisScol']->getMontant();
                        }
        
                        $paiement_eleve_scol = new PaiementEleve();
                        $paiement_eleve_scol->setDateSaisie(new \DateTime("now"))
                            ->setSaisiePar($this->getUser())
                            ->setDateOperation($datePaiement ? $datePaiement : new \DateTime("now"))
                            ->setEtablissement($etablissement)                            
                            ->setReference($reference)
                            ->setInscription($inscription_eleve)
                            ->setPromo($session->get('promo'))
                            ->setMontant($frais_scolarite)
                            ->setTaux($item['taux'])
                            ->setCaisse($caisse)
                            ->setDevise($devise)
                            ->setModePaie($modePaie)
                            ->setBanquePaie($item['banquePaie'])
                            ->setNumeroPaie($item['numeroPaie'])
                            ->setTypePaie($item['fraisScol']->getTranche()->getNom())
                            ->setOrigine('scolarite')
                            ->setCategorieOperation($categorieOpRep->find(1))
                            ->setCompteOperation($compteOpRep->find(6))
                            ->setTypeMouvement("scolarite")
                            ->setEtatOperation('clos');
        
                        $entityManager->persist($paiement_eleve_scol);
                    }
                }
            }
            // $entityManager->clear();
            $entityManager->flush();
            $session = $request->getSession();
            $session->remove('panier');
            $session->remove('caisse');
            $session->remove('devise');
            $session->remove('modePaie');
            $session->remove('numeroPaie');
            $session->remove('banquePaie');
            $session->remove('taux');

            $this->addFlash("success", "Paiement validé avec succès :)");
            // return $this->redirectToRoute('app_gandaal_administration_comptabilite_paiement_eleve_new', ['etablissement' => $etablissement->getId(), 'id_user_search' => $search->getId(), 'step' => 'end'], Response::HTTP_SEE_OTHER);
            $referer = $request->headers->get('referer');
            return $this->redirect($referer);

        }
        $cumulGeneral = 0;
        foreach ($panier as $key => $item) {
            $cumulGeneral = $cumulGeneral + $item['inscription'];

            if (is_array($item['fraisScol'])) {
                foreach ($item['fraisScol'] as $scol) {
                    if ($item['remiseScolarite']) {
                        $frais_scolarite = $scol->getMontant();
                    }else{
                        $frais_scolarite = $scol->getMontant();
                    }
                    
                    $cumulGeneral = $cumulGeneral + $frais_scolarite;
                    
                }
            }else{
                if ($item['fraisScol']) {
                    if ($item['remiseScolarite']) {
                        $frais_scolarite = $item['fraisScol']->getMontant() ;
                    }else{
                        $frais_scolarite = $item['fraisScol']->getMontant();
                    }
                }else{
                    $frais_scolarite = 0;
                }
                
                $cumulGeneral = $cumulGeneral + $frais_scolarite;

            }

            if ($key == 0) {
                $caisse_id = $item['caisse'];
                $caisse = $caisseRep->find($caisse_id); 
                $session->set('caisse', $caisse);

                $devise_id = $item['devise'];
                $devise = $deviseRep->find($devise_id);
                $session->set('devise', $devise);

                $modePaie_id = $item['modePaie'];
                $modePaie = $modePaieRep->find($modePaie_id);
                $session->set('modePaie', $modePaie);

                $taux = $item['taux'];
                $session->set('taux', $taux);

                $banquePaie = $item['banquePaie'];
                $session->set('banquePaie', $banquePaie);

                $numeroPaie = $item['numeroPaie'];
                $session->set('numeroPaie', $numeroPaie);
            }
        }
        $historiques = $paiementRep->findBy(['inscription' => $inscription, 'promo' => $session->get('promo')], ['id' => 'DESC']);
        $cumulPaiements = $paiementRep->cumulPaiementEleveGroupeParType($inscription, $session->get('promo'));
       
        
        return $this->render('gandaal/administration/comptabilite/paiement_eleve/new.html.twig', [
            'etablissement' => $etablissement,
            'search' => $search,
            'inscription' => $inscription,
            'frais' => $frais,
            'fraisIns' => $fraisIns,
            'paiements' => $paiements,
            'tranches' => $tranches,
            'panier' => $panier,
            'cumulGeneral' => $cumulGeneral,
            'caisses' => $caisses,
            'devises' => $devises,
            'modePaies' => $modePaies,
            'session_caisse' => $session->get('caisse', []),
            'session_devise' => $session->get('devise', []),
            'session_modePaie' => $session->get('modePaie', []),
            'session_taux' => $session->get('taux', []),
            'session_banquePaie' => $session->get('banquePaie', []),
            'session_numeroPaie' => $session->get('numeroPaie', []),
            'historiques' => $historiques,
            'cumulPaiements' => $cumulPaiements,
            'scolarite_annuel' => $scolarite_annuel,
            'reste_scolarite' => $reste_scolarite,
            'reste_inscription' => $reste_inscription,
            
        ]);
    }

    #[Route('/show/{id}/{etablissement}', name: 'app_gandaal_administration_comptabilite_paiement_eleve_show', methods: ['GET'])]
    public function show(PaiementEleve $paiementEleve, PaiementEleveRepository $paiementRep, Etablissement $etablissement): Response
    {
        
        $paiement_lies = $paiementRep->paiementEleveLies($paiementEleve->getReference(), $paiementEleve);
        return $this->render('gandaal/administration/comptabilite/paiement_eleve/show.html.twig', [
            'paiement_eleve' => $paiementEleve,
            'etablissement' => $etablissement,
            'paiement_lies' => $paiement_lies,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_gandaal_administration_comptabilite_paiement_eleve_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, PaiementEleve $paiementEleve, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(PaiementEleveType::class, $paiementEleve);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_gandaal_administration_comptabilite_paiement_eleve_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/administration/comptabilite/paiement_eleve/edit.html.twig', [
            'paiement_eleve' => $paiementEleve,
            'form' => $form,
        ]);
    }

    #[Route('/delete/paiement/{id}/{etablissement}', name: 'app_gandaal_administration_comptabilite_paiement_eleve_delete_paiement')]
    public function deletePaiement($id, Etablissement $etablissement, Request $request, SessionInterface $session): Response
    {
        $panier = $session->get("panier", []);
        foreach ($panier as $indice => $item) {
            if ($item['inscriptionEleve']->getId() == $id) {
                unset($panier[$indice]);
                break;
            }
        }
        $session->set("panier", $panier);

        if (empty($panier)) {
            // si le panier est vide on initialise tous les paiements
            
            $session->remove('panier');           
        }
        $this->addFlash("success", "le paiement a été retiré de votre panier"); 
        // return $this->redirectToRoute("app_gandaal_administration_comptabilite_paiement_eleve_new", ['etablissement' => $etablissement->getId()]);
        $referer = $request->headers->get('referer');
        return $this->redirect($referer);
    }

    #[Route('/confirm/delete/{id}/{etablissement}', name: 'app_gandaal_administration_comptabilite_paiement_eleve_confirm_delete', methods: ['GET', 'POST'])]
    public function confirmDelete(PaiementEleve $paiementEleve, Request $request, Etablissement $etablissement): Response
    {
        $param = $request->request->get('param'); // Récupération du paramètre

        if ($param === 'simple') {
            // Code spécifique pour le paramètre "simple"
            $route_suppression = $this->generateUrl('app_gandaal_administration_comptabilite_paiement_eleve_delete', [
                'id' => $paiementEleve->getId(),
                'etablissement' => $etablissement->getId()
            ]);
        }elseif ($param === 'general') {
            // Code pour d'autres valeurs de paramètre ou défaut
            $route_suppression = $this->generateUrl('app_gandaal_administration_comptabilite_paiement_eleve_delete_liaison', [
                'id' => $paiementEleve->getId(),
                'etablissement' => $etablissement->getId()
            ]);
        }

        return $this->render('gandaal/administration/comptabilite/paiement_eleve/confirm_delete.html.twig', [
            'paiementEleve' => $paiementEleve,
            'etablissement' => $etablissement,
            'route_suppression' => $route_suppression,
            'param' => $param,
        ]);
    }

    #[Route('/delete/{id}/{etablissement}', name: 'app_gandaal_administration_comptabilite_paiement_eleve_delete', methods: ['POST'])]
    public function delete(Request $request, Etablissement $etablissement, SessionInterface $session, PaiementEleve $paiementEleve, EntityManagerInterface $entityManager, CsrfTokenManagerInterface $csrfTokenManager): Response
    {
        // Générer le token attendu pour comparaison
        $expectedCsrfToken = $csrfTokenManager->getToken('delete'.$paiementEleve->getId())->getValue();

        
        // Vérification du token CSRF
        if ($this->isCsrfTokenValid('delete'.$paiementEleve->getId(), $request->request->get('_token'))) {
            // Récupérer le motif de suppression
            $deleteReason = $request->request->get('delete_reason');
            $information = 'ref '.$paiementEleve->getReference().' '.number_format($paiementEleve->getMontant(),0,',',' ');
            $historique = new HistoriqueSuppression();
            $historique->setType('paiement frais scolarité') // ou un type plus spécifique
                ->setMotif($deleteReason)
                ->setOrigine('comptabilite')
                ->setDateOperation(new \DateTime())
                ->setInformation($information)
                ->setSaisiePar($this->getUser())
                ->setPromo($session->get('promo'))
                ->setUser($paiementEleve->getInscription()->getEleve());
                
            $entityManager->persist($historique);
            
            // Suppression de l'entité
            $entityManager->remove($paiementEleve);
            $entityManager->flush();
        }

        // Redirection après suppression
        return $this->redirectToRoute('app_gandaal_administration_comptabilite_paiement_eleve_new', [
            'etablissement' => $etablissement->getId(),
            'id_user_search' => $paiementEleve->getInscription()->getEleve()->getId()
        ], Response::HTTP_SEE_OTHER);
    }


    #[Route('/delete/liaison/{id}/{etablissement}', name: 'app_gandaal_administration_comptabilite_paiement_eleve_delete_liaison', methods: ['POST'])]
    public function deleteLiaison(Request $request, Etablissement $etablissement, PaiementEleve $paiementEleve, PaiementEleveRepository $paiementRep, SessionInterface $session, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$paiementEleve->getId(), $request->getPayload()->getString('_token'))) {
            $reference = $paiementEleve->getReference();
            $paiements = $paiementRep->findBy(['reference' => $reference]);
            $deleteReason = $request->request->get('delete_reason');
            
            foreach ($paiements as $paiement) {
                $information = 'ref '.$paiement->getReference().' '.number_format($paiement->getMontant(),0,',',' ');
                $historique = new HistoriqueSuppression();
                $historique->setType('paiement frais scolarité') // ou un type plus spécifique
                    ->setMotif($deleteReason)
                    ->setOrigine('comptabilite')
                    ->setDateOperation(new \DateTime())
                    ->setInformation($information)
                    ->setSaisiePar($this->getUser())
                    ->setPromo($session->get('promo'))
                    ->setUser($paiement->getInscription()->getEleve());
                $entityManager->persist($historique);
                $entityManager->remove($paiement);
            }
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_gandaal_administration_comptabilite_paiement_eleve_new', ['etablissement' => $etablissement->getId(), 'id_user_search' => $paiementEleve->getInscription()->getEleve()->getId()], Response::HTTP_SEE_OTHER);
    }


    #[Route('/historique/suppression/{etablissement}', name: 'app_gandaal_administration_comptabilite_paiement_eleve_historique_suppression', methods: ['GET'])]
    public function historiqueSuppression(Request $request, HistoriqueSuppressionRepository $historiqueRep, SessionInterface $session, Etablissement $etablissement): Response
    {
        if ($request->get("search")){
            $search = $request->get("search");
        }else{
            $search = "";
        }

        $firstOp = $historiqueRep->findOneBy(['promo' => $session->get('promo')], ['dateOperation' => 'ASC']);
        $date1 = $request->get("date1") ? $request->get("date1") : ($firstOp ? $firstOp->getDateOperation()->format('Y-m-d') : $request->get("date1"));
        $date2 = $request->get("date2") ? $request->get("date2") : date("Y-m-d");

        $pageEncours = $request->get('pageEncours', 1);
        
        $historiques = $historiqueRep->historiqueParEtablissementParOrigineParPeriodePaginated($search, 'comptabilite', $etablissement, $date1, $date2, $pageEncours, 25);

        
        return $this->render('gandaal/administration/comptabilite/paiement_eleve/historique_suppression.html.twig', [
            'historiques' => $historiques,
            'etablissement' => $etablissement,
            'search' => $search,
            'date1' => $date1,
            'date2' => $date2,

        ]);
    }
}
