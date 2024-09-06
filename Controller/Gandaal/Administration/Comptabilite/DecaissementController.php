<?php

namespace App\Controller\Gandaal\Administration\Comptabilite;

use Dompdf\Dompdf;
use Dompdf\Options;
use App\Entity\Client;
use App\Entity\Etablissement;
use App\Entity\Decaissement;
use App\Entity\Modification;
use App\Form\DecaissementType;
use App\Entity\MouvementCaisse;
use App\Entity\ModifDecaissement;
use App\Entity\DeleteDecaissement;
use App\Repository\UserRepository;
use App\Entity\MouvementCollaborateur;
use App\Repository\EntrepriseRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\etablissementRepository;
use App\Repository\DecaissementRepository;
use App\Repository\ModificationRepository;
use Symfony\Component\Filesystem\Filesystem;
use App\Repository\ConfigCompteOperationRepository;
use App\Repository\MouvementCaisseRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\ModifDecaissementRepository;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\ConfigCategorieOperationRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\String\Slugger\AsciiSlugger;
use App\Repository\MouvementCollaborateurRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

#[Route('\gandaal/administration/comptabilite/decaissement')]
class DecaissementController extends AbstractController
{
    #[Route('/accueil/{etablissement}', name: 'app_gandaal_administration_comptabilite_decaissement_index', methods: ['GET'])]
    public function index(DecaissementRepository $decaissementRep, etablissement $etablissement, EntrepriseRepository $entrepriseRep, SessionInterface $session, Request $request, UserRepository $userRep): Response
    {
        if ($request->get("id_user_search")){
            $search = $request->get("id_user_search");
        }else{
            $search = "";
        }

        $firstOp = $decaissementRep->findOneBy(['promo' => $session->get('promo')], ['dateOperation' => 'ASC']);
        $date1 = $request->get("date1") ? $request->get("date1") : ($firstOp ? $firstOp->getDateOperation()->format('Y-m-d') : $request->get("date1"));
        $date2 = $request->get("date2") ? $request->get("date2") : date("Y-m-d");

        if ($request->isXmlHttpRequest()) {
            $search = $request->query->get('search');
            $users = $userRep->rechercheUserParTypeParEtablissement($search, 'personnel', $etablissement);    
            $response = [];
            foreach ($users as $user) {
                $response[] = [
                    'nom' => ucwords($user->getPrenom())." ".strtoupper($user->getNom()),
                    'id' => $user->getId()
                ]; 
            }
            return new JsonResponse($response);
        }
        $pageEncours = $request->get('pageEncours', 1);
        if ($request->get("id_user_search")){
            $decaissements = $decaissementRep->findDecaissementByEtablissementBySearchPaginated($etablissement, $search, $session->get('promo'), $date1, $date2, $pageEncours, 25);
        }else{
            $decaissements = $decaissementRep->findDecaissementByEtablissementPaginated($etablissement, $session->get('promo'), $date1, $date2, $pageEncours, 25);
        }

        return $this->render('gandaal/administration/comptabilite/decaissement/index.html.twig', [
            'decaissements' => $decaissements,
            'entreprise' => $entrepriseRep->find(1),
            'etablissement' => $etablissement,
            'search' => $search,
            'date1' => $date1,
            'date2' => $date2,

        ]);
    }

    #[Route('/new/{etablissement}', name: 'app_gandaal_administration_comptabilite_decaissement_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, etablissement $etablissement, DecaissementRepository $decaissementRep, ConfigCompteOperationRepository $compteOpRep, ConfigCategorieOperationRepository $catetgorieOpRep, MouvementCaisseRepository $mouvementRep, MouvementCollaborateurRepository $mouvementCollabRep, UserRepository $userRep, SessionInterface $session, EntrepriseRepository $entrepriseRep): Response
    {
        if ($request->get("id_user_search")){
            $client_find = $userRep->find($request->get("id_user_search"));
             $soldes_collaborateur = $mouvementCollabRep->findSoldeCollaborateur($client_find);
        }else{
            $client_find = array();
             $soldes_collaborateur = array();
        }
        
        if ($request->isXmlHttpRequest()) {
            $search = $request->query->get('search');
            $users = $userRep->rechercheUserParTypeParEtablissement($search, 'personnel', $etablissement);    
            $response = [];
            foreach ($users as $user) {
                $response[] = [
                    'nom' => ucwords($user->getPrenom())." ".strtoupper($user->getNom()),
                    'id' => $user->getId()
                ]; 
            }
            return new JsonResponse($response);
        }

        $decaissement = new Decaissement();
        $form = $this->createForm(DecaissementType::class, $decaissement, ['etablissement' => $etablissement] );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $montantString = $form->get('montant')->getData();
            // Supprimez les espaces pour obtenir un nombre valide
            $montantString = preg_replace('/[^0-9,.]/', '', $montantString);
            // Convertissez la chaîne en nombre
            $montant = floatval($montantString);
            $dateDuJour = new \DateTime();
            $referenceDate = $dateDuJour->format('ymd');
            $idSuivant =($decaissementRep->findMaxId() + 1);
            $reference = "dec".$referenceDate . sprintf('%04d', $idSuivant);
            $collaborateur = $request->get('id_collaborateur');
            $collaborateur = $userRep->find($collaborateur);
            $caisse = $form->getViewData()->getCaisse();
            $devise = $form->getViewData()->getDevise();
            $solde_caisse = $mouvementRep->findSoldeCaisse($caisse, $devise);
            if ($solde_caisse >= $montant) {
                $categorie_op = $catetgorieOpRep->find(3);
                $compte_op = $compteOpRep->find(1);
                $decaissement->setEtablissement($etablissement)
                        ->setCollaborateur($collaborateur)
                        ->setSaisiePar($this->getUser())
                        ->setReference($reference)
                        ->setMontant(- $montant)
                        ->setTaux(1)
                        ->setDateSaisie(new \DateTime("now"))
                        ->setCategorieOperation($categorie_op)
                        ->setCompteOperation($compte_op)
                        ->setTypeMouvement('decaissement')
                        ->setEtatOperation('clos')
                        ->setPromo($session->get('promo'));

                $fichier = $form->get("document")->getData();
                if ($fichier) {
                    $nomFichier= pathinfo($fichier->getClientOriginalName(), PATHINFO_FILENAME);
                    $slugger = new AsciiSlugger();
                    $nouveauNomFichier = $slugger->slug($nomFichier);
                    $nouveauNomFichier .="_".uniqid();
                    $nouveauNomFichier .= "." .$fichier->guessExtension();
                    $fichier->move($this->getParameter("dossier_decaissements"),$nouveauNomFichier);
                    $decaissement->setDocument($nouveauNomFichier);
                }

                $mouvement_collab = new MouvementCollaborateur();
                $mouvement_collab->setCollaborateur($collaborateur)
                    ->setOrigine("decaissement")
                    ->setMontant(- $montant)
                    ->setDevise($devise)
                    ->setEtablissement($etablissement)
                    ->setDateOperation($form->getViewData()->getdateOperation())
                    ->setDateSaisie(new \DateTime("now"));
                $decaissement->addMouvementCollaborateur($mouvement_collab);
                
                
                $entityManager->persist($decaissement);
                $entityManager->flush();

                $this->addFlash("success", "Décaissement enregistré avec succès :)");
                return $this->redirectToRoute('app_gandaal_administration_comptabilite_decaissement_index', ['etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
            }else{
                $this->addFlash("warning", "Le montant disponible en caisse est insuffisant");
                // Récupérer l'URL de la page précédente
                $referer = $request->headers->get('referer');
                if ($referer) {
                    $formView = $form->createView();
                    return $this->render('gandaal/administration/comptabilite/decaissement/new.html.twig', [
                        'entreprise' => $entrepriseRep->find(1),
                        'etablissement' => $etablissement,
                        'form' => $formView,
                        'decaissement' => $decaissement,
                        'referer' => $referer,
                        'client_find' => $client_find,
                        'soldes_collaborateur' => $soldes_collaborateur,
                    ]);
                }
            }
        }
        
        return $this->render('gandaal/administration/comptabilite/decaissement/new.html.twig', [
            'decaissement' => $decaissement,
            'form' => $form,
            'entreprise' => $entrepriseRep->find(1),
            'etablissement' => $etablissement,
            'client_find' => $client_find,
            'soldes_collaborateur' => $soldes_collaborateur,
        ]);
    }

    #[Route('/show/{id}/{etablissement}', name: 'app_gandaal_administration_comptabilite_decaissement_show', methods: ['GET'])]
    public function show(Decaissement $decaissement, etablissement $etablissement, EntrepriseRepository $entrepriseRep): Response
    {
        $decaissement_modif = [];
        return $this->render('gandaal/administration/comptabilite/decaissement/show.html.twig', [
            'decaissement' => $decaissement,
            'entreprise' => $entrepriseRep->find(1),
            'etablissement' => $etablissement,
            'decaissements_modif' => $decaissement_modif,
        ]);
    }

    #[Route('/edit/{id}/{etablissement}', name: 'app_gandaal_administration_comptabilite_decaissement_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Decaissement $decaissement, DecaissementRepository $decaissementRep, EntityManagerInterface $entityManager, UserRepository $userRep, MouvementCollaborateurRepository $mouvementCollabRep, MouvementCaisseRepository $mouvementCaisseRep, etablissement $etablissement, EntrepriseRepository $entrepriseRep): Response
    {

        if ($request->isXmlHttpRequest()) {
            $search = $request->query->get('search');
            $users = $userRep->rechercheUserParTypeParEtablissement($search, 'personnel', $etablissement);    
            $response = [];
            foreach ($users as $user) {
                $response[] = [
                    'nom' => ucwords($user->getPrenom())." ".strtoupper($user->getNom()),
                    'id' => $user->getId()
                ]; 
            }
            return new JsonResponse($response);
        }
        $decaissement->setMontant(-$decaissement->getMontant());
        
        $form = $this->createForm(DecaissementType::class, $decaissement, ['etablissement' => $etablissement] );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $montantString = $form->get('montant')->getData();
            $montantString = preg_replace('/[^0-9,.]/', '', $montantString);
            $montant = floatval($montantString);
            $caisse = $form->getViewData()->getCaisse();
            $devise = $form->getViewData()->getDevise();
            $solde_caisse = $mouvementCaisseRep->findSoldeCaisse($caisse, $devise);
            if ($solde_caisse >= $montant) {
                $collaborateur = $request->get('id_collaborateur');
                $collaborateur = $userRep->find($collaborateur);
                $decaissement->setMontant(-$montant)
                            ->setCollaborateur($collaborateur)
                            ->setSaisiePar($this->getUser())
                            ->setDateSaisie(new \DateTime("now"));
                $justificatif =$form->get("document")->getData();
                if ($justificatif) {
                    if ($decaissement->getDocument()) {
                        $ancienJustificatif=$this->getParameter("dossier_decaissements")."/".$decaissement->getDocument();
                        if (file_exists($ancienJustificatif)) {
                            unlink($ancienJustificatif);
                        }
                    }
                    $nomJustificatif= pathinfo($justificatif->getClientOriginalName(), PATHINFO_FILENAME);
                    $slugger = new AsciiSlugger();
                    $nouveauNomJustificatif = $slugger->slug($nomJustificatif);
                    $nouveauNomJustificatif .="_".uniqid();
                    $nouveauNomJustificatif .= "." .$justificatif->guessExtension();
                    $justificatif->move($this->getParameter("dossier_decaissements"),$nouveauNomJustificatif);
                    $decaissement->setDocument($nouveauNomJustificatif);

                }

                $mouvement_collab = $mouvementCollabRep->findOneBy(['decaissement' => $decaissement]); 
                $mouvement_collab->setCollaborateur($collaborateur)
                    ->setMontant(-$montant)
                    ->setDevise($form->getViewData()->getDevise())
                    ->setEtablissement($etablissement)
                    ->setDateOperation($form->getViewData()->getdateOperation())
                    ->setDateSaisie(new \DateTime("now"));

                $entityManager->persist($decaissement);
                $entityManager->flush();
                $this->addFlash("success", "Décaissement modifié avec succès :)");
                return $this->redirectToRoute('app_gandaal_administration_comptabilite_decaissement_index', ['etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
            }else{
                $this->addFlash("warning", "Le montant disponible en caisse est insuffisant");
                // Récupérer l'URL de la page précédente
                $referer = $request->headers->get('referer');

                if ($referer) {
                    $formView = $form->createView();
                    return $this->render('gandaal/administration/comptabilite/decaissement/edit.html.twig', [
                        'entreprise' => $entrepriseRep->find(1),
                        'etablissement' => $etablissement,
                        'form' => $formView,
                        'decaissement' => $decaissement,
                        'referer' => $referer,
                    ]);
                }

                
            }
        }

        if ($request->get("id_user_search")){
            $client_find = $userRep->find($request->get("id_user_search"));
            $soldes_collaborateur = $mouvementCollabRep->findSoldeCollaborateur($client_find);
        }else{
            $client_find = $decaissement->getCollaborateur();
            $soldes_collaborateur = $mouvementCollabRep->findSoldeCollaborateur($client_find);
        }

        return $this->render('gandaal/administration/comptabilite/decaissement/edit.html.twig', [
            'decaissement' => $decaissement,
            'form' => $form,
            'entreprise' => $entrepriseRep->find(1),
            'etablissement' => $etablissement,
            'client_find' => $client_find,
            'soldes_collaborateur' => $soldes_collaborateur
        ]);
    }

    #[Route('/delete/{id}/{etablissement}', name: 'app_gandaal_administration_comptabilite_decaissement_delete', methods: ['POST'])]
    public function delete(Request $request, Decaissement $decaissement, EntityManagerInterface $entityManager, Filesystem $filesystem, etablissement $etablissement, EntrepriseRepository $entrepriseRep): Response
    {
        if ($this->isCsrfTokenValid('delete'.$decaissement->getId(), $request->request->get('_token'))) {
            $justificatif = $decaissement->getDocument();
            $pdfPath = $this->getParameter("dossier_decaissements") . '/' . $justificatif;
            // Si le chemin du justificatif existe, supprimez également le fichier
            if ($justificatif && $filesystem->exists($pdfPath)) {
                $filesystem->remove($pdfPath);
            }

            
            $entityManager->remove($decaissement);
            $entityManager->flush();

            $this->addFlash("success", "Décaissement supprimé avec succès :)");
        }

        return $this->redirectToRoute('app_gandaal_administration_comptabilite_decaissement_index', ['etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/pdf/reçu/{id}/{etablissement}', name: 'app_gandaal_administration_comptabilite_decaissement_recu_pdf', methods: ['GET'])]
    public function recuPdf(Decaissement $decaissement, etablissement $etablissement, MouvementCollaborateurRepository $mouvementCollabRep)
    {
        $logoPath = $this->getParameter('kernel.project_dir') . '/public/images/config/'.$etablissement->getEntreprise()->getLogo();
        $logoBase64 = base64_encode(file_get_contents($logoPath));
        $symbolePath = $this->getParameter('kernel.project_dir') . '/public/images/config/symbole.png';
        $symboleBase64 = base64_encode(file_get_contents($symbolePath));
        $ministerePath = $this->getParameter('kernel.project_dir') . '/public/images/config/ministere.jpg';
        $ministereBase64 = base64_encode(file_get_contents($ministerePath));

        $soleCollaborateur = $mouvementCollabRep->findSoldeCollaborateur($decaissement->getCollaborateur());

        $collaborateur = $decaissement->getCollaborateur();
        $dateOp = $decaissement->getdateOperation();

        $ancienSoleCollaborateur = $mouvementCollabRep->findAncienSoldeCollaborateur($collaborateur, $dateOp);

        $html = $this->renderView('gandaal/administration/comptabilite/decaissement/recu_pdf.html.twig', [
            'decaissement' => $decaissement,
            'solde_collaborateur' => $soleCollaborateur,
            'ancien_solde' => $ancienSoleCollaborateur,
            'etablissement' => $etablissement,
            'logoPath' => $logoBase64,
            'symbolePath' => $symboleBase64,
            'ministerePath' => $ministereBase64,
        ]);

        // Configurez Dompdf
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);
        $options->set("isPhpEnabled", true);
        $options->set("isHtml5ParserEnabled", true);

        // Instancier Dompdf
        $dompdf = new Dompdf($options);

        // Charger le contenu HTML
        $dompdf->loadHtml($html);

        // Définir la taille du papier (A4 par défaut)
        $dompdf->setPaper('A4', 'portrait');

        // Rendre le PDF (stream le PDF au navigateur)
        $dompdf->render();

        // Renvoyer une réponse avec le contenu du PDF
        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="réçu_decaissement.pdf"',
        ]);
    }
}
