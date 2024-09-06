<?php

namespace App\Controller\Gandaal\Administration\Scolarite;

use App\Entity\ClasseRepartition;
use App\Entity\Etablissement;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use App\Entity\Inscription;
use App\Repository\ClasseRepartitionRepository;
use App\Repository\ConfigFonctionRepository;
use App\Repository\InscriptionRepository;
use App\Repository\PaiementEleveRepository;
use App\Repository\PersonnelRepository;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

#[Route('/gandaal/administration/scolarite/pdf')]
class PdfController extends AbstractController
{
    #[Route('/fiche/inscription/{inscription}', name: 'app_gandaal_administration_scolarite_pdf_fiche_inscription')]
    public function index(Inscription $inscription,  PaiementEleveRepository $paiementRep, SessionInterface $session): Response
    {
        $etablissement = $inscription->getEleve()->getEtablissement();        
        $logoPath = $this->getParameter('kernel.project_dir') . '/public/images/config/'.$etablissement->getEntreprise()->getLogo();
        $logoBase64 = base64_encode(file_get_contents($logoPath));
        $symbolePath = $this->getParameter('kernel.project_dir') . '/public/images/config/symbole.png';
        $symboleBase64 = base64_encode(file_get_contents($symbolePath));
        $ministerePath = $this->getParameter('kernel.project_dir') . '/public/images/config/ministere.jpg';
        $ministereBase64 = base64_encode(file_get_contents($ministerePath));
        
        if ($inscription->getEleve()->getPhoto()) {
            # code...
            $photo = $this->getParameter('kernel.project_dir') . '/public/dossier/eleves/'.$inscription->getEleve()->getPhoto();
        }else{
            $photo = $this->getParameter('kernel.project_dir') . '/public/images/config/default.jpg';

        }
        $photoBase64 = base64_encode(file_get_contents($photo));
        
             
        $cumulPaiements = $paiementRep->cumulPaiementEleveGroupeParType($inscription, $session->get('promo'));
        $html = $this->renderView('gandaal/administration/scolarite/pdf/fiche_inscription.html.twig', [                     
            'logoPath' => $logoBase64,
            'symbolePath' => $symboleBase64,
            'ministerePath' => $ministereBase64,
            'photoPath' => $photoBase64,
            'etablissement' => $etablissement,
            'inscription' => $inscription,
            'cumulPaiements' => $cumulPaiements,
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
            'Content-Disposition' => 'inline; filename=fiche_inscription_'.$inscription->getEleve()->getMatricule().date("d/m/Y à H:i").'".pdf"',
        ]);
    }

    #[Route('/carte/scolaire/{etablissement}', name: 'app_gandaal_administration_scolarite_pdf_carte_scolaire')]
    public function carteScolaire(Etablissement $etablissement, InscriptionRepository $inscriptionRep, ClasseRepartitionRepository $classeRep, PersonnelRepository $personnelRep, ConfigFonctionRepository $fonctionRep, Request $request, SessionInterface $session): Response
    {
        $search = $request->get('search');
        $inscriptions = $request->get('inscription') ? $inscriptionRep->findBy(['id' => $request->get('inscription')]) : null;
            
        $classe = $request->get('classe') ? $classeRep->find($request->get('classe')) : null;
        if ($classe) {
            $inscriptions = $inscriptionRep->listeDesElevesInscritParPromoParEtablissementParClassePaginated($session->get('promo'), $etablissement, $classe, $search, 1, 1000);
            $inscriptions = $inscriptions['data'];

            $info = $classeRep->find($request->get('classe'))->getNom();
        }else{
            $info = $inscriptionRep->find($request->get('inscription'))->getEleve()->getMatricule();
        }       
        $logoPath = $this->getParameter('kernel.project_dir') . '/public/images/config/'.$etablissement->getEntreprise()->getLogo();
        $logoBase64 = base64_encode(file_get_contents($logoPath));
        $symbolePath = $this->getParameter('kernel.project_dir') . '/public/images/config/symbole.png';
        $symboleBase64 = base64_encode(file_get_contents($symbolePath));
        $ministerePath = $this->getParameter('kernel.project_dir') . '/public/images/config/ministere.jpg';
        $ministereBase64 = base64_encode(file_get_contents($ministerePath));
        
        $inscriptionsTraites = [];
        foreach ($inscriptions as $inscription) {
            $eleve = $inscription->getEleve();
            
            // Récupération de la photo de l'élève
            if ($eleve->getPhoto()) {
                $photoPath = $this->getParameter('kernel.project_dir') . '/public/dossier/eleves/' . $eleve->getPhoto();
            }else{
                $photoPath = $this->getParameter('kernel.project_dir') . '/public/images/config/default.jpg';

            }
            $photoBase64 = base64_encode(file_get_contents($photoPath));

            // Génération du QR Code
            $qrCode = new QrCode($eleve->getMatricule());
            $writer = new PngWriter();
            $result = $writer->write($qrCode);
            $qrCodeData = $result->getDataUri();

            // Structuration des données à transmettre à la vue
            $inscriptionsTraites[] = [
                'inscription' => $inscription,
                'photoBase64' => $photoBase64,
                'qrCodeData' => $qrCodeData
            ];
        }

        // dd($inscriptionsTraites)
        
        $responsable = $personnelRep->findOneBy(['fonction' => $fonctionRep->find(2)]) ?:Null;
        if ($responsable and $responsable->getSignature()) {
            $signature = $this->getParameter('kernel.project_dir') . '/public/dossier/personnels/'.$responsable->getSignature();
        }else{
            $signature = $this->getParameter('kernel.project_dir') . '/public/dossier/personnels/sig.jpg';

        }
        if (file_exists($signature)) {
            $signatureBase64 = base64_encode(file_get_contents($signature));
        } else {
            $signatureBase64 = Null;
        }

        

        $html = $this->renderView('gandaal/administration/scolarite/pdf/carte_scolaire.html.twig', [                     
            'logoPath' => $logoBase64,
            'symbolePath' => $symboleBase64,
            'ministerePath' => $ministereBase64,
            'photoPath' => $photoBase64,
            'signaturePath' => $signatureBase64,
            'qrCodeData' => $qrCodeData,
            'etablissement' => $etablissement,
            'inscriptionsTraites' => $inscriptionsTraites,
            'responsable' => $responsable,

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
            'Content-Disposition' => 'inline; filename=carte_scolaire_'.$info.date("d/m/Y à H:i").'".pdf"',
        ]);
    }

    #[Route('/carte/retrait/{etablissement}', name: 'app_gandaal_administration_scolarite_pdf_carte_retrait')]
    public function carteRetrait(Etablissement $etablissement, InscriptionRepository $inscriptionRep, ClasseRepartitionRepository $classeRep, PersonnelRepository $personnelRep, ConfigFonctionRepository $fonctionRep, Request $request, SessionInterface $session): Response
    { 
        $search = $request->get('search');
        $inscriptions = $request->get('inscription') ? $inscriptionRep->findBy(['id' => $request->get('inscription')]) : null;
            
        $classe = $request->get('classe') ? $classeRep->find($request->get('classe')) : null;
        if ($classe) {
            $inscriptions = $inscriptionRep->listeDesElevesInscritParPromoParEtablissementParClassePaginated($session->get('promo'), $etablissement, $classe, $search, 1, 1000);
            $inscriptions = $inscriptions['data'];

            $info = $classeRep->find($request->get('classe'))->getNom();
        }else{
            $info = $inscriptionRep->find($request->get('inscription'))->getEleve()->getMatricule();
        }  
        

        $logoPath = $this->getParameter('kernel.project_dir') . '/public/images/config/'.$etablissement->getEntreprise()->getLogo();
        $logoBase64 = base64_encode(file_get_contents($logoPath));
        $symbolePath = $this->getParameter('kernel.project_dir') . '/public/images/config/symbole.png';
        $symboleBase64 = base64_encode(file_get_contents($symbolePath));
        $ministerePath = $this->getParameter('kernel.project_dir') . '/public/images/config/ministere.jpg';
        $ministereBase64 = base64_encode(file_get_contents($ministerePath));
        
        $inscriptionsTraites = [];
        foreach ($inscriptions as $inscription) {
            $eleve = $inscription->getEleve();
            
            if ($eleve->getPhoto()) {
                $photoPath = $this->getParameter('kernel.project_dir') . '/public/dossier/eleves/' . $eleve->getPhoto();
            }else{
                $photoPath = $this->getParameter('kernel.project_dir') . '/public/images/config/default.jpg';

            }
            $photoBase64 = base64_encode(file_get_contents($photoPath));

            // Génération du QR Code
            $qrCode = new QrCode($eleve->getMatricule());
            $writer = new PngWriter();
            $result = $writer->write($qrCode);
            $qrCodeData = $result->getDataUri();

            // Structuration des données à transmettre à la vue
            $inscriptionsTraites[] = [
                'inscription' => $inscription,
                'photoBase64' => $photoBase64,
                'qrCodeData' => $qrCodeData
            ];
        } 
        
        $responsable = $personnelRep->findOneBy(['fonction' => $fonctionRep->find(2)]) ?:Null;
        if ($responsable and $responsable->getSignature()) {
            $signature = $this->getParameter('kernel.project_dir') . '/public/dossier/personnels/'.$responsable->getSignature();
        }else{
            $signature = $this->getParameter('kernel.project_dir') . '/public/dossier/personnels/sig.jpg';

        }
        if (file_exists($signature)) {
            $signatureBase64 = base64_encode(file_get_contents($signature));
        } else {
            $signatureBase64 = Null;
        }
        $html = $this->renderView('gandaal/administration/scolarite/pdf/carte_retrait.html.twig', [                     
            'logoPath' => $logoBase64,
            'symbolePath' => $symboleBase64,
            'ministerePath' => $ministereBase64,
            'signaturePath' => $signatureBase64 ? $signatureBase64 : 'nok',
            'etablissement' => $etablissement,
            'inscriptionsTraites' => $inscriptionsTraites,
            'responsable' => $responsable,
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
            'Content-Disposition' => 'inline; filename=carte_retrait_'.$info.date("d/m/Y à H:i").'".pdf"',
        ]);
    }

}
