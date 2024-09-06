<?php

namespace App\Controller\Gandaal\Administration\Comptabilite;

use App\Entity\Etablissement;
use App\Repository\UserRepository;
use App\Entity\InscriptionActivite;
use App\Repository\EleveRepository;
use App\Entity\HistoriqueSuppression;
use App\Form\InscriptionActiviteType;
use App\Entity\PaiementActiviteScolaire;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\ConfigCaisseRepository;
use App\Repository\ConfigDeviseRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\ConfigModePaiementRepository;
use App\Repository\InscriptionActiviteRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Repository\ConfigCompteOperationRepository;
use App\Repository\TarifActiviteScolaireRepository;
use App\Repository\ConfigActiviteScolaireRepository;
use App\Repository\ConfigCategorieOperationRepository;
use App\Repository\PaiementActiviteScolaireRepository;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/gandaal/administration/comptabilite/paiement/activite')]
class PaiementActiviteController extends AbstractController
{
    #[Route('/accueil/{etablissement}', name: 'app_gandaal_administration_comptabilite_paiement_activite_index', methods: ['GET'])]
    public function index(PaiementActiviteScolaireRepository $paiementRep, SessionInterface $session, EleveRepository $eleveRep, ConfigActiviteScolaireRepository $activiteRep, Request $request, etablissement $etablissement): Response
    {
        if ($request->get("id_user_search")){
            $search = $request->get("id_user_search");
        }else{
            $search = "";
        }

        if ($request->get("activite")){
            $activite = $activiteRep->find($request->get("activite"));
        }else{
            $activite = "";
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
            $paiements_activite = $paiementRep->findPaiementActiviteByEtablissementBySearchPaginated($etablissement, $search, $session->get('promo'), $date1, $date2, $pageEncours, 25);
        }elseif ($request->get("activite")){
            $paiements_activite = $paiementRep->findPaiementActiviteByEtablissementByActivitePaginated($etablissement, $activite, $session->get('promo'), $date1, $date2, $pageEncours, 25);
        }else{
            $paiements_activite = $paiementRep->findPaiementActiviteByEtablissementPaginated($etablissement, $session->get('promo'), $date1, $date2, $pageEncours, 25);
        }

        return $this->render('gandaal/administration/comptabilite/paiement_activite/index.html.twig', [
            'paiements_activite' => $paiements_activite,
            'search' => $search,            
            'etablissement' => $etablissement,
            'date1' => $date1,
            'date2' => $date2,
            'activites' => $activiteRep->findBy(['etablissement' => $etablissement]),
            'activite' => $activite
        ]);
    }

    #[Route('/new/{etablissement}', name: 'app_gandaal_administration_comptabilite_paiement_activite_new', methods: ['GET', 'POST'])]
    public function new(Request $request, Etablissement $etablissement, PaiementActiviteScolaireRepository $paiementActiviteScolaireRep, SessionInterface $session, InscriptionActiviteRepository $inscriptionActiviteRep, TarifActiviteScolaireRepository $tarifScolariteRep, UserRepository $userRep, EleveRepository $eleveRep, ConfigCaisseRepository $caisseRep, ConfigModePaiementRepository $modePaieRep, ConfigDeviseRepository $deviseRep, ConfigCompteOperationRepository $compteOpRep, ConfigCategorieOperationRepository $categorieOpRep, EntityManagerInterface $entityManager): Response
    {

        if ($request->get("id_user_search")){
            $search = $userRep->find($request->get("id_user_search"));  
        }else{
            $search = "";
        }
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

        if ($request->get('paiement_eleve')) {
            $periode = $request->get('periode');
            $caisse = $caisseRep->find($request->get('caisse'));
            $devise = $deviseRep->find($request->get('devise'));
            $modePaie = $modePaieRep->find($request->get('modePaie'));
            $montantSaisie = floatval(preg_replace('/[^0-9,.]/', '', $request->get('montant')));
            $datePaiement = new \DateTime($request->get('datePaiement'));
            $banquePaie = $request->get('banquePaie');
            $numeroPaie = $request->get('numeroPaie');
            $taux = $request->get('taux');

            $currentYear = (new \DateTime())->format('ymd');
            $maxPaie = $paiementActiviteScolaireRep->findMaxId($etablissement, $session->get('promo'));
            $formattedMaxPaie = sprintf('%04d', $maxPaie + 1); 
            $generatedReference = $currentYear . $formattedMaxPaie;
            $reference = 'act'.$generatedReference;

            $periodes = $request->get('periodes');
            foreach ($periodes as $periode) {               
                $tarif = $tarifScolariteRep->find($request->get('tarif'));
                $montant = $montantSaisie ? $montantSaisie : $tarif->getMontant();
                $inscription = $inscriptionActiviteRep->findOneBy(['eleve' => $search, 'tarifActivite' => $tarif, 'promo' => $session->get('promo')]);

                $remise = $inscription->getRemise() ? $inscription->getRemise() : 0;

                $montant_remise = $montant* (1 - ($remise / 100));
                $tarif_remise = $tarif->getMontant()* (1 - ($remise / 100));


                $cumcul_paie = $paiementActiviteScolaireRep->cumulPaiementEleve($inscription, $periode, $session->get('promo'));
                
                if ($tarif->getType() == 'annuel') {
                    if (($cumcul_paie + $montant_remise) <= $tarif_remise) {
                        $paiement = new PaiementActiviteScolaire();
                        $paiement->setCaisse($caisse)
                            ->setCategorieOperation($categorieOpRep->find(1))
                            ->setCompteOperation($compteOpRep->find(6))
                            ->setDevise($devise)
                            ->setModePaie($modePaie)
                            ->setTaux($taux)
                            ->setMontant($montant_remise)
                            ->setNumeroPaie($numeroPaie)
                            ->setBanquePaie($banquePaie)
                            ->setTypeMouvement('activite')
                            ->setEtatOperation('clos')
                            ->setDateOperation($datePaiement ? $datePaiement : new \DateTime("now"))
                            ->setDateSaisie(new \DateTime("now"))
                            ->setSaisiePar($this->getUser())
                            ->setEtablissement($etablissement)
                            ->setPromo($session->get('promo'))
                            ->setPeriode('annuel')
                            ->setReference($reference)
                            ->setInscription($inscription);
                    }else{
                        $this->addFlash("warning", "Paiement refusé) ");
                        $referer = $request->headers->get('referer');
                        return $this->redirect($referer);

                    }              
                }

                if ($tarif->getType() == 'mensuel') {
                    if (($cumcul_paie + $montant_remise) <= $tarif_remise) {
                        
                        $paiement = new PaiementActiviteScolaire();
                        $paiement->setCaisse($caisse)
                            ->setCategorieOperation($categorieOpRep->find(1))
                            ->setCompteOperation($compteOpRep->find(6))
                            ->setDevise($devise)
                            ->setModePaie($modePaie)
                            ->setTaux($taux)
                            ->setMontant($montant_remise)
                            ->setNumeroPaie($numeroPaie)
                            ->setBanquePaie($banquePaie)
                            ->setTypeMouvement('activite')
                            ->setEtatOperation('clos')
                            ->setDateOperation($datePaiement ? $datePaiement : new \DateTime("now"))
                            ->setDateSaisie(new \DateTime("now"))
                            ->setSaisiePar($this->getUser())
                            ->setEtablissement($etablissement)
                            ->setPromo($session->get('promo'))
                            ->setPeriode($periode)
                            ->setReference($reference)
                            ->setInscription($inscription);  

                    }else{
                        $this->addFlash("warning", "Paiement refusé) ");
                        $referer = $request->headers->get('referer');
                        return $this->redirect($referer);

                    }                
                }

                $entityManager->persist($paiement);
            }
            $entityManager->flush();
            $this->addFlash("success", "Paiement enregistré avec succés :) ");
            $referer = $request->headers->get('referer');
            return $this->redirect($referer);

        }


        $eleve = $eleveRep->find($search);
        $inscriptions = $inscriptionActiviteRep->findBy(['eleve' => $search, 'promo' => $session->get('promo')]);
        $historiques = $paiementActiviteScolaireRep->findBy(['inscription' => $inscriptions, 'promo' => $session->get('promo')]);

        $caisses = $caisseRep->findBy(['etablissement' => $etablissement]);
        $devises = $deviseRep->findAll();
        $modePaies = $modePaieRep->findAll();
        return $this->render('gandaal/administration/comptabilite/paiement_activite/new.html.twig', [
            'eleve' => $eleve,
            'etablissement' => $etablissement,
            'search' => $search,
            'caisses' => $caisses,
            'devises' => $devises,
            'modePaies' => $modePaies,
            'dernier_promo' => $session->get('promo'),
            'inscriptions' => $inscriptions,
            'historiques' => $historiques
        ]);
    }

    #[Route('/show/{id}/{etablissement}', name: 'app_gandaal_administration_comptabilite_paiement_activite_show', methods: ['GET'])]
    public function show(PaiementActiviteScolaire $paiementEleve, Etablissement $etablissement, PaiementActiviteScolaireRepository $paiementRep): Response
    {
        $paiement_lies = $paiementRep->paiementEleveLies($paiementEleve->getReference(), $paiementEleve);
        return $this->render('gandaal/administration/comptabilite/paiement_activite/show.html.twig', [
            'paiement_eleve' => $paiementEleve,
            'etablissement' => $etablissement,
            'paiement_lies' => $paiement_lies,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_gandaal_administration_comptabilite_paiement_activite_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, InscriptionActivite $inscription, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(InscriptionActiviteType::class, $inscription);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_gandaal_administration_comptabilite_paiement_activite_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/administration/scolarite/inscription_activite/edit.html.twig', [
            'inscription' => $inscription,
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

    #[Route('/confirm/delete/{id}/{etablissement}', name: 'app_gandaal_administration_comptabilite_paiement_activite_confirm_delete', methods: ['GET', 'POST'])]
    public function confirmDelete(PaiementActiviteScolaire $paiementEleve, Request $request, Etablissement $etablissement): Response
    {
        $param = $request->request->get('param'); // Récupération du paramètre

        if ($param === 'simple') {
            // Code spécifique pour le paramètre "simple"
            $route_suppression = $this->generateUrl('app_gandaal_administration_comptabilite_paiement_activite_delete', [
                'id' => $paiementEleve->getId(),
                'etablissement' => $etablissement->getId()
            ]);
        }elseif ($param === 'general') {
            // Code pour d'autres valeurs de paramètre ou défaut
            $route_suppression = $this->generateUrl('app_gandaal_administration_comptabilite_paiement_activite_delete_liaison', [
                'id' => $paiementEleve->getId(),
                'etablissement' => $etablissement->getId()
            ]);
        }

        return $this->render('gandaal/administration/comptabilite/paiement_activite/confirm_delete.html.twig', [
            'paiementEleve' => $paiementEleve,
            'etablissement' => $etablissement,
            'route_suppression' => $route_suppression,
            'param' => $param,
        ]);
    }

    #[Route('/delete/{id}/{etablissement}', name: 'app_gandaal_administration_comptabilite_paiement_activite_delete', methods: ['POST'])]
    public function delete(Request $request, SessionInterface $session, PaiementActiviteScolaire $paiementEleve, PaiementActiviteScolaireRepository $paiementActiviteScolaireRep, Etablissement $etablissement, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$paiementEleve->getId(), $request->getPayload()->getString('_token'))) {
            $deleteReason = $request->request->get('delete_reason');
            $information = 'ref '.$paiementEleve->getReference().' '.number_format($paiementEleve->getMontant(),0,',',' ');
            $historique = new HistoriqueSuppression();
            $historique->setType('paiement activité') // ou un type plus spécifique
                ->setMotif($deleteReason)
                ->setOrigine('comptabilite')
                ->setDateOperation(new \DateTime())
                ->setInformation($information)
                ->setSaisiePar($this->getUser())
                ->setPromo($session->get('promo'))
                ->setUser($paiementEleve->getInscription()->getEleve());
            $entityManager->persist($historique);
            $entityManager->remove($paiementEleve);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_gandaal_administration_comptabilite_paiement_activite_new', ['etablissement' => $etablissement->getId(), 'id_user_search' => $paiementEleve->getInscription()->getEleve()->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/delete/liaison/{id}/{etablissement}', name: 'app_gandaal_administration_comptabilite_paiement_activite_delete_liaison', methods: ['POST'])]
    public function deleteLiaison(Request $request, SessionInterface $session, PaiementActiviteScolaire $paiementEleve, PaiementActiviteScolaireRepository $paiementActiviteScolaireRep, Etablissement $etablissement, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$paiementEleve->getId(), $request->getPayload()->getString('_token'))) {
            $reference = $paiementEleve->getReference();
            $paiements = $paiementActiviteScolaireRep->findBy(['reference' => $reference]);
            $deleteReason = $request->request->get('delete_reason');

            foreach ($paiements as $paiement) {
                $information = 'ref '.$paiement->getReference().' montant'.number_format($paiement->getMontant(),0,',',' ');
                $historique = new HistoriqueSuppression();
                $historique->setType('paiement activité') // ou un type plus spécifique
                    ->setMotif($deleteReason)
                    ->setDateOperation(new \DateTime())
                    ->setOrigine('comptabilite')
                    ->setInformation($information)
                    ->setSaisiePar($this->getUser())
                    ->setPromo($session->get('promo'))
                    ->setUser($paiement->getInscription()->getEleve());
                $entityManager->persist($historique);
                $entityManager->remove($paiement);
                $entityManager->remove($paiement);
            }
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_gandaal_administration_comptabilite_paiement_activite_new', ['etablissement' => $etablissement->getId(), 'id_user_search' => $paiementEleve->getInscription()->getEleve()->getId()], Response::HTTP_SEE_OTHER);
    }
}
