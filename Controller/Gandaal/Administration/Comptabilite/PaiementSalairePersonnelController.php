<?php
namespace App\Controller\Gandaal\Administration\Comptabilite;

use Dompdf\Dompdf;
use Dompdf\Options;
use App\Entity\Etablissement;
use App\Entity\MouvementCaisse;
use App\Repository\UserRepository;
use App\Repository\EventRepository;
use App\Repository\CaisseRepository;
use App\Repository\DeviseRepository;
use App\Entity\HistoriqueSuppression;
use App\Repository\SalaireRepository;
use App\Repository\PersonnelRepository;
use App\Entity\PaiementSalairePersonnel;
use Doctrine\ORM\EntityManagerInterface;
use App\Form\PaiementSalairePersonnelType;
use App\Repository\ConfigCaisseRepository;
use App\Repository\ConfigDeviseRepository;
use App\Repository\EtablissementRepository;
use App\Repository\HeureTravailleRepository;
use App\Repository\PersonnelActifRepository;
use App\Repository\PrimePersonnelRepository;
use App\Repository\MouvementCaisseRepository;
use Symfony\Component\HttpFoundation\Request;
use App\Repository\AbsencePersonnelRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\ConfigModePaiementRepository;
use App\Repository\ConfigCompteOperationRepository;
use App\Repository\AvanceSalairePersonnelRepository;
use App\Repository\ConfigCategorieOperationRepository;
use App\Repository\PaiementSalairePersonnelRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/gandaal/administration/comptabilite/paiement/salaire')]
class PaiementSalairePersonnelController extends AbstractController
{
    #[Route('/accueil/{etablissement}', name: 'app_gandaal_administration_comptabilite_paiement_salaire_index', methods: ['GET'])]
    public function index(PaiementSalairePersonnelRepository $paiementRep, SessionInterface $session, Request $request, Etablissement $etablissement): Response
    {
        if ($request->get("periode")) {
            $type = $request->get("type");
            $cursus = $request->get("cursus");
            $periode_select = $request->get("periode");
            $periode = date("Y") . '-' . ($periode_select)  . '-01';
    
            $paiements = $paiementRep->listePaiementParTypeParCursusParPeriodeParEtablissementParPromo($type, ($cursus ? $cursus : "général"), $periode, $etablissement, $session->get('promo'), null);
    
        } else {
            $periode = date("Y-m-d");
            $periode_select = date("m");
            $type = 'personnel';
            $cursus = 'général';
            $paiements = $paiementRep->listePaiementParPeriodeParEtablissementParPromo($periode, $etablissement, $session->get('promo'));
        }
    
        // Grouper les paiements par mode de paiement
        $paiementsParMode = [];
        foreach ($paiements as $paiement) {
            $modePaie = $paiement->getModePaie()->getNom();
            if (!isset($paiementsParMode[$modePaie])) {
                $paiementsParMode[$modePaie] = [];
            }
            $paiementsParMode[$modePaie][] = $paiement;
        }
    
        return $this->render('gandaal/administration/comptabilite/paiement_salaire/index.html.twig', [
            'paiementsParMode' => $paiementsParMode,
            'etablissement' => $etablissement,
            'periode' => $periode,
            'periode_select' => $periode_select,
            'type' => $type,
            'cursus' => $cursus,
        ]);
    }
    

    #[Route('/new/{etablissement}', name: 'app_gandaal_administration_comptabilite_paiement_salaire_new', methods: ['GET', 'POST'])]
    public function new(Request $request, PersonnelActifRepository $personnelActifRep, EventRepository $eventRep, PrimePersonnelRepository $primesRep, AvanceSalairePersonnelRepository $avanceRep, ConfigCaisseRepository $caisseRep, ConfigModePaiementRepository $modePaieRep, SalaireRepository $salaireRep, ConfigDeviseRepository $deviseRep, ConfigCategorieOperationRepository $categorieOpRep, PaiementSalairePersonnelRepository $paiementSalaireRep, HeureTravailleRepository $heureTravailleRep, ConfigCompteOperationRepository $compteOpRep, MouvementCaisseRepository $mouvementRep, SessionInterface $session, EntityManagerInterface $entityManager, Etablissement $etablissement): Response
    {
        if ($request->get("periode")) {
            $type = $request->get("type");
            $cursus = $request->get("cursus");
            $periode_select = $request->get("periode");
            $periode = date("Y") . '-' . $periode_select  . '-01';
            
            $paiementSalairePersonnel = new PaiementSalairePersonnel();
            // dd($paiementSalairePersonnel);
            if ($request->get("caisse") && $request->get("modePaie")) {
                $prime = $request->get("prime") ? $request->get("prime") : 0;
                $avance = $request->get("avance") ? $request->get("avance") : 0;
                $cotisation = $request->get("cotisation") ? $request->get("cotisation") : 0;
                $heures = $request->get("heures") ? $request->get("heures") : 0;
                $salaireNet = $request->get("salaireNet") ? $request->get("salaireNet") : 0;
                $salaireBrut = $request->get("salaireBrut") ? $request->get("salaireBrut") : 0;
                $tauxHoraire = $request->get("tauxHoraire") ? $request->get("tauxHoraire") : 0;

                $devise = $deviseRep->find(1);
                $caisse = $caisseRep->find($request->get("caisse"));
                // dd($request->get("caisse"));
                $solde_caisse = $mouvementRep->findSoldeCaisse($caisse, $devise);
                if ($solde_caisse >= $salaireNet) {
                    $currentYear = (new \DateTime())->format('ymd');
                    $maxPaie = $paiementSalaireRep->findMaxId($etablissement, $session->get('promo'));
                    $formattedMaxPaie = sprintf('%04d', $maxPaie + 1); 
                    $generatedReference = $currentYear . $formattedMaxPaie;
                    $reference = 'sal'.$generatedReference;

                    $personnelActif = $personnelActifRep->find($request->query->get("personnel"));
                    // $salaire = $salaireRep->findOneBy(['user' => $personnelActif->getPersonnel(), 'promo' => $session->get('promo')]);


                    $paiementSalairePersonnel->setPersonnelActif($personnelActif)
                            ->setSaisiePar($this->getUser())
                            ->setEtablissement($etablissement)
                            ->setReference($reference)
                            ->setPeriode(new \DateTime($periode))
                            ->setDateOperation(new \DateTime($periode))
                            ->setDateSaisie(new \DateTime("now"))
                            ->setCommentaires($request->get("commentaire"))
                            ->setSalaireBrut($salaireBrut)
                            ->setPrime($prime)
                            ->setAvanceSalaire($avance)
                            ->setCotisation($cotisation)
                            ->setMontant(-$salaireNet)
                            ->setTauxHoraire($tauxHoraire)
                            ->setTaux(1)
                            ->setHeures($heures)
                            ->setPromo($session->get('promo'))
                            ->setCaisse($caisseRep->find($request->get("caisse")))
                            ->setModePaie($modePaieRep->find($request->get("modePaie")))
                            ->setDevise($deviseRep->find(1))
                            ->setCategorieOperation($categorieOpRep->find(5))
                            ->setCompteOperation($compteOpRep->find(2))
                            ->setTypeMouvement("salaire")
                            ->setCompteBancaire($personnelActif->getPersonnel()->getNumeroCompte())
                            ->setBanqueVirement($personnelActif->getPersonnel()->getAgenceBanque())
                            ->setEtatOperation('clos');
                    $entityManager->persist($paiementSalairePersonnel);
                    // dd($type, $cursus);
                    $entityManager->flush();

                    $this->addFlash("success", 'Paiement éffectué avec succès :) ');
                    // return new RedirectResponse($this->generateUrl('app_gandaal_administration_comptabilite_paiement_salaire_new', ['etablissement' => $etablissement->getId(), 'periode' => $request->get("periode"), 'type' => $type, 'cursus' => $cursus]));
                    $referer = $request->headers->get('referer');
                    return $this->redirect($referer);
                }else{
                    $this->addFlash("warning", "Le montant disponible en caisse est insuffisant");
                }
            }

            $periode_select = $request->get("periode");
            $periode = date("Y") . '-' . $periode_select  . '-01';
            // $periode_select_format = new \DateTime($periode_select);
            $paiementsInfos = [];
            
            $personnels = $personnelActifRep->listePersonnelNonPaiyeParTypeParCursusParPeriodeParEtablissement($type, ($cursus ? $cursus : "général"), $periode, $etablissement);

            // dd($personnels);
            
            foreach ($personnels as $key => $personnelActif) {                
                $salaire = $salaireRep->findOneBy(['user' => $personnelActif->getPersonnel(), 'promo' => $session->get('promo')]);                
                $heuresTravailles = $heureTravailleRep->sommeHeureTravaillePersonnel($personnelActif, $periode);
                $montant_prime = $primesRep->sommePrimePersonnelMois($personnelActif, $periode);
                // $montant_cotisation = $cotisationRep->findSumOfCotisationForPersonnel($personnelActif->getUser(), $periode);
                // dd($personnelActif, $periode);
                $montant_avance = $avanceRep->sommeAvancePersonnel($personnelActif, $periode);
                // dd($montant_avance, );
                $paiementsInfos[] = [
                    'personnelActif' => $personnelActif,
                    'salaire' => $salaire,
                    'heuresTravailles' => $heuresTravailles,
                    'montant_prime' => $montant_prime,
                    // 'montant_cotisation' => $montant_cotisation,
                    'montant_avance' => $montant_avance,
                ];
            }
        }else{
            $paiementsInfos = [];
            $periode = date("Y-m-d");
            $periode_select = date("m");
            $type = 'personnel';
            $cursus = 'général';

        }
        // dd($paiementsInfos);
        return $this->render('gandaal/administration/comptabilite/paiement_salaire/new.html.twig', [
            'paiementsInfos' => $paiementsInfos,
            'caisses' => $caisseRep->findBy(['etablissement' => $etablissement]),
            'modePaies' => $modePaieRep->findAll(),
            'etablissement' => $etablissement,
            'periode' => $periode,
            'periode_select' => $periode_select,
            'type' => $type,
            'cursus' => $cursus

        ]);
    }

    #[Route('/show/{id}/{etablissement}', name: 'app_gandaal_administration_comptabilite_paiement_salaire_show', methods: ['GET'])]
    public function show(PaiementSalairePersonnel $paiementSalairePersonnel, Etablissement $etablissement): Response
    {
        return $this->render('gandaal/administration/comptabilite/paiement_salaire/show.html.twig', [
            'paiements_salaires_personnel' => $paiementSalairePersonnel,
            'etablissement' => $etablissement,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_nhema_personnel_paiement_salaires_personnel_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, PaiementSalairePersonnel $paiementSalairePersonnel, EntityManagerInterface $entityManager, Etablissement $etablissement): Response
    {
        $form = $this->createForm(PaiementSalairePersonnelType::class, $paiementSalairePersonnel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_nhema_personnel_paiement_salaires_personnel_index', ['etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('nhema/personnel/paiements_salaires_personnel/edit.html.twig', [
            'paiements_salaires_personnel' => $paiementSalairePersonnel,
            'form' => $form,
            'etablissement' => $etablissement,

        ]);
    }

    #[Route('/confirm/delete/{id}/{etablissement}', name: 'app_gandaal_administration_comptabilite_paiement_salaire_confirm_delete', methods: ['GET', 'POST'])]
    public function confirmDelete(PaiementSalairePersonnel $paiement, Request $request, Etablissement $etablissement): Response
    {
        $param = $request->request->get('param'); // Récupération du paramètre
        // Code spécifique pour le paramètre "simple"
        $route_suppression = $this->generateUrl('app_gandaal_administration_comptabilite_paiement_salaire_delete', [
            'id' => $paiement->getId(),
            'etablissement' => $etablissement->getId()
        ]);
        

        return $this->render('gandaal/administration/comptabilite/paiement_salaire/confirm_delete.html.twig', [
            'paiement' => $paiement,
            'etablissement' => $etablissement,
            'route_suppression' => $route_suppression,
            'param' => $param,
        ]);
    }

    #[Route('delete/{id}/{etablissement}', name: 'app_gandaal_administration_comptabilite_paiement_salaire_delete', methods: ['POST' , 'GET'])]
    public function delete(Request $request, PaiementSalairePersonnel $paiement, SessionInterface $session, EntityManagerInterface $entityManager, Etablissement $etablissement): Response
    {
        if ($this->isCsrfTokenValid('delete'.$paiement->getId(), $request->request->get('_token'))) {
            // Récupérer le motif de suppression
            $deleteReason = $request->request->get('delete_reason');
            $information = 'ref '.$paiement->getReference().' '.number_format($paiement->getMontant(),0,',',' ');
            $historique = new HistoriqueSuppression();
            $historique->setType('paiement salaire') // ou un type plus spécifique
                ->setMotif($deleteReason)
                ->setOrigine('comptabilite')
                ->setDateOperation(new \DateTime())
                ->setInformation($information)
                ->setSaisiePar($this->getUser())
                ->setPromo($session->get('promo'))
                ->setUser($paiement->getPersonnelActif()->getPersonnel());
                
            $entityManager->persist($historique);
            $entityManager->remove($paiement);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_gandaal_administration_comptabilite_paiement_salaire_index', ['etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
    }


    #[Route('/pdf/fichepaie/{id}/{etablissement}', name: 'app_gandaal_administration_comptabilite_paiement_salaire_fiche_paie', methods: ['GET'])]
    public function genererPdfAction(PaiementSalairePersonnel $paiementSalaire, Etablissement $etablissement, PersonnelRepository $personnelRep)
    {
        $entreprise = $etablissement->getEntreprise();
        $logoPath = $this->getParameter('kernel.project_dir') . '/public/images/img-logos/'.$entreprise->getLogo();
        $logoBase64 = base64_encode(file_get_contents($logoPath));

        $html = $this->renderView('gandaal/administration/comptabilite/paiement_salaire/fiche_paie.html.twig', [
            'paiement_salaire' => $paiementSalaire,
            'personnel' => $personnelRep->findOneBy(['user' => $paiementSalaire->getPersonnelActif()]),
            'logoPath' => $logoBase64,
            'etablissement' => $etablissement,
            // 'qrCode'    => $qrCode,
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
            'Content-Disposition' => 'inline; filename="fiche_paie.pdf"',
        ]);
    }
}
