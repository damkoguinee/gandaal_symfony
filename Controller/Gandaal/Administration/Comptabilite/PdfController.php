<?php

namespace App\Controller\Gandaal\Administration\Comptabilite;

use App\Entity\ConfigModePaiement;
use Dompdf\Dompdf;
use Dompdf\Options;
use App\Entity\Eleve;
use App\Entity\Inscription;
use App\Entity\Etablissement;
use App\Entity\PaiementEleve;
use App\Repository\EleveRepository;
use App\Entity\PaiementActiviteScolaire;
use App\Repository\InscriptionRepository;
use App\Repository\ConfigCaisseRepository;
use App\Repository\PaiementEleveRepository;
use App\Repository\FraisScolariteRepository;
use App\Repository\TranchePaiementRepository;
use Symfony\Component\HttpFoundation\Request;
use App\Repository\FraisInscriptionRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\ClasseRepartitionRepository;
use App\Repository\ConfigModePaiementRepository;
use App\Repository\InscriptionActiviteRepository;
use App\Repository\PaiementActiviteScolaireRepository;
use App\Repository\PaiementSalairePersonnelRepository;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/gandaal/administration/comptabilite/pdf')]
class PdfController extends AbstractController
{
    #[Route('/paiement/historique/{inscription}', name: 'app_gandaal_administration_comptabilite_pdf_paiement_historique')]
    public function index(Inscription $inscription, FraisScolariteRepository $fraisScolRep, FraisInscriptionRepository $fraisInsRep, SessionInterface $session, PaiementEleveRepository $paiementRep): Response
    {
        $etablissement = $inscription->getEleve()->getEtablissement();        
        $logoPath = $this->getParameter('kernel.project_dir') . '/public/images/config/'.$etablissement->getEntreprise()->getLogo();
        $logoBase64 = base64_encode(file_get_contents($logoPath));
        $symbolePath = $this->getParameter('kernel.project_dir') . '/public/images/config/symbole.png';
        $symboleBase64 = base64_encode(file_get_contents($symbolePath));
        $ministerePath = $this->getParameter('kernel.project_dir') . '/public/images/config/ministere.jpg';
        $ministereBase64 = base64_encode(file_get_contents($ministerePath));

        $remiseIns = $inscription->getRemiseInscription() ? $inscription->getRemiseInscription() : 0;
        $remiseScolarite = $inscription->getRemiseScolarite() ? $inscription->getRemiseScolarite() : 0;

        $classe = $inscription->getClasse();

        $cursus = $classe->getFormation()->getCursus();

        $fraisIns = $fraisInsRep->findOneBy(['cursus' => $cursus, 'description' => $inscription->getType(), 'promo' => $session->get('promo')]);

        $totalScolarite = $fraisScolRep->montantTotalFraisScolariteParFormation($classe->getFormation(), $session->get('promo'));

        $totalScolarite = $totalScolarite * (1 - ($remiseScolarite / 100));
        $fraisInscription = $fraisIns->getMontant() * (1 - ($remiseIns / 100));
            
        $scolarite_annuel = $totalScolarite + $fraisInscription;


        $historiques = $paiementRep->findBy(['inscription' => $inscription, 'promo' => $session->get('promo')], ['id' => 'DESC']);

        $html = $this->renderView('gandaal/administration/comptabilite/pdf/historique_paiement.html.twig', [
            'historiques' => $historiques,            
            'logoPath' => $logoBase64,
            'symbolePath' => $symboleBase64,
            'ministerePath' => $ministereBase64,
            'etablissement' => $etablissement,
            'inscription' => $inscription,
            'scolarite_annuel' => $scolarite_annuel,
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
            'Content-Disposition' => 'inline; filename=historique_paiement_'.date("d/m/Y à H:i").'".pdf"',
        ]);
    }

    #[Route('/paiement/cumul/{inscription}', name: 'app_gandaal_administration_comptabilite_pdf_paiement_cumul')]
    public function paiementCumulEleve(Inscription $inscription, FraisScolariteRepository $fraisScolRep, FraisInscriptionRepository $fraisInsRep, SessionInterface $session, PaiementEleveRepository $paiementRep): Response
    {
        $etablissement = $inscription->getEleve()->getEtablissement();        
        $logoPath = $this->getParameter('kernel.project_dir') . '/public/images/config/'.$etablissement->getEntreprise()->getLogo();
        $logoBase64 = base64_encode(file_get_contents($logoPath));
        $symbolePath = $this->getParameter('kernel.project_dir') . '/public/images/config/symbole.png';
        $symboleBase64 = base64_encode(file_get_contents($symbolePath));
        $ministerePath = $this->getParameter('kernel.project_dir') . '/public/images/config/ministere.jpg';
        $ministereBase64 = base64_encode(file_get_contents($ministerePath));

        $remiseIns = $inscription->getRemiseInscription() ? $inscription->getRemiseInscription() : 0;
        $remiseScolarite = $inscription->getRemiseScolarite() ? $inscription->getRemiseScolarite() : 0;

        $classe = $inscription->getClasse();

        $cursus = $classe->getFormation()->getCursus();

        $fraisIns = $fraisInsRep->findOneBy(['cursus' => $cursus, 'description' => $inscription->getType(), 'promo' => $session->get('promo')]);

        $totalScolarite = $fraisScolRep->montantTotalFraisScolariteParFormation($classe->getFormation(), $session->get('promo'));

        $totalScolarite = $totalScolarite * (1 - ($remiseScolarite / 100));
        $fraisInscription = $fraisIns->getMontant() * (1 - ($remiseIns / 100));
            
        $scolarite_annuel = $totalScolarite + $fraisInscription;


        $cumulPaiements = $paiementRep->cumulPaiementEleveGroupeParType($inscription, $session->get('promo'));

        $html = $this->renderView('gandaal/administration/comptabilite/pdf/cumul_paiement.html.twig', [
            'cumulPaiements' => $cumulPaiements,            
            'logoPath' => $logoBase64,
            'symbolePath' => $symboleBase64,
            'ministerePath' => $ministereBase64,
            'etablissement' => $etablissement,
            'inscription' => $inscription,
            'scolarite_annuel' => $scolarite_annuel,
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
            'Content-Disposition' => 'inline; filename=cumul_paiement_'.$inscription->getEleve()->getMatricule().date("d/m/Y à H:i").'".pdf"',
        ]);
    }

    #[Route('/paiement/recu/{id}', name: 'app_gandaal_administration_comptabilite_pdf_paiement_recu')]
    public function recuPaiementEleve(PaiementEleve $paiementEleve, ConfigCaisseRepository $caisseRep, FraisScolariteRepository $fraisScolRep, FraisInscriptionRepository $fraisInsRep, SessionInterface $session, PaiementEleveRepository $paiementRep): Response
    {
        $etablissement = $paiementEleve->getInscription()->getEleve()->getEtablissement();
        $logoPath = $this->getParameter('kernel.project_dir') . '/public/images/config/'.$etablissement->getEntreprise()->getLogo();
        $logoBase64 = base64_encode(file_get_contents($logoPath));
        $symbolePath = $this->getParameter('kernel.project_dir') . '/public/images/config/symbole.png';
        $symboleBase64 = base64_encode(file_get_contents($symbolePath));
        $ministerePath = $this->getParameter('kernel.project_dir') . '/public/images/config/ministere.jpg';
        $ministereBase64 = base64_encode(file_get_contents($ministerePath));
    
        $paiementsParReference = $paiementRep->paiementEleveParReference($paiementEleve->getReference());
        $groupedPaiements = [];
    
        $cumulTotalPaye = 0;
        $cumulResteAnnuel = 0;
    
        foreach ($paiementsParReference as $paiement) {
            $cumulPaiement = $paiementRep->cumulPaiementEleve($paiement->getInscription(), $session->get('promo'));
    
            $remiseIns = $paiement->getInscription()->getRemiseInscription() ?: 0;
            $remiseScolarite = $paiement->getInscription()->getRemiseScolarite() ?: 0;
    
            $totalScolarite = $fraisScolRep->montantTotalFraisScolariteParFormation($paiement->getInscription()->getClasse()->getFormation(), $session->get('promo'));
            $totalScolarite *= (1 - ($remiseScolarite / 100));
    
            $cursus = $paiement->getInscription()->getClasse()->getFormation()->getCursus();
            $fraisIns = $fraisInsRep->findOneBy(['cursus' => $cursus, 'description' => $paiement->getInscription()->getType(), 'promo' => $session->get('promo')]);
    
            $fraisInscription = $fraisIns->getMontant() * (1 - ($remiseIns / 100));
            $scolarite_annuel = $totalScolarite + $fraisInscription;
            $resteAnnuel = $scolarite_annuel - $cumulPaiement;
    
            $inscriptionId = $paiement->getInscription()->getId();
            if (!isset($groupedPaiements[$inscriptionId])) {
                $groupedPaiements[$inscriptionId] = [
                    'inscription' => $paiement->getInscription(),
                    'paiements' => [],
                    'cumulPaiement' => $cumulPaiement,
                    'resteAnnuel' => $resteAnnuel,
                ];
                $cumulResteAnnuel += $resteAnnuel;
            }
            $groupedPaiements[$inscriptionId]['paiements'][] = $paiement;
            $cumulTotalPaye += $paiement->getMontant();
        }
    
        $html = $this->renderView('gandaal/administration/comptabilite/pdf/recu_paiement.html.twig', [
            'logoPath' => $logoBase64,
            'symbolePath' => $symboleBase64,
            'ministerePath' => $ministereBase64,
            'etablissement' => $etablissement,
            'caisses' => $caisseRep->findBy(['etablissement' => $etablissement, 'type' => 'banque', 'document' => 'actif']),
            'paiementEleve' => $paiementEleve,
            'groupedPaiements' => $groupedPaiements,
            'cumulTotalPaye' => $cumulTotalPaye,
            'cumulResteAnnuel' => $cumulResteAnnuel
        ]);
    
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);
        $options->set("isPhpEnabled", true);
        $options->set("isHtml5ParserEnabled", true);
    
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
    
        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename=recu_paiement_'.$paiementEleve->getInscription()->getEleve()->getMatricule().date("d/m/Y à H:i").'".pdf"',
        ]);
    }


    #[Route('/paiement/activite/recu/{id}', name: 'app_gandaal_administration_comptabilite_pdf_paiement_activite_recu')]
    public function recuPaiementActiviteEleve(PaiementActiviteScolaire $paiementEleve,PaiementActiviteScolaireRepository $paiementActiviteScolaireRep, ConfigCaisseRepository $caisseRep, FraisScolariteRepository $fraisScolRep, FraisInscriptionRepository $fraisInsRep, SessionInterface $session): Response
    {
        $etablissement = $paiementEleve->getInscription()->getEleve()->getEtablissement();
        $logoPath = $this->getParameter('kernel.project_dir') . '/public/images/config/'.$etablissement->getEntreprise()->getLogo();
        $logoBase64 = base64_encode(file_get_contents($logoPath));
        $symbolePath = $this->getParameter('kernel.project_dir') . '/public/images/config/symbole.png';
        $symboleBase64 = base64_encode(file_get_contents($symbolePath));
        $ministerePath = $this->getParameter('kernel.project_dir') . '/public/images/config/ministere.jpg';
        $ministereBase64 = base64_encode(file_get_contents($ministerePath));
    
        $paiementsParReference = $paiementActiviteScolaireRep->paiementEleveParReference($paiementEleve->getReference());
        $groupedPaiements = [];
    
        $cumulTotalPaye = 0;
        $cumulResteAnnuel = 0;
    
        foreach ($paiementsParReference as $paiement) {
            $cumulPaiement = $paiementActiviteScolaireRep->cumulPaiementEleve($paiement->getInscription(), $paiement->getPeriode(), $session->get('promo'));
    
            $remise = $paiement->getInscription()->getRemise() ?: 0;

    
            $inscriptionId = $paiement->getInscription()->getId();
            if (!isset($groupedPaiements[$inscriptionId])) {
                $groupedPaiements[$inscriptionId] = [
                    'inscription' => $paiement->getInscription(),
                    'paiements' => [],
                    'cumulPaiement' => $cumulPaiement,
                ];
            }
            $groupedPaiements[$inscriptionId]['paiements'][] = $paiement;
            $cumulTotalPaye += $paiement->getMontant();
        }
        // dd($groupedPaiements);
    
        $html = $this->renderView('gandaal/administration/comptabilite/pdf/recu_paiement_activite.html.twig', [
            'logoPath' => $logoBase64,
            'symbolePath' => $symboleBase64,
            'ministerePath' => $ministereBase64,
            'etablissement' => $etablissement,
            'caisses' => $caisseRep->findBy(['etablissement' => $etablissement, 'type' => 'banque', 'document' => 'actif']),
            'paiementEleve' => $paiementEleve,
            'groupedPaiements' => $groupedPaiements,
            'cumulTotalPaye' => $cumulTotalPaye,
            'cumulResteAnnuel' => $cumulResteAnnuel
        ]);
    
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);
        $options->set("isPhpEnabled", true);
        $options->set("isHtml5ParserEnabled", true);
    
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
    
        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename=recu_paiement_activite_'.$paiementEleve->getInscription()->getEleve()->getMatricule().date("d/m/Y à H:i").'".pdf"',
        ]);
    }

    #[Route('/paiement/activite/historique/{eleve}', name: 'app_gandaal_administration_comptabilite_pdf_paiement_activite_historique')]
    public function historiqueActivite(Eleve $eleve, InscriptionActiviteRepository $inscriptionActiviteRepository, SessionInterface $session, PaiementActiviteScolaireRepository $paiementRep): Response
    {
        $etablissement = $eleve->getEtablissement();        
        $logoPath = $this->getParameter('kernel.project_dir') . '/public/images/config/'.$etablissement->getEntreprise()->getLogo();
        $logoBase64 = base64_encode(file_get_contents($logoPath));
        $symbolePath = $this->getParameter('kernel.project_dir') . '/public/images/config/symbole.png';
        $symboleBase64 = base64_encode(file_get_contents($symbolePath));
        $ministerePath = $this->getParameter('kernel.project_dir') . '/public/images/config/ministere.jpg';
        $ministereBase64 = base64_encode(file_get_contents($ministerePath));

        $inscriptions = $inscriptionActiviteRepository->findBy(['eleve' => $eleve, 'promo' => $session->get('promo')]);
        $historiques = $paiementRep->findBy(['inscription' => $inscriptions, 'promo' => $session->get('promo')], ['dateOperation' => 'ASC']);

        $html = $this->renderView('gandaal/administration/comptabilite/pdf/historique_paiement_activite.html.twig', [
            'historiques' => $historiques,            
            'logoPath' => $logoBase64,
            'symbolePath' => $symboleBase64,
            'ministerePath' => $ministereBase64,
            'etablissement' => $etablissement,
            'eleve' => $eleve
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
            'Content-Disposition' => 'inline; filename=historique_paiement_activite_'.$eleve->getMatricule().date("d/m/Y à H:i").'".pdf"',
        ]);
    }
    

    #[Route('/retard/paiement/{etablissement}', name: 'app_gandaal_administration_comptabilite_pdf_retard_paiement')]
    public function retardPaiement(Etablissement $etablissement, PaiementEleveRepository $paiementRep, InscriptionRepository $inscriptionRep, TranchePaiementRepository $tranchePaieRep, FraisScolariteRepository $fraisScolRep, SessionInterface $session, ClasseRepartitionRepository $classeRep, EleveRepository $eleveRep, Request $request ): Response
    {       
        $logoPath = $this->getParameter('kernel.project_dir') . '/public/images/config/'.$etablissement->getEntreprise()->getLogo();
        $logoBase64 = base64_encode(file_get_contents($logoPath));
        $symbolePath = $this->getParameter('kernel.project_dir') . '/public/images/config/symbole.png';
        $symboleBase64 = base64_encode(file_get_contents($symbolePath));
        $ministerePath = $this->getParameter('kernel.project_dir') . '/public/images/config/ministere.jpg';
        $ministereBase64 = base64_encode(file_get_contents($ministerePath));

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
        $html = $this->renderView('gandaal/administration/comptabilite/pdf/retard_paiement.html.twig', [           
            'logoPath' => $logoBase64,
            'symbolePath' => $symboleBase64,
            'ministerePath' => $ministereBase64,
            'etablissement' => $etablissement,
            'donnees' => $donnees,
            'promo' => $session->get('promo'),
            'tranches' => $tranchePaieRep->findBy(['etablissement' => $etablissement, 'promo' => $session->get('promo')]),
            'classes' => $classeRep->listeDesClassesParEtablissementParPromo($etablissement, $session->get('promo')),
            'classe' => $classe,
            'tranche' => $tranche,
            'search' => $search,
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
            'Content-Disposition' => 'inline; filename=retards_paiement_'.$inscription->getEleve()->getMatricule().date("d/m/Y à H:i").'".pdf"',
        ]);
    }

    #[Route('/creances/scolarite/{etablissement}', name: 'app_gandaal_administration_comptabilite_pdf_creances_scolarite')]
    public function creances(Etablissement $etablissement, PaiementEleveRepository $paiementRep, InscriptionRepository $inscriptionRep, TranchePaiementRepository $tranchePaieRep, FraisScolariteRepository $fraisScolRep, SessionInterface $session, ClasseRepartitionRepository $classeRep, EleveRepository $eleveRep, Request $request ): Response
    {       
        $logoPath = $this->getParameter('kernel.project_dir') . '/public/images/config/'.$etablissement->getEntreprise()->getLogo();
        $logoBase64 = base64_encode(file_get_contents($logoPath));
        $symbolePath = $this->getParameter('kernel.project_dir') . '/public/images/config/symbole.png';
        $symboleBase64 = base64_encode(file_get_contents($symbolePath));
        $ministerePath = $this->getParameter('kernel.project_dir') . '/public/images/config/ministere.jpg';
        $ministereBase64 = base64_encode(file_get_contents($ministerePath));

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
        $html = $this->renderView('gandaal/administration/comptabilite/pdf/creances.html.twig', [           
            'logoPath' => $logoBase64,
            'symbolePath' => $symboleBase64,
            'ministerePath' => $ministereBase64,
            'etablissement' => $etablissement,
            'donnees' => $donnees,
            'promo' => $session->get('promo'),
            'tranches' => $tranchePaieRep->findBy(['etablissement' => $etablissement, 'promo' => $session->get('promo')]),
            'classes' => $classeRep->listeDesClassesParEtablissementParPromo($etablissement, $session->get('promo')),
            'classe' => $classe,
            'tranche' => $tranche,
            'search' => $search,
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
            'Content-Disposition' => 'inline; filename=creances_scolarite_'.date("d/m/Y à H:i").'".pdf"',
        ]);
    }


    #[Route('/relance/scolarite/{etablissement}', name: 'app_gandaal_administration_comptabilite_pdf_relance_scolarite')]
    public function relanceScolarite(Etablissement $etablissement, PaiementEleveRepository $paiementRep, InscriptionRepository $inscriptionRep, TranchePaiementRepository $tranchePaieRep, FraisScolariteRepository $fraisScolRep, SessionInterface $session, ClasseRepartitionRepository $classeRep, EleveRepository $eleveRep, Request $request ): Response
    {       
        $logoPath = $this->getParameter('kernel.project_dir') . '/public/images/config/'.$etablissement->getEntreprise()->getLogo();
        $logoBase64 = base64_encode(file_get_contents($logoPath));
        $symbolePath = $this->getParameter('kernel.project_dir') . '/public/images/config/symbole.png';
        $symboleBase64 = base64_encode(file_get_contents($symbolePath));
        $ministerePath = $this->getParameter('kernel.project_dir') . '/public/images/config/ministere.jpg';
        $ministereBase64 = base64_encode(file_get_contents($ministerePath));

        $search = $request->get('search') ? $request->get('search') : Null;    
        $tranche = $request->get('tranche') ? $tranchePaieRep->find($request->get('tranche')) : Null;        
        $classe = $request->get('classe') ? $classeRep->find($request->get('classe')) : Null;
        $pageEncours = $request->get('pageEncours', 1);

        
        $donnees = [];
        if ($request->get('inscription')) {
            $inscriptions = $inscriptionRep->findBy(['id' => $request->get('inscription')]);
            $inscriptions = $inscriptions;
        }else{

            if ($classe) {
                $inscriptions = $inscriptionRep->listeDesElevesInscritParPromoParEtablissementParClassePaginated($session->get('promo'), $etablissement, $classe, $search, $pageEncours, 20000);
    
            }else{
    
                $inscriptions = $inscriptionRep->listeDesElevesInscritParPromoParEtablissement($session->get('promo'), $etablissement, $search, $pageEncours, 20000);
            }

            $inscriptions = $inscriptions['data'];
        }
        foreach ($inscriptions as $inscription) {
            
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
                    'frais_tranche' => $frais_tranche,
                    'restes' => $reste_scolarite_tranche,
                    'cumuls' => $cumulPaiements,
                    'paiement' => $paiement,
                ];
            }
        } 
        
        // dd($donnees);
        $html = $this->renderView('gandaal/administration/comptabilite/pdf/relance_scolarite.html.twig', [           
            'logoPath' => $logoBase64,
            'symbolePath' => $symboleBase64,
            'ministerePath' => $ministereBase64,
            'etablissement' => $etablissement,
            'donnees' => $donnees,
            'promo' => $session->get('promo'),
            'tranches' => $tranchePaieRep->findBy(['etablissement' => $etablissement, 'promo' => $session->get('promo')]),
            'classes' => $classeRep->listeDesClassesParEtablissementParPromo($etablissement, $session->get('promo')),
            'classe' => $classe,
            'tranche' => $tranche,
            'search' => $search,
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
            'Content-Disposition' => 'inline; filename=relance_scolarite'.date("d/m/Y à H:i").'".pdf"',
        ]);
    }

    #[Route('/fiche/paie/{etablissement}', name: 'app_gandaal_administration_comptabilite_pdf_fiche_paie')]
    public function fichePaie(Etablissement $etablissement, SessionInterface $session, PaiementSalairePersonnelRepository $paiementRep, Request $request ): Response
    {       
        $logoPath = $this->getParameter('kernel.project_dir') . '/public/images/config/'.$etablissement->getEntreprise()->getLogo();
        $logoBase64 = base64_encode(file_get_contents($logoPath));
        $symbolePath = $this->getParameter('kernel.project_dir') . '/public/images/config/symbole.png';
        $symboleBase64 = base64_encode(file_get_contents($symbolePath));
        $ministerePath = $this->getParameter('kernel.project_dir') . '/public/images/config/ministere.jpg';
        $ministereBase64 = base64_encode(file_get_contents($ministerePath));

        $id_paie = $request->get('id') ? $request->get('id') : Null; 
        if ($id_paie) {
            $paiements = $paiementRep->findBY(['id' => $id_paie]);
        }else{
            $type = $request->get("type");
            $cursus = $request->get("cursus");
            $periode_select = $request->get("periode");
            $periode = date("Y") . '-' . ($periode_select)  . '-01';

            $paiements = $paiementRep->listePaiementParTypeParCursusParPeriodeParEtablissementParPromo($type, $cursus, $periode, $etablissement, $session->get('promo'));
        } 
        
        // dd($paiements);
        
        $html = $this->renderView('gandaal/administration/comptabilite/pdf/fiche_paie.html.twig', [           
            'logoPath' => $logoBase64,
            'symbolePath' => $symboleBase64,
            'ministerePath' => $ministereBase64,
            'etablissement' => $etablissement,
            'paiements' => $paiements,
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
            'Content-Disposition' => 'inline; filename=fiche_paie'.date("d/m/Y à H:i").'".pdf"',
        ]);
    }

    #[Route('/salaire/personnel/{etablissement}', name: 'app_gandaal_administration_comptabilite_pdf_salaire_personnel')]
    public function salairePersonnel(Etablissement $etablissement, SessionInterface $session, PaiementSalairePersonnelRepository $paiementRep, ConfigModePaiementRepository $modeRep, Request $request ): Response
    {       
        $logoPath = $this->getParameter('kernel.project_dir') . '/public/images/config/'.$etablissement->getEntreprise()->getLogo();
        $logoBase64 = base64_encode(file_get_contents($logoPath));
        $symbolePath = $this->getParameter('kernel.project_dir') . '/public/images/config/symbole.png';
        $symboleBase64 = base64_encode(file_get_contents($symbolePath));
        $ministerePath = $this->getParameter('kernel.project_dir') . '/public/images/config/ministere.jpg';
        $ministereBase64 = base64_encode(file_get_contents($ministerePath));


        if ($request->get("periode")) {
            $type = $request->get("type");
            $cursus = $request->get("cursus");
            $periode_select = $request->get("periode");
            $periode = date("Y") . '-' . ($periode_select)  . '-01';

            $mode = $request->get('mode') ? $modeRep->findOneBy(['nom' => $request->get('mode')]) : null;            
    
            $paiements = $paiementRep->listePaiementParTypeParCursusParPeriodeParEtablissementParPromo($type, ($cursus ? $cursus : "général"), $periode, $etablissement, $session->get('promo'), $mode);
    
        } else {
            $periode = date("Y-m-d");
            $periode_select = date("m");
            $type = 'personnel';
            $cursus = 'général';
            $paiements = $paiementRep->listePaiementParPeriodeParEtablissementParPromo($periode, $etablissement, $session->get('promo'));
            $mode = null;
        }
        // dd($mode);
    
        // Grouper les paiements par mode de paiement
        $paiementsParMode = [];
        foreach ($paiements as $paiement) {
            $modePaie = $paiement->getModePaie()->getNom();
            if (!isset($paiementsParMode[$modePaie])) {
                $paiementsParMode[$modePaie] = [];
            }
            $paiementsParMode[$modePaie][] = $paiement;
        }
        
        // dd($paiements);
        
        $html = $this->renderView('gandaal/administration/comptabilite/pdf/salaire.html.twig', [           
            'logoPath' => $logoBase64,
            'symbolePath' => $symboleBase64,
            'ministerePath' => $ministereBase64,
            'etablissement' => $etablissement,
            'paiementsParMode' => $paiementsParMode,
            'periode' => $periode,
            'modePaie' => $mode
        ]);

        // return $this->render('gandaal/administration/comptabilite/pdf/salaire.html.twig', [
        //     'paiementsParMode' => $paiementsParMode,
        //     'etablissement' => $etablissement,
        //     'periode' => $periode,
        //     'periode_select' => $periode_select,
        //     'type' => $type,
        //     'cursus' => $cursus,
        //     'periode' => $periode,
        //     'mode' => $mode,
        //     'logoPath' => $logoBase64,
        //     'symbolePath' => $symboleBase64,
        //     'ministerePath' => $ministereBase64,
        // ]);

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
            'Content-Disposition' => 'inline; filename=salaires'.date("d/m/Y à H:i").'".pdf"',
        ]);
    }

}
