<?php

namespace App\Controller\Gandaal\Administration\Comptabilite;

use App\Entity\Etablissement;
use App\Entity\TransfertFond;
use App\Entity\MouvementCaisse;
use App\Form\TransfertFondType;
use App\Repository\CaisseRepository;
use App\Entity\MouvementCollaborateur;
use App\Repository\EntrepriseRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\TransfertFondRepository;
use Symfony\Component\Filesystem\Filesystem;
use App\Repository\MouvementCaisseRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\TransfertProductsRepository;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\ConfigCategorieOperationRepository;
use App\Repository\ConfigCaisseRepository;
use App\Repository\ConfigCompteOperationRepository;
use App\Repository\ConfigModePaiementRepository;
use App\Repository\ModePaiementRepository;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

#[Route('/gandaal/administration/comptabilite/transfert/fond')]
class TransfertFondController extends AbstractController
{
    #[Route('/accueil/{etablissement}', name: 'app_gandaal_administration_comptabilite_transfert_fond_index', methods: ['GET'])]
    public function index(TransfertFondRepository $transfertFondRepository, Request $request, ConfigCaisseRepository $caisseRep, Etablissement $etablissement, SessionInterface $session, EntrepriseRepository $entrepriseRep): Response
    {

        if ($request->get("search")){
            $search = $caisseRep->find($request->get("search"));
        }else{
            $search = "";
        }

        $firstOp = $transfertFondRepository->findOneBy(['promo' => $session->get('promo')], ['dateOperation' => 'ASC']);
        $date1 = $request->get("date1") ? $request->get("date1") : ($firstOp ? $firstOp->getDateOperation()->format('Y-m-d') : $request->get("date1"));
        $date2 = $request->get("date2") ? $request->get("date2") : date("Y-m-d");

        $pageEncours = $request->get('pageEncours', 1);
        if ($request->get("search")){
            $transferts = $transfertFondRepository->findTransfertByEtablissementBySearchPaginated($etablissement, $search, $session->get('promo'), $date1, $date2, $pageEncours, 25);
        }else{
            $transferts = $transfertFondRepository->findTransfertByEtablissementPaginated($etablissement, $session->get('promo'), $date1, $date2, $pageEncours, 25);
        }

        return $this->render('gandaal/administration/comptabilite/transfert_fond/index.html.twig', [
            'transfert_fonds' => $transferts,
            'etablissement' => $etablissement,
            'search' => $search,
            'liste_caisse' => $caisseRep->findBy(['etablissement' => $etablissement]),
            'date1' => $date1,
            'date2' => $date2,
        ]);
    }

    #[Route('/new/{etablissement}', name: 'app_gandaal_administration_comptabilite_transfert_fond_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, Etablissement $etablissement, TransfertFondRepository $transfertRep, MouvementCaisseRepository $mouvementRep, ConfigCompteOperationRepository $compteOpRep, ConfigModePaiementRepository $modePaieRep, ConfigCaisseRepository $caisseRep, SessionInterface $session, ConfigCategorieOperationRepository $catetgorieOpRep, EntrepriseRepository $entrepriseRep): Response
    {
        $transfertFond = new TransfertFond();
        $form = $this->createForm(TransfertFondType::class, $transfertFond, ['etablissement' => $etablissement]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $montantString = $form->get('montant')->getData();
            $montantString = preg_replace('/[^0-9]/', '', $montantString);
            $montant = floatval($montantString);
            $dateDuJour = new \DateTime();
            $referenceDate = $dateDuJour->format('ymd');
            $idSuivant =($transfertRep->findCountId($etablissement, $session->get('promo')) + 1);
            $reference = "trans".$referenceDate . sprintf('%04d', $idSuivant);

            $caisse_depart = $form->getViewData()->getCaisse();
            $caisse_recep = $form->getViewData()->getCaisseReception();
            // $caisse_recep = $caisseRep->find($request->get('caisse_reception'));
            $devise = $form->getViewData()->getDevise();
            

            if (empty($caisse_depart) and empty($caisse_recep)) {
                $this->addFlash("warning", "vous devez selectionner au moins une caisse");
                // Récupérer l'URL de la page précédente
                $referer = $request->headers->get('referer');
                if ($referer) {
                    $formView = $form->createView();
                    return $this->render('gandaal/administration/comptabilite/transfert_fond/new.html.twig', [
                        'entreprise' => $entrepriseRep->find(1),
                        'etablissement' => $etablissement,
                        'form' => $formView,
                        'transfert_fond' => $transfertFond,
                        'referer' => $referer,
                    ]);
                }
            }else{

                $solde_caisse = $caisse_depart ? $mouvementRep->findSoldeCaisseByPromo($caisse_depart, $devise, $session->get('promo')) : 1000000000000000000000000000000;
                if ($solde_caisse >= $montant) {
                    $categorie_op = $catetgorieOpRep->find(8);
                    $compte_op = $compteOpRep->find(8);                    
                    
                    if (!empty($caisse_depart) and !empty($caisse_recep)) {
                        $transfertFond->setEtablissement($etablissement)
                            ->setSaisiePar($this->getUser())
                            ->setReference($reference)
                            ->setMontant(- $montant)
                            ->setEtatOperation("clos")
                            ->setTypeMouvement('transfert')
                            ->setDateSaisie(new \DateTime("now"))
                            ->setCategorieOperation($categorie_op)
                            ->setCompteOperation($compte_op)
                            ->setTaux(1)
                            ->setPromo($session->get('promo'));    
                        $fichier = $form->get("document")->getData();
                        if ($fichier) {
                            $nomFichier= pathinfo($fichier->getClientOriginalName(), PATHINFO_FILENAME);
                            $slugger = new AsciiSlugger();
                            $nouveauNomFichier = $slugger->slug($nomFichier);
                            $nouveauNomFichier .="_".uniqid();
                            $nouveauNomFichier .= "." .$fichier->guessExtension();
                            $fichier->move($this->getParameter("dossier_transferts"),$nouveauNomFichier);
                            $transfertFond->setDocument($nouveauNomFichier);
                        }

                        $entityManager->persist($transfertFond);

                        $transfert_recep = new TransfertFond();
                        $transfert_recep->setEtablissement($etablissement)
                                ->setCaisse($transfertFond->getCaisseReception())
                                ->setCaisseReception($transfertFond->getCaisse())
                                ->setDevise($transfertFond->getDevise())
                                ->setModePaie($transfertFond->getModePaie())
                                ->setSaisiePar($this->getUser())
                                ->setReference($reference)
                                ->setMontant($montant)
                                ->setDescription($transfertFond->getDescription())
                                ->setDateOperation($transfertFond->getDateOperation())
                                ->setEtatOperation("clos")
                                ->setTypeMouvement('transfert')
                                ->setDateSaisie(new \DateTime("now"))
                                ->setCategorieOperation($categorie_op)
                                ->setCompteOperation($compte_op)
                                ->setTaux(1)
                                ->setPromo($session->get('promo'));
                        $entityManager->persist($transfert_recep);
                    }elseif (empty($caisse_depart)) {

                        $transfert_recep = new TransfertFond();
                        $transfert_recep->setEtablissement($etablissement)
                                ->setCaisse($transfertFond->getCaisseReception())
                                ->setCaisseReception($transfertFond->getCaisse())
                                ->setDevise($transfertFond->getDevise())
                                ->setModePaie($transfertFond->getModePaie())
                                ->setSaisiePar($this->getUser())
                                ->setReference($reference)
                                ->setMontant($montant)
                                ->setDescription($transfertFond->getDescription())
                                ->setDateOperation($transfertFond->getDateOperation())
                                ->setEtatOperation("clos")
                                ->setTypeMouvement('transfert')
                                ->setDateSaisie(new \DateTime("now"))
                                ->setCategorieOperation($categorie_op)
                                ->setCompteOperation($compte_op)
                                ->setTaux(1)
                                ->setPromo($session->get('promo'));
                        $entityManager->persist($transfert_recep);
                        
                    }else{

                        $transfertFond->setEtablissement($etablissement)
                            ->setSaisiePar($this->getUser())
                            ->setReference($reference)
                            ->setMontant(- $montant)
                            ->setEtatOperation("clos")
                            ->setTypeMouvement('transfert')
                            ->setDateSaisie(new \DateTime("now"))
                            ->setCategorieOperation($categorie_op)
                            ->setCompteOperation($compte_op)
                            ->setTaux(1)
                            ->setPromo($session->get('promo'));    
                        $fichier = $form->get("document")->getData();
                        if ($fichier) {
                            $nomFichier= pathinfo($fichier->getClientOriginalName(), PATHINFO_FILENAME);
                            $slugger = new AsciiSlugger();
                            $nouveauNomFichier = $slugger->slug($nomFichier);
                            $nouveauNomFichier .="_".uniqid();
                            $nouveauNomFichier .= "." .$fichier->guessExtension();
                            $fichier->move($this->getParameter("dossier_transferts"),$nouveauNomFichier);
                            $transfertFond->setDocument($nouveauNomFichier);
                        }

                        $entityManager->persist($transfertFond);

                    }
                    $entityManager->flush();
    
                    $this->addFlash("success", "transfert enregistré avec succès :)");
                    return $this->redirectToRoute('app_gandaal_administration_comptabilite_transfert_fond_index', ['etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
                }else{
                    $this->addFlash("warning", "Le montant disponible en caisse est insuffisant");
                    // Récupérer l'URL de la page précédente
                    $referer = $request->headers->get('referer');
                    if ($referer) {
                        $formView = $form->createView();
                        return $this->render('gandaal/administration/comptabilite/transfert_fond/new.html.twig', [
                            'etablissement' => $etablissement,
                            'form' => $formView,
                            'transfert_fond' => $transfertFond,
                            'referer' => $referer,
                        ]);
                    }
                }
            }


            return $this->redirectToRoute('app_gandaal_administration_comptabilite_transfert_fond_index', ['etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/administration/comptabilite/transfert_fond/new.html.twig', [
            'transfert_fond' => $transfertFond,
            'form' => $form,
            'entreprise' => $entrepriseRep->find(1),
            'etablissement' => $etablissement,
        ]);
    }

    #[Route('/show/{id}/{etablissement}', name: 'app_gandaal_administration_comptabilite_transfert_fond_show', methods: ['GET'])]
    public function show(TransfertFond $transfertFond, Etablissement $etablissement, EntrepriseRepository $entrepriseRep): Response
    {
        return $this->render('gandaal/administration/comptabilite/transfert_fond/show.html.twig', [
            'transfert_fond' => $transfertFond,
            'entreprise' => $entrepriseRep->find(1),
            'etablissement' => $etablissement,
        ]);
    }

    #[Route('/edit/{id}/{etablissement}', name: 'app_gandaal_administration_comptabilite_transfert_fond_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, TransfertFond $transfertFond, EntityManagerInterface $entityManager, Etablissement $etablissement, EntrepriseRepository $entrepriseRep, ConfigCompteOperationRepository $compteOpRep, ConfigCategorieOperationRepository $catetgorieOpRep, SessionInterface $session, TransfertFondRepository $transfertRep, MouvementCaisseRepository $mouvementRep): Response
    {
        $form = $this->createForm(TransfertFondType::class, $transfertFond, ['etablissement' => $etablissement]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $montantString = $form->get('montant')->getData();
            $montantString = preg_replace('/[^0-9]/', '', $montantString);
            $montant = floatval($montantString);
            $dateDuJour = new \DateTime();
            $referenceDate = $dateDuJour->format('ymd');
            $idSuivant =($transfertRep->findCountId($etablissement, $session->get('promo')) + 1);
            $reference = "trans".$referenceDate . sprintf('%04d', $idSuivant);

            $caisse_depart = $form->getViewData()->getCaisse();
            $caisse_recep = $form->getViewData()->getCaisseReception();
            $devise = $form->getViewData()->getDevise();

            if (empty($caisse_depart) and empty($caisse_recep)) {
                $this->addFlash("warning", "vous devez saisir au moins une caisse");
                // Récupérer l'URL de la page précédente
                $referer = $request->headers->get('referer');
                if ($referer) {
                    $formView = $form->createView();
                    return $this->render('gandaal/administration/comptabilite/transfert_fond/new.html.twig', [
                        'entreprise' => $entrepriseRep->find(1),
                        'etablissement' => $etablissement,
                        'form' => $formView,
                        'transfert_fond' => $transfertFond,
                        'referer' => $referer,
                    ]);
                }
            }else{
                $transferts = $transfertRep->findBy(['reference' => $transfertFond->getReference()]); 

                foreach ($transferts as $transfert) {
                    $entityManager->remove($transfert);
                }

                $categorie_op = $catetgorieOpRep->find(8);
                $compte_op = $compteOpRep->find(8);  
                $solde_caisse = $caisse_depart ? $mouvementRep->findSoldeCaisseByPromo($caisse_depart, $devise, $session->get('promo')) : 1000000000000000000000000000000;

                if ($solde_caisse >= $montant) {
                    if (!empty($caisse_depart) and !empty($caisse_recep)) {
                        $transfertFond->setEtablissement($etablissement)
                            ->setSaisiePar($this->getUser())
                            ->setReference($reference)
                            ->setMontant(- $montant)
                            ->setEtatOperation("clos")
                            ->setTypeMouvement('transfert')
                            ->setDateSaisie(new \DateTime("now"))
                            ->setTaux(1)
                            ->setPromo($session->get('promo'));    
                        $justificatif =$form->get("document")->getData();
                        if ($justificatif) {
                            if ($transfertFond->getDocument()) {
                                $ancienJustificatif=$this->getParameter("dossier_transferts")."/".$transfertFond->getDocument();
                                if (file_exists($ancienJustificatif)) {
                                    unlink($ancienJustificatif);
                                }
                            }
                            $nomJustificatif= pathinfo($justificatif->getClientOriginalName(), PATHINFO_FILENAME);
                            $slugger = new AsciiSlugger();
                            $nouveauNomJustificatif = $slugger->slug($nomJustificatif);
                            $nouveauNomJustificatif .="_".uniqid();
                            $nouveauNomJustificatif .= "." .$justificatif->guessExtension();
                            $justificatif->move($this->getParameter("dossier_transferts"),$nouveauNomJustificatif);
                            $transfertFond->setDocument($nouveauNomJustificatif);
        
                        }

                        $entityManager->persist($transfertFond);

                        $transfert_recep = new TransfertFond();
                        $transfert_recep->setEtablissement($etablissement)
                                ->setCaisse($transfertFond->getCaisseReception())
                                ->setCaisseReception($transfertFond->getCaisse())
                                ->setDevise($transfertFond->getDevise())
                                ->setModePaie($transfertFond->getModePaie())
                                ->setSaisiePar($this->getUser())
                                ->setReference($reference)
                                ->setMontant($montant)
                                ->setDescription($transfertFond->getDescription())
                                ->setDateOperation($transfertFond->getDateOperation())
                                ->setEtatOperation("clos")
                                ->setTypeMouvement('transfert')
                                ->setDateSaisie(new \DateTime("now"))
                                ->setCategorieOperation($categorie_op)
                                ->setCompteOperation($compte_op)
                                ->setTaux(1)
                                ->setPromo($session->get('promo'));
                        $entityManager->persist($transfert_recep);
                    }elseif (empty($caisse_depart)) {

                        $transfert_recep = new TransfertFond();
                        $transfert_recep->setEtablissement($etablissement)
                                ->setCaisse($transfertFond->getCaisseReception())
                                ->setCaisseReception($transfertFond->getCaisse())
                                ->setDevise($transfertFond->getDevise())
                                ->setModePaie($transfertFond->getModePaie())
                                ->setSaisiePar($this->getUser())
                                ->setReference($reference)
                                ->setMontant($montant)
                                ->setDescription($transfertFond->getDescription())
                                ->setDateOperation($transfertFond->getDateOperation())
                                ->setEtatOperation("clos")
                                ->setTypeMouvement('transfert')
                                ->setDateSaisie(new \DateTime("now"))
                                ->setCategorieOperation($categorie_op)
                                ->setCompteOperation($compte_op)
                                ->setTaux(1)
                                ->setPromo($session->get('promo'));
                        $entityManager->persist($transfert_recep);
                        
                    }else{

                        $transfertFond->setEtablissement($etablissement)
                            ->setSaisiePar($this->getUser())
                            ->setReference($reference)
                            ->setMontant(- $montant)
                            ->setEtatOperation("clos")
                            ->setTypeMouvement('transfert')
                            ->setDateSaisie(new \DateTime("now"))
                            ->setCategorieOperation($categorie_op)
                            ->setCompteOperation($compte_op)
                            ->setTaux(1)
                            ->setPromo($session->get('promo'));    
                        $fichier = $form->get("document")->getData();
                        if ($fichier) {
                            $nomFichier= pathinfo($fichier->getClientOriginalName(), PATHINFO_FILENAME);
                            $slugger = new AsciiSlugger();
                            $nouveauNomFichier = $slugger->slug($nomFichier);
                            $nouveauNomFichier .="_".uniqid();
                            $nouveauNomFichier .= "." .$fichier->guessExtension();
                            $fichier->move($this->getParameter("dossier_transferts"),$nouveauNomFichier);
                            $transfertFond->setDocument($nouveauNomFichier);
                        }

                        $entityManager->persist($transfertFond);
        
                    }
                    $entityManager->flush();
    
                    $this->addFlash("success", "transfert modifié avec succès :)");
                    return $this->redirectToRoute('app_gandaal_administration_comptabilite_transfert_fond_index', ['etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
                }else{
                    $this->addFlash("warning", "Le montant disponible en caisse est insuffisant");
                    // Récupérer l'URL de la page précédente
                    $referer = $request->headers->get('referer');
                    if ($referer) {
                        $formView = $form->createView();
                        return $this->render('gandaal/administration/comptabilite/transfert_fond/new.html.twig', [
                            'entreprise' => $entrepriseRep->find(1),
                            'etablissement' => $etablissement,
                            'form' => $formView,
                            'transfert_fond' => $transfertFond,
                            'referer' => $referer,
                        ]);
                    }
                }
            }


            return $this->redirectToRoute('app_gandaal_administration_comptabilite_transfert_fond_index', ['etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/administration/comptabilite/transfert_fond/edit.html.twig', [
            'transfert_fond' => $transfertFond,
            'form' => $form,
            'entreprise' => $entrepriseRep->find(1),
            'etablissement' => $etablissement,
        ]);
    }

    #[Route('/delete/{id}/{etablissement}', name: 'app_gandaal_administration_comptabilite_transfert_fond_delete', methods: ['POST'])]
    public function delete(Request $request, TransfertFond $transfertFond, TransfertFondRepository $transfertRep, EntityManagerInterface $entityManager, Filesystem $filesystem, Etablissement $etablissement, EntrepriseRepository $entrepriseRep): Response
    {
        if ($this->isCsrfTokenValid('delete'.$transfertFond->getId(), $request->request->get('_token'))) {

            $justificatif = $transfertFond->getDocument();
            $pdfPath = $this->getParameter("dossier_transferts") . '/' . $justificatif;
            // Si le chemin du justificatif existe, supprimez également le fichier
            if ($justificatif && $filesystem->exists($pdfPath)) {
                $filesystem->remove($pdfPath);
            }

            $transferts = $transfertRep->findBy(['reference' => $transfertFond->getReference()]);
            foreach ($transferts as $transfert) {
                $entityManager->remove($transfert);
            }
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_gandaal_administration_comptabilite_transfert_fond_index', ['etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
    }
}
